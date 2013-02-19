<?php
namespace Dropbox;

/**
 * Use {@link WebAuth::start() start()} and {@link WebAuth::finish() finish()} to guide your
 * user through the process of giving your app access to their Dropbox account.  At the end, you
 * will have a {@link AccessToken}, which you can pass to {@link Client} and start making
 * API calls.
 */
final class WebAuth
{
    /**
     * Whatever Config was passed into the constructor.
     *
     * @return Config
     */
    function getConfig() { return $this->config; }

    /** @var Config */
    private $config;

    /**
     * @param Config $config
     *     {@link getConfig()}
     */
    function __construct($config)
    {
        Config::checkArg("config", $config);
        $this->config = $config;
    }

    /**
     * Tells Dropbox that you want to start authorization and returns the information necessary
     * to continue authorization.  This corresponds to step 1 of the three-step OAuth web flow.
     *
     * <p>
     * After this function returns, direct your user to the returned $authorizeUrl,
     * which gives them a chance to grant your application access to their Dropbox account.  This
     * corresponds to step 2 of the three-step OAuth web flow.
     * </p>
     *
     * <p>
     * If they choose to grant access, they will be redirected to the URL you provide for
     * <code>callbackUrl</code>, after which you should call <code>finish()</code> to get an
     * access token.
     * </p>
     *
     * @param string $callbackUrl
     *    The URL that the Dropbox servers will redirect the user to after the user finishes
     *    authorizing your app.  If this is <code>null</code>, the user
     *    will not be redirected.
     *
     * @throws Exception
     *
     * @return array
     *    A pair of (RequestToken $requestToken, string $authorizeUrl).  Redirect the user's
     *    browser to $authorizeUrl.  When they're done authorizing, call {@link finish()} with
     *    $requestToken.
     */
    function start($callbackUrl)
    {
        Checker::argStringOrNull("callbackUrl", $callbackUrl);

        $url = RequestUtil::buildUri($this->config->getAppInfo()->getHost()->getApi(), "1/oauth/request_token");

        $params = array(
            "oauth_signature_method" => "PLAINTEXT",
            "oauth_consumer_key" => $this->config->getAppInfo()->getKey(),
            "oauth_signature" => rawurlencode($this->config->getAppInfo()->getSecret()) . "&",
            "locale" => $this->config->getUserLocale(),
        );

        $curl = RequestUtil::mkCurlWithoutAuth($this->config, $url);
        $curl->set(CURLOPT_POST, true);
        $curl->set(CURLOPT_POSTFIELDS, RequestUtil::buildPostBody($params));

        $curl->set(CURLOPT_RETURNTRANSFER, true);
        $response = $curl->exec();

        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        $parts = array();
        parse_str($response->body, $parts);
        if (!array_key_exists('oauth_token', $parts)) {
            throw new Exception_BadResponse("Missing \"oauth_token\" parameter.");
        }
        if (!array_key_exists('oauth_token_secret', $parts)) {
            throw new Exception_BadResponse("Missing \"oauth_token_secret\" parameter.");
        }
        $requestToken = new RequestToken($parts['oauth_token'], $parts['oauth_token_secret']);

        $authorizeUrl = RequestUtil::buildUrl(
            $this->config,
            $this->config->getAppInfo()->getHost()->getWeb(),
            "1/oauth/authorize",
            array(
                "oauth_token" => $requestToken->getKey(),
                "oauth_callback" => $callbackUrl,
            ));

        return array($requestToken, $authorizeUrl);
    }

    /**
     * Call this after the user has visited the authorize URL
     * (returned by {@link start()}) and approved your app.  This corresponds to
     * step 3 of the three-step OAuth web flow.
     *
     * @param RequestToken $requestToken
     *    The <code>RequestToken</code> returned by {@link start()}.
     *
     * @return array
     *    A pair of (RequestToken $requestToken, string $dropboxUserId).  Use $requestToken to
     *    construct a {@link Client} object and start making API calls.  $dropboxUserId is the
     *    user's "user ID" on Dropbox and is for your own reference.
     */
    function finish($requestToken)
    {
        RequestToken::checkArg("requestToken", $requestToken);

        $response = RequestUtil::doPost(
            $this->config,
            $requestToken,
            $this->config->getAppInfo()->getHost()->getApi(),
            "1/oauth/access_token");

        if ($response->statusCode !== 200) throw RequestUtil::unexpectedStatus($response);

        $parts = array();
        parse_str($response->body, $parts);
        if (!array_key_exists('oauth_token', $parts)) {
            throw new Exception_BadResponse("Missing \"oauth_token\" parameter.");
        }
        if (!array_key_exists('oauth_token_secret', $parts)) {
            throw new Exception_BadResponse("Missing \"oauth_token_secret\" parameter.");
        }
        if (!array_key_exists('uid', $parts)) {
            throw new Exception_BadResponse("Missing \"uid\" parameter.");
        }

        $accessToken = new AccessToken($parts['oauth_token'], $parts['oauth_token_secret']);
        return array($accessToken, $parts['uid']);
    }
}
