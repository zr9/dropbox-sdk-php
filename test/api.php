<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../vendor/vierbergenlars/simpletest/autorun.php';

use \Dropbox as dbx;

// Override the exception reporter to print the full stack trace instead of just
// the first line.
class MyReporter extends TextReporter
{
    function paintException(\Exception $ex)
    {
        parent::paintException($ex);
        echo $ex->getTraceAsString();
    }
}
SimpleTest::prefer(new MyReporter());

class ApiTest extends UnitTestCase
{
    var $client;
    var $basePath;

    function __construct()
    {
        $authFile = __DIR__."/test.auth";

        try {
            list($appInfo, $accessToken) = dbx\AuthInfo::loadFromJsonFile($authFile);
        } catch (dbx\AuthInfoLoadException $ex) {
            echo "Error loading auth-info: ".$ex->getMessage()."\n";
            die;
        }

        $userLocale = "en";
        $dbxConfig = new dbx\Config($appInfo, "examples-account-info", $userLocale);
        $this->client = new dbx\Client($dbxConfig, $accessToken);
    }

    var $testFolder;

    private function p($path = null)
    {
        if ($path === null) return $this->testFolder;
        return "{$this->testFolder}/$path";
    }

    function setUp()
    {
        // Create a new folder for the tests to work with.
        $timestamp = \date('Y-M-d H.i.s', \time());
        $basePath = "/PHP SDK Tests/$timestamp";

        $tryPath = $basePath;
        $result = $this->client->createFolder($basePath);
        $i = 2;
        while ($result == null) {
            $tryPath = "$basePath ($i)";
            $i++;
            if ($i >= 100) {
                fwrite(STDERR, "Unable to create folder \"$basePath\"");
                die;
            }
        }

        $this->testFolder = $tryPath;
    }

    function tearDown()
    {
        @unlink("test-dest.txt");
        @unlink("test-media.txt");
        @unlink("test-shared.txt");
        @unlink("test-source.txt");

        $this->client->delete($this->testFolder);
    }

    function writeTempFile($size)
    {
        $fd = tmpfile();

        $chars = "\nabcdefghijklmnopqrstuvwxyz0123456789";
        for ($i = 0; $i < $size; $i++)
        {
            fwrite($fd, $chars[rand() % strlen($chars)]);
        }

        fseek($fd, 0);

        return $fd;
    }

    private function addFile($path, $size, $writeMode = null)
    {
        if ($writeMode === null) $writeMode = dbx\WriteMode::add();

        $fd = $this->writeTempFile($size);
        $result = $this->client->uploadFile($path, $writeMode, $fd, $size);
        $this->assertEqual($size, $result['bytes']);

        return $result;
    }

    private function deleteItem($path)
    {
        echo "deleting $path\n";
        $res = $this->client->delete($path);
        $this->assertTrue($res['is_deleted']);
    }

    private function fetchUrl($url)
    {
        //sadly, https doesn't work out of the box on windows for functions
        //like file_get_contents, so let's make this easy for devs

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $ret = curl_exec($ch);

        curl_close($ch);

        return $ret;
    }

    function testUploadAndDownload()
    {
        $localPathSource = "test-source.txt";
        $localPathDest = "test-dest.txt";
        $contents = "A simple test file";
        file_put_contents($localPathSource, $contents);

        $remotePath = $this->p("test-file.txt");

        $up = $this->client->uploadFile($remotePath, dbx\WriteMode::add(), fopen($localPathSource, "rb"));

        $fd = fopen($localPathDest, "wb");
        $down = $this->client->getFile($remotePath, $fd);
        fclose($fd);

        $this->assertEqual($up['size'], $down['size']);
        $this->assertEqual($up['size'], filesize($localPathSource));
        $this->assertEqual(filesize($localPathDest), filesize($localPathSource));
    }

    function testDelta()
    {
        // eat up all the deltas to the point where we should expect exactly
        // one after we add a new file
        $result = $this->client->getDelta();
        $this->assertTrue($result['reset']);
        $start = $result['cursor'];

        do {
            $result = $this->client->getDelta($start);
            $start = $result['cursor'];
        } while ($result['has_more']);

        $path = $this->p("make a delta.txt");

        $this->addFile($path, 100);
        $result = $this->client->getDelta($start);
        $this->assertEqual(1, count($result['entries']));
        $this->assertEqual($path, $result['entries'][0][1]["path"]);
    }

    function testRevisions()
    {
        $path = $this->p("revisions.txt");
        $this->addFile($path, 100, dbx\WriteMode::force());
        $this->addFile($path, 200, dbx\WriteMode::force());
        $this->addFile($path, 300, dbx\WriteMode::force());

        $revs = $this->client->getRevisions($path);
        $this->assertTrue(count($revs) > 2);

        $revs = $this->client->getRevisions($path, 2);
        $this->assertEqual(2, count($revs));
        $this->assertEqual(300, $revs[0]['size']);
        $this->assertEqual(200, $revs[1]['size']);
    }

    function testRestore()
    {
        $path = $this->p("revisions.txt");
        $resultA = $this->addFile($path, 100);
        $resultB = $this->addFile($path, 200);

        $result = $this->client->restoreFile($path, $resultA['rev']);
        $this->assertEqual(100, $result['size']);

        $final = $this->client->getMetadata($path);
        $this->assertEqual(100, $final['size']);
    }

    function testSearch()
    {
        $this->addFile($this->p("search - a.txt"), 100);
        $this->client->createFolder($this->p("sub"));
        $this->addFile($this->p("sub/search - b.txt"), 200);
        $this->addFile($this->p("search - c.txt"), 200);
        $this->client->delete($this->p("search - c.txt"));

        $result = $this->client->searchFileNames($this->p(), "search");
        $this->assertEqual(2, count($result));

        $result = $this->client->searchFileNames($this->p(), "search", 1);
        $this->assertEqual(1, count($result));

        $result = $this->client->searchFileNames($this->p("sub"), "search");
        $this->assertEqual(1, count($result));

        $result = $this->client->searchFileNames($this->p(), "search", null, true);
        $this->assertEqual(3, count($result));
    }

    function testShares()
    {
        $contents = "A shared text file";
        $remotePath = $this->p("share-me.txt");
        $up = $this->client->uploadFileFromString($remotePath, dbx\WriteMode::add(), $contents);

        $short = $this->client->createShareableLink($remotePath, true);
        $long = $this->client->createShareableLink($remotePath);
        $this->assertTrue(strlen($short['url']) < strlen($long['url']));
        $fetchedStr = $this->fetchUrl($long['url']);
        assert(strlen($fetchedStr) > 5 * strlen($contents)); //should get a big page back
    }

    function testMedia()
    {
        $contents = "A media text file";

        $remotePath = $this->p("media-me.txt");
        $up = $this->client->uploadFileFromString($remotePath, dbx\WriteMode::add(), $contents);

        $result = $this->client->createTemporaryDirectLink($remotePath);
        $fetchedStr = $this->fetchUrl($result['url']);

        $this->assertEqual($contents, $fetchedStr);
    }

    function testCopyRef()
    {
        $source = $this->p("copy-ref me.txt");
        $dest = $this->p("ok - copied ref.txt");
        $size = 1024;

        $this->addFile($source, $size);
        $result = $this->client->createCopyRef($source);
        $ref = $result['copy_ref'];

        $result = $this->client->copyFromCopyRef($ref, $dest);
        $this->assertEqual($size, $result['bytes']);

        $result = $this->client->getMetadataWithChildren($this->p());
        $this->assertEqual(2, count($result['contents']));
    }

    function testThumbnail()
    {
        $remotePath = $this->p("image.jpg");
        $localPath = __DIR__."/upload.jpg";
        $this->client->uploadFile($remotePath, dbx\WriteMode::add(), fopen($localPath, "rb"));

        list($md1, $data1) = $this->client->getThumbnail($remotePath, "jpeg", "xs");
        $this->assertTrue(self::isJpeg($data1));

        list($md2, $data2) = $this->client->getThumbnail($remotePath, "jpeg", "s");
        $this->assertTrue(self::isJpeg($data1));
        $this->assertTrue(strlen($data2) > strlen($data1));

        list($md3, $data3) = $this->client->getThumbnail($remotePath, "png", "s");
        $this->assertTrue(self::isPng($data3));
    }

    static function isJpeg($data)
    {
        $first_two = substr($data, 0, 2);
        $last_two = substr($data, -2);
        return ($first_two === "\xFF\xD8") && ($last_two === "\xFF\xD9");
    }

    static function isPng($data)
    {
        return substr($data, 0, 8) === "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a";
    }

    function testChunkedUpload()
    {
        $fd = $this->writeTempFile(1024);
        $contents = stream_get_contents($fd);
        fseek($fd, 0);

        $remotePath = $this->p("chunked-upload.txt");
        $this->client->uploadFileChunked($remotePath, dbx\WriteMode::add(), $fd, null, 512);

        $fd = tmpfile();
        $this->client->getFile($remotePath, $fd);
        fseek($fd, 0);
        $fetched = stream_get_contents($fd);
        fclose($fd);

        $this->assertEqual($contents, $fetched);
    }

    // --------------- Test File Operations -------------------
    function testCopy()
    {
        $source = $this->p("copy me.txt");
        $dest = $this->p("ok - copied.txt");
        $size = 1024;

        $this->addFile($source, $size);
        $result = $this->client->copy($source, $dest);
        $this->assertEqual($size, $result['bytes']);

        $result = $this->client->getMetadataWithChildren($this->p());
        $this->assertEqual(2, count($result['contents']));
    }

    function testCreateFolder()
    {
        $result = $this->client->getMetadataWithChildren($this->p());
        $this->assertEqual(0, count($result['contents']));

        $this->client->createFolder($this->p("a"));

        $result = $this->client->getMetadataWithChildren($this->p());
        $this->assertEqual(1, count($result['contents']));

        $result = $this->client->getMetadata($this->p("a"));
        $this->assertTrue($result['is_dir']);
    }

    function testDelete()
    {
        $path = $this->p("delete me.txt");
        $size = 1024;

        $this->addFile($path, $size);
        $this->client->delete($path);

        $result = $this->client->getMetadataWithChildren($this->p());
        $this->assertEqual(0, count($result['contents']));
    }

    function testMove()
    {
        $source = $this->p("move me.txt");
        $dest = $this->p("ok - moved.txt");
        $size = 1024;

        $this->addFile($source, $size);
        $result = $this->client->getMetadataWithChildren($this->p());
        $this->assertEqual(1, count($result['contents']));

        $result = $this->client->move($source, $dest);
        $this->assertEqual($size, $result['bytes']);

        $result = $this->client->getMetadataWithChildren($this->p());
        $this->assertEqual(1, count($result['contents']));
    }
}
