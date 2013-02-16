<?php
namespace Dropbox;

/**
 * Returned by {@link WebAuth::start()}
 */
final class WebAuthStart
{
    /** @var RequestToken  */
    private $requestToken;
    /** @var string */
    private $authorizeUrl;

    /**
     * The OAuth request token (used by the Dropbox server to identify the OAuth session.
     * Needs to be passed to {@link WebAuth::finish()}.
     *
     * @return RequestToken
     */
    function getRequestToken() { return $this->requestToken; }

    /**
     * This URL is a page on the Dropbox website that asks the user "do you want to give this
     * app access to your files?", with buttons to allow or deny.  Redirect the user's browser to
     * this URL.  This corresponds to step 3 of the three-step OAuth web flow.
     *
     * @return string
     */
    function getAuthorizeUrl() { return $this->authorizeUrl; }

    /**
     * @param RequestToken $requestToken
     *     {@link $requestToken}
     * @param string $authorizeUrl
     *     {@link $authorizeUrl}
     */
    function __construct($requestToken, $authorizeUrl)
    {
        $this->requestToken = $requestToken;
        $this->authorizeUrl = $authorizeUrl;
    }
}
