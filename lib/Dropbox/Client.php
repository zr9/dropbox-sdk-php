<?php
namespace Dropbox;

/**
 * The class used to make most Dropbox API calls.  You can use this once you've gotten an
 * {@link AccessToken} via {@link WebAuth}.
 */
final class Client
{
    /**
     * The Config used when making Dropbox API calls.
     *
     * @return Config
     */
    function getConfig() { return $this->config; }

    /** @var Config */
    private $config;

    /**
     * The access token used by this client to make authenticated API calls.  You can get an
     * access token via {@link WebAuth}.
     *
     * @return AccessToken
     */
    function getAccessToken() { return $this->accessToken; }

    /** @var AccessToken */
    private $accessToken;

    /**
     * @param Config $config
     *     {@link getConfig()}
     * @param AccessToken $accessToken
     *     {@link getAccessToken()}
     */
    function __construct($config, $accessToken)
    {
        Config::checkArg("config", $config);
        AccessToken::checkArg("accessToken", $accessToken);

        $this->config = $config;
        $this->accessToken = $accessToken;

        // These fields are redundant, but it makes these values a little more convenient
        // to access.
        $this->apiHost = $config->getAppInfo()->getHost()->getApi();
        $this->contentHost = $config->getAppInfo()->getHost()->getContent();
        $this->root = $config->getAppInfo()->getAccessType()->getUrlPart();
    }

    /** @var string */
    private $apiHost;
    /** @var string */
    private $contentHost;
    /** @var string */
    private $root;

    private function appendFilePath($base, $path)
    {
        return $base . "/" . $this->root . "/" . rawurlencode(substr($path, 1));
    }

    /**
     * Returns a basic account and quota information.
     *
     * <p>
     * This maps to the <a href="https://www.dropbox.com/developers/reference/api#account-info">/account/info</a> API endpoint.
     * </p>
     */
    function getAccountInfo()
    {
        $response = $this->doGet($this->apiHost, "1/account/info");
        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);
        return RequestUtil::parseResponseJson($response->body);
    }

    /**
     * Downloads a file at the given Dropbox $path.  The file's contents are written to the
     * given $outStream and the file's metadata is returned.
     *
     * <p>
     * This maps to the <a href="https://www.dropbox.com/developers/reference/api#files-GET">/files (GET)</a> API endpoint.
     * </p>
     *
     * @param string $path
     *   The path to the file on Dropbox. 
     *
     * @param resource $outStream
     *   If the file exists, the file contents will be written to this stream.
     *
     * @param string|null $rev
     *   If you want the latest revision of the file at the given path, pass in <code>null</code>.
     *   If you want a specific version of a file, pass in value of the file metadata's "rev" field.
     *
     * @return null|array
     *   The metadata object for the file at the given $path and $rev.  If the file doesn't exist,
     *   you'll get back <code>null</code>.
     */
    function getFile($path, $outStream, $rev = null)
    {
        Path::checkArgNonRoot("path", $path);
        Checker::argResource("outStream", $outStream);
        Checker::argStringNonEmptyOrNull("rev", $rev);

        $url = RequestUtil::buildUrl(
            $this->config,
            $this->contentHost,
            $this->appendFilePath("1/files", $path),
            array("rev" => $rev));

        $curl = self::mkCurl($url);
        $metadataCatcher = new DropboxMetadataHeaderCatcher($curl->handle);
        $streamRelay = new CurlStreamRelay($curl->handle, $outStream);

        $response = $curl->exec();

        if ($response->statusCode === 404) return null;

        if ($response->statusCode !== 200) {
            $response->body = $streamRelay->getErrorBody();
            throw RequestUtil::unexpectedStatus($response);
        }

        return $metadataCatcher->getMetadata();
    }

    /**
     * Calling 'uploadFile' with $numBytes less than this value, will cause this SDK to use the standard
     * /files_put endpoint.  When $numBytes is greater than this value, we'll use the /chunked_upload endpoint.
     */
    const AUTO_CHUNKED_UPLOAD_THRESHOLD = 9863168;  // 8 MB

    const DEFAULT_CHUNK_SIZE = 4194304;  // 4 MB

    /**
     * Creates a file on Dropbox consisting of all the data from $inStream.
     *
     * @param string $path
     *    The Dropbox path to save the file to.
     *
     * @param WriteMode $writeMode
     *    What to do if there's already a file at the given path.
     *
     * @param resource $inStream
     *
     * @param int|null $numBytes
     *    You can pass in <code>null</code> if you don't know.  If you do provide the size, we can perform a
     *    slightly more efficient upload (fewer network round-trips) for files smaller than 8 MB.
     *
     * @return mixed
     *    The metadata object for the newly-added file.
     */
    function uploadFile($path, $writeMode, $inStream, $numBytes = null)
    {
        try {
            Path::checkArgNonRoot("path", $path);
            WriteMode::checkArg("writeMode", $writeMode);
            Checker::argResource("inStream", $inStream);
            Checker::argNatOrNull("numBytes", $numBytes);

            // If we don't know how many bytes are coming, we have to use chunked upload.
            // If $numBytes is large, we elect to use chunked upload.
            // In all other cases, use regular upload.
            if ($numBytes === null || $numBytes > self::AUTO_CHUNKED_UPLOAD_THRESHOLD) {
                $metadata = $this->_uploadFileChunked($path, $writeMode, $inStream, $numBytes, self::DEFAULT_CHUNK_SIZE);
            } else {
                $metadata = $this->_uploadFile($path, $writeMode, function(Curl $curl) use ($inStream, $numBytes) {
                    $curl->set(CURLOPT_PUT, true);
                    $curl->set(CURLOPT_INFILE, $inStream);
                    $curl->set(CURLOPT_INFILESIZE, $numBytes);
                });
            }
        }
        catch (\Exception $ex) {
            fclose($inStream);
            throw $ex;
        }
        fclose($inStream);

        return $metadata;
    }

    function uploadFileFromString($path, $writeMode, $data)
    {
        Path::checkArgNonRoot("path", $path);
        WriteMode::checkArg("writeMode", $writeMode);
        Checker::argString("data", $data);

        return $this->_uploadFile($path, $writeMode, function(Curl $curl) use ($data) {
            $curl->set(CURLOPT_CUSTOMREQUEST, "PUT");
            $curl->set(CURLOPT_POSTFIELDS, $data);
            $curl->addHeader("Content-Type: application/octet-stream");
        });
    }

    function uploadFileChunked($path, $writeMode, $inStream, $numBytes = null, $chunkSize = self::DEFAULT_CHUNK_SIZE)
    {
        try {
            Path::checkArgNonRoot("path", $path);
            WriteMode::checkArg("writeMode", $writeMode);
            Checker::argResource("inStream", $inStream);
            Checker::argNatOrNull("numBytes", $numBytes);
            Checker::argIntPositive("chunkSize", $chunkSize);

            $metadata = $this->_uploadFileChunked($path, $writeMode, $inStream, $numBytes, $chunkSize);
        }
        catch (\Exception $ex) {
            fclose($inStream);
            throw $ex;
        }
        fclose($inStream);

        return $metadata;
    }

    /**
     * @param string $path
     *
     * @param WriteMode $writeMode
     *    What to do if there's already a file at the given path.
     *
     * @param resource $inStream
     *    The source of data to upload.
     *
     * @param int|null $numBytes
     *    You can pass in <code>null</code>.  But if you know how many bytes you expect, pass in that value and
     *    this function will do a sanity check at the end to make sure the number of bytes read
     *    from $inStream matches up.
     *
     * @param int $chunkSize
     *
     * @return array
     *    The metadata object for the newly-added file.
     */
    private function _uploadFileChunked($path, $writeMode, $inStream, $numBytes, $chunkSize)
    {
        Path::checkArg("path", $path);
        WriteMode::checkArg("writeMode", $writeMode);
        Checker::argResource("inStream", $inStream);
        Checker::argNatOrNull("numBytes", $numBytes);
        Checker::argNat("chunkSize", $chunkSize);

        // NOTE: This function performs 3 retries on every call.  This is maybe not the right
        // layer to make retry decisions.  It's also awkward because none of the other calls
        // perform retries.

        assert($chunkSize > 0);

        $data = fread($inStream, $chunkSize);
        $len = strlen($data);

        $client = $this;
        $uploadId = RequestUtil::runWithRetry(3, function() use ($data, $client) {
            return $client->chunkedUploadStart($data);
        });

        $byteOffset = $len;

        while (!feof($inStream)) {
            $data = fread($inStream, $chunkSize);
            $len = strlen($data);

            while (true) {
                $r = RequestUtil::runWithRetry(3, function() use ($client, $uploadId, $byteOffset, $data) {
                    return $client->chunkedUploadContinue($uploadId, $byteOffset, $data);
                });

                if ($r === true) {  // Chunk got uploaded!
                    $byteOffset += $len;
                    break;
                }
                if ($r === false) {  // Server didn't recognize our upload ID
                    // This is very unlikely since we're uploading all the chunks in sequence.
                    throw new Exception_BadResponse("Server forgot our uploadId");
                }

                // Otherwise, the server is at a different byte offset from us.
                $serverByteOffset = $r;
                assert($serverByteOffset !== $byteOffset);  // chunkedUploadContinue ensures this.
                if ($r < $byteOffset) {
                    // An earlier byte offset means the server has lost data we sent earlier.
                    throw new Exception_BadResponse("Server is at an ealier byte offset: us=$byteOffset, server=$serverByteOffset");
                }
                // The normal case is that the server is a bit further along than us because of a
                // partially-uploaded chunk.
                $diff = $serverByteOffset - $byteOffset;
                if ($diff > $len) {
                    throw new Exception_BadResponse("Server is more than a chunk ahead: us=$byteOffset, server=$serverByteOffset");
                }

                // Finish the rest of this chunk.
                $byteOffset += $diff;
                $data = substr($data, $diff);
            }
        }

        if ($numBytes !== null && $byteOffset !== $numBytes) {
            throw new \InvalidArgumentException("You passed numBytes=$numBytes but the stream had $byteOffset bytes.");
        }

        $metadata = RequestUtil::runWithRetry(3, function() use ($client, $uploadId, $path, $writeMode) {
            return $client->chunkedUploadFinish($uploadId, $path, $writeMode);
        });

        return $metadata;
    }

    /**
     * @param string $path
     * @param WriteMode $writeMode
     * @param callable $curlConfigClosure
     * @return array
     */
    private function _uploadFile($path, $writeMode, $curlConfigClosure)
    {
        Path::checkArg("path", $path);
        WriteMode::checkArg("writeMode", $writeMode);
        Checker::argCallable("curlConfigClosure", $curlConfigClosure);

        $url = RequestUtil::buildUrl(
            $this->config,
            $this->contentHost,
            $this->appendFilePath("1/files_put", $path),
            $writeMode->getExtraParams());

        $curl = $this->mkCurl($url);

        $curlConfigClosure($curl);

        $curl->set(CURLOPT_RETURNTRANSFER, true);
        $response = $curl->exec();

        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        return RequestUtil::parseResponseJson($response->body);
    }

    /**
     * Start a new chunked upload session and upload the first chunk of data.
     *
     * @param string $data
     *     The data to start off the chunked upload session.
     *
     * @return array
     *     A pair of (string $uploadId, int $byteOffset).  $uploadId is a unique identifier for this chunked
     *     upload session.  You pass this in to {@link chunkedUploadContinue} and {@link chuunkedUploadFinish}.
     *     $byteOffset is the number of bytes that have been successfully uploaded.
     */
    function chunkedUploadStart($data)
    {
        Checker::argString("data", $data);

        $response = $this->_chunkedUpload(array(), $data);

        if ($response->statusCode === 404) {
            throw new Exception_BadResponse("Got a 404, but we didn't send up an 'upload_id'");
        }

        $correction = self::_chunkedUploadCheckForOffsetCorrection($response);
        if ($correction !== null) {
            throw new Exception_BadResponse("Got an offset-correcting 400 response, but we didn't send an offset");
        }

        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        list($uploadId, $byteOffset) = self::_chunkedUploadParse200Response($response->body);
        $len = strlen($data);
        if ($byteOffset !== $len) {
            throw new Exception_BadResponse("We sent $len bytes, but server returned an offset of $byteOffset");
        }

        return $uploadId;
    }

    /**
     * Append another chunk data to a previously-started chunked upload session.
     *
     * @param string $uploadId
     *     The unique identifier for the chunked upload session.  This is obtained via {@link chunkedUploadStart}.
     *
     * @param int $byteOffset
     *     The number of bytes you think you've already uploaded to the given chunked upload session.  The server will
     *     append the new chunk of data after that point.
     *
     * @param string $data
     *     The data to append to the existing chunked upload session.
     *
     * @return int|bool
     *     If false, it means the server didn't know about the given $uploadId.  This may be because the chunked
     *     upload session has expired (they last around 24 hours).
     *     If true, the chunk was successfully uploaded.
     *     If an integer, it means you and the server don't agree on the current $byteOffset.  The returned integer is
     *     the server's internal byte offset for the chunked upload session.  You need to adjust your input to match.
     */
    function chunkedUploadContinue($uploadId, $byteOffset, $data)
    {
        Checker::argStringNonEmpty("uploadId", $uploadId);
        Checker::argNat("byteOffset", $byteOffset);
        Checker::argString("data", $data);

        $response = $this->_chunkedUpload(array("upload_id" => $uploadId, "offset" => $byteOffset), $data);

        if ($response->statusCode === 404) {
            // The server doesn't know our upload ID.  Maybe it expired?
            return false;
        }

        $correction = self::_chunkedUploadCheckForOffsetCorrection($response);
        if ($correction !== null) {
            list($correctedUploadId, $correctedByteOffset) = $correction;
            if ($correctedUploadId !== $uploadId) {
                throw new Exception_BadResponse("Corrective 400 upload_id mismatch: us=".var_export($uploadId, true)." server=".var_export($correctedUploadId, true));
            }
            if ($correctedByteOffset === $byteOffset) {
                throw new Exception_BadResponse("Corrective 400 offset is the same as ours: $byteOffset");
            }
            return $correctedByteOffset;
        }

        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);
        list($returnedUploadId, $returnedByteOffset) = self::_chunkedUploadParse200Response($response->body);

        $nextByteOffset = $byteOffset + strlen($data);
        if ($uploadId !== $returnedUploadId) {
            throw new Exception_BadResponse("upload_id mismatch: us=".var_export($uploadId, true).", server=".var_export($uploadId, true));
        }
        if ($nextByteOffset !== $returnedByteOffset) {
            throw new Exception_BadResponse("next-offset mismatch: us=$nextByteOffset, server=$returnedByteOffset");
        }

        return true;
    }

    /**
     * @param string $body
     * @return array
     */
    private static function _chunkedUploadParse200Response($body)
    {
        $j = RequestUtil::parseResponseJson($body);
        if (!array_key_exists("upload_id", $j)) throw new Exception_BadResponse("Missing field \"upload_id\": $body");
        if (!array_key_exists("offset", $j)) throw new Exception_BadResponse("Missing field \"offset\": $body");
        $uploadId = $j["upload_id"];
        $byteOffset = $j["offset"];
        return array($uploadId, $byteOffset);
    }

    /**
     * @param HttpResponse $response
     * @return array|null
     */
    private static function _chunkedUploadCheckForOffsetCorrection($response)
    {
        if ($response->statusCode !== 400) return null;
        $j = json_decode($response->body, true);
        if ($j === null) return null;
        if (!array_key_exists("upload_id", $j) || !array_key_exists("upload_id", $j)) return null;
        $uploadId = $j["upload_id"];
        $byteOffset = $j["offset"];
        return array($uploadId, $byteOffset);
    }

    /**
     * Creates a file on Dropbox using the accumulated contents of the given chunked upload session.
     *
     * @param string $uploadId
     *     The unique identifier for the chunked upload session.  This is obtained via {@link chunkedUploadStart}.
     *
     * @param string $path
     *    The Dropbox path to save the file to.
     *
     * @param WriteMode $writeMode
     *    What to do if there's already a file at the given path.
     *
     * @return array|null
     *    If <code>null</code>, it means the Dropbox server wasn't aware of the uploadId you gave it.
     *    Otherwise, you get back the metadata object for the newly-created file.
     */
    function chunkedUploadFinish($uploadId, $path, $writeMode)
    {
        Checker::argStringNonEmpty("uploadId", $uploadId);
        Path::checkArgNonRoot("path", $path);
        WriteMode::checkArg("writeMode", $writeMode);

        $params = array_merge(array("upload_id" => $uploadId), $writeMode->getExtraParams());

        $response = $this->doPost(
            $this->contentHost,
            $this->appendFilePath("1/commit_chunked_upload", $path),
            $params);

        if ($response->statusCode === 404) return null;
        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        return RequestUtil::parseResponseJson($response->body);
    }

    /**
     * @param array $params
     * @param string $data
     * @return HttpResponse
     */
    private function _chunkedUpload($params, $data)
    {
        $url = RequestUtil::buildUrl($this->config, $this->contentHost, "1/chunked_upload", $params);

        $curl = $this->mkCurl($url);

        // We can't use CURLOPT_PUT because it wants a stream, but we already have $data in memory.
        $curl->set(CURLOPT_CUSTOMREQUEST, "PUT");
        $curl->set(CURLOPT_POSTFIELDS, $data);
        $curl->addHeader("Content-Type: application/octet-stream");

        $curl->set(CURLOPT_RETURNTRANSFER, true);
        return $curl->exec();
    }

    /**
     * @param $path
     * @return array
     */
    function getMetadata($path)
    {
        Path::checkArg("path", $path);

        return $this->_getMetadata($path, array("list" => "false"));
    }

    /**
     * @param $path
     * @return array
     */
    function getMetadataWithChildren($path)
    {
        Path::checkArg("path", $path);

        return $this->_getMetadata($path, array("list" => "true", "file_limit" => "25000"));
    }

    /**
     * @param string $path
     * @param array $params
     * @return array
     */
    private function _getMetadata($path, $params)
    {
        $response = $this->doGet(
            $this->apiHost,
            $this->appendFilePath("1/metadata", $path),
            $params);

        if ($response->statusCode === 404) return null;
        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        $metadata = RequestUtil::parseResponseJson($response->body);
        if (array_key_exists("is_deleted", $metadata) && $metadata["is_deleted"]) return null;
        return $metadata;
    }

    /**
     * If you've previously retrieved the metadata for a folder and its children, this method will retrieve updated
     * metadata only if something has changed.
     *
     * @param string $path
     *
     * @param string $previousFolderHash
     *    The "hash" field from the previously retrieved folder metadata.
     *
     * @return array
     *    An <code>array(boolean $changed, array $metadata)</code>.  If the metadata hasn't changed,
     *    you'll get <code>array(false, null)</code>.  If the metadata has changed, you'll get
     *    <code>array(true, $newMetadata)</code>.
     */
    function getMetadataWithChildrenIfChanged($path, $previousFolderHash)
    {
        Path::checkArg("path", $path);
        Checker::argStringNonEmpty("previousFolderHash", $previousFolderHash);

        $params = array("list" => "true", "limit" => "25000", "hash" => $previousFolderHash);

        $response = $this->doGet(
            $this->apiHost, "1/metadata",
            $this->appendFilePath("1/metadata", $path),
            $params);

        if ($response->statusCode === 304) return array(false, null);
        if ($response->statusCode === 404) return array(true, null);
        if ($response->statusCode !== 404) throw RequestUtil::unexpectedStatus($response);

        $metadata = RequestUtil::parseResponseJson($response->body);
        if (array_key_exists("is_deleted", $metadata) && $metadata["is_deleted"]) return array(true, null);
        return array(true, $metadata);
    }

    /**
     * A way of letting you keep up with changes to files and folders in a user's Dropbox.
     *
     * <p>
     * This maps to the <a href="https://www.dropbox.com/developers/reference/api#delta">/delta</a> API endpoint.
     * </p>
     */
    function getDelta($cursor = null)
    {
        Checker::argStringNonEmptyOrNull("cursor", $cursor);

        $response = $this->doPost($this->apiHost, "1/delta", array("cursor" => $cursor));

        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        return RequestUtil::parseResponseJson($response->body);
    }

    /**
     * Obtains metadata for the previous revisions of a file.
     *
     * <p>
     * This maps to the <a href="https://www.dropbox.com/developers/reference/api#revisions">/revisions</a> API endpoint.
     * </p>
     */
    function getRevisions($path, $limit = null)
    {
        Path::checkArgNonRoot("path", $path);
        Checker::argIntPositiveOrNull("limit", $limit);

        $response = $this->doGet(
            $this->apiHost,
            $this->appendFilePath("1/revisions", $path),
            array("rev_limit" => $limit));

        if ($response->statusCode === 406) return null;
        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        return RequestUtil::parseResponseJson($response->body);
    }

    /**
     * Restores a file path to a previous revision.
     *
     * <p>
     * This maps to the <a href="https://www.dropbox.com/developers/reference/api#restore">/restore</a> API endpoint.
     * </p>
     */
    function restoreFile($path, $rev)
    {
        Path::checkArgNonRoot("path", $path);
        Checker::argStringNonEmpty("rev", $rev);

        $response = $this->doPost(
            $this->apiHost,
            $this->appendFilePath("1/restore", $path),
            array("rev" => $rev));

        if ($response->statusCode === 404) return null;
        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        return RequestUtil::parseResponseJson($response->body);
    }

    /**
     * Returns metadata for all files and folders whose filename contains the given search string as a substring.
     *
     * <p>
     * This maps to the <a href="https://www.dropbox.com/developers/reference/api#search">/search</a> API endpoint.
     * </p>
     */
    function searchFileNames($basePath, $query, $limit = null, $includeDeleted = false)
    {
        Path::checkArg("basePath", $basePath);
        Checker::argStringNonEmpty("query", $query);
        Checker::argNatOrNull("limit", $limit);
        Checker::argBool("includeDeleted", $includeDeleted);

        $response = $this->doPost(
            $this->apiHost,
            $this->appendFilePath("1/search", $basePath),
            array(
                "query" => $query,
                "file_limit" => $limit,
                "include_deleted" => $includeDeleted,
            ));

        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        return RequestUtil::parseResponseJson($response->body);
    }

    /**
     * Creates and returns a Dropbox link to files or folders users can use to view a preview of the file in a web browser.
     *
     * <p>
     * This maps to the <a href="https://www.dropbox.com/developers/reference/api#shares">/shares</a> API endpoint.
     * </p>
     */
    function createShareableLink($path, $shortUrl = false)
    {
        Path::checkArg("path", $path);
        Checker::argBool("shortUrl", $shortUrl);

        $response = $this->doPost(
            $this->apiHost,
            $this->appendFilePath("1/shares", $path),
            array(
                "short_url" => $shortUrl,
            ));

        if ($response->statusCode === 404) return null;
        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        return RequestUtil::parseResponseJson($response->body);
    }

    /**
     * Returns a link directly to a file.
     *
     * <p>
     * This maps to the <a href="https://www.dropbox.com/developers/reference/api#media">/media</a> API endpoint.
     * </p>
     */
    function createTemporaryDirectLink($path)
    {
        Path::checkArgNonRoot("path", $path);

        $response = $this->doPost(
            $this->apiHost,
            $this->appendFilePath("1/media", $path));

        if ($response->statusCode === 404) return null;
        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        return RequestUtil::parseResponseJson($response->body);
    }

    /**
     * Creates and returns a copy_ref to a file.
     *
     * <p>
     * This maps to the <a href="https://www.dropbox.com/developers/reference/api#copy_ref">/copy_ref</a> API endpoint.
     * </p>
     */
    function createCopyRef($path)
    {
        Path::checkArg("path", $path);

        $response = $this->doGet(
            $this->apiHost,
            $this->appendFilePath("1/copy_ref", $path));

        if ($response->statusCode === 404) return null;
        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        return RequestUtil::parseResponseJson($response->body);
    }

    /**
     * Gets a thumbnail image representation of the file at the given path.
     *
     * @param string $path
     *
     * @param string $format
     *    One of the two image formats: "jpeg" or "png".
     *
     * @param string $size
     *    One of the predefined image size names, as a string:
     *    <ul>
     *    <li>"xs" - 32x32</li>
     *    <li>"s" - 64x64</li>
     *    <li>"m" - 128x128</li>
     *    <li>"l" - 640x480</li>
     *    <li>"xl" - 1024x768</li>
     *    </ul>
     *
     * @return array
     *    A list of (array $metadata, string $data).  $metadata is the original file's metadata.
     *    $data is the raw data for the thumbnail image.
     */
    function getThumbnail($path, $format, $size)
    {
        Path::checkArgNonRoot("path", $path);
        Checker::argString("format", $format);
        Checker::argString("size", $size);
        if (!in_array($format, array("jpeg", "png"))) {
            throw new \InvalidArgumentException("Invalid 'format': ".var_export($format, true));
        }
        if (!in_array($size, array("xs", "s", "m", "l", "xl"))) {
            throw new \InvalidArgumentException("Invalid 'size': ".var_export($format, true));
        }

        $url = RequestUtil::buildUrl(
            $this->config,
            $this->contentHost,
            $this->appendFilePath("1/thumbnails", $path),
            array("size" => $size, "format" => $format));

        $curl = self::mkCurl($url);
        $metadataCatcher = new DropboxMetadataHeaderCatcher($curl->handle);

        $curl->set(CURLOPT_RETURNTRANSFER, true);
        $response = $curl->exec();

        if ($response->statusCode === 404) return null;
        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        $metadata = $metadataCatcher->getMetadata();
        return array($metadata, $response->body);
    }

    /**
     * Copies a file or folder to a new location
     *
     * <p>
     * This maps to the <a href="https://www.dropbox.com/developers/reference/api#fileops-copy">/fileops/copy</a> API endpoint.
     * </p>
     */
    function copy($fromPath, $toPath)
    {
        Path::checkArg("fromPath", $fromPath);
        Path::checkArgNonRoot("toPath", $toPath);

        $response = $this->doPost(
            $this->apiHost,
            "1/fileops/copy",
            array(
                "root" => $this->root,
                "from_path" => $fromPath,
                "to_path" => $toPath,
            ));

        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        return RequestUtil::parseResponseJson($response->body);
    }

    function copyFromCopyRef($copyRef, $toPath)
    {
        Checker::argStringNonEmpty("copyRef", $copyRef);
        Path::checkArgNonRoot("toPath", $toPath);

        $response = $this->doPost(
            $this->apiHost,
            "1/fileops/copy",
            array(
                "root" => $this->root,
                "from_copy_ref" => $copyRef,
                "to_path" => $toPath,
            )
        );

        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        return RequestUtil::parseResponseJson($response->body);
    }

    /**
     * Creates a folder.
     *
     * <p>
     * This maps to the <a href="https://www.dropbox.com/developers/reference/api#fileops-create-folder">/fileops/create_folder</a> API endpoint.
     * </p>
     *
     * @param string $path
     *
     * @return array|null
     *    If successful, you'll get back the metadata object for the newly-created folder.
     *    If not successful, you'll get back <code>null</code>.
     */
    function createFolder($path)
    {
        Path::checkArgNonRoot("path", $path);

        $response = $this->doPost(
            $this->apiHost,
            "1/fileops/create_folder",
            array(
                "root" => $this->root,
                "path" => $path,
            ));

        if ($response->statusCode === 403) return null;
        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        return RequestUtil::parseResponseJson($response->body);
    }

    /**
     * Deletes a file or folder
     *
     * <p>
     * This maps to the <a href="https://www.dropbox.com/developers/reference/api#fileops-delete">/fileops/delete</a> API endpoint.
     * </p>
     */
    function delete($path)
    {
        Path::checkArgNonRoot("path", $path);

        $response = $this->doPost(
            $this->apiHost,
            "1/fileops/delete",
            array(
                "root" => $this->root,
                "path" => $path,
            ));

        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        return RequestUtil::parseResponseJson($response->body);
    }

    /**
     * Moves a file or folder to a new location.
     *
     * <p>
     * This maps to the <a href="https://www.dropbox.com/developers/reference/api#fileops-move">/fileops/move</a> API endpoint.
     * </p>
     */
    function move($fromPath, $toPath)
    {
        Path::checkArgNonRoot("fromPath", $fromPath);
        Path::checkArgNonRoot("toPath", $toPath);

        $response = $this->doPost(
            $this->apiHost,
            "1/fileops/move",
            array(
                "root" => $this->root,
                "from_path" => $fromPath,
                "to_path" => $toPath,
            ));

        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        return RequestUtil::parseResponseJson($response->body);
    }

    /**
     * @param string $host
     * @param string $path
     * @param array|null $params
     * @return HttpResponse
     */
    public function doGet($host, $path, $params = null)
    {
        Checker::argString("host", $host);
        Checker::argString("path", $path);
        return RequestUtil::doGet($this->config, $this->accessToken, $host, $path, $params);
    }

    /**
     * @param string $host
     * @param string $path
     * @param array|null $params
     * @return HttpResponse
     */
    public function doPost($host, $path, $params = null)
    {
        Checker::argString("host", $host);
        Checker::argString("path", $path);
        return RequestUtil::doPost($this->config, $this->accessToken, $host, $path, $params);
    }

    /**
     * @param string $url
     * @return Curl
     */
    public function mkCurl($url)
    {
        return RequestUtil::mkCurl($this->config, $url, $this->accessToken);
    }
}
