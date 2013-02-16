<?php
namespace Dropbox;

/**
 * When you successfully complete the authorization process, the Dropbox server returns
 * this information to you.
 */
final class AuthFinish
{
    /** @var AccessToken  */
    private $accessToken;
    /** @var string */
    private $userId;

    /**
     * @param AccessToken $accessToken
     *     {@link getAccessToken()}
     * @param string $userId
     *     {@link getUserId()}
     */
    function __construct($accessToken, $userId)
    {
        $this->accessToken = $accessToken;
        $this->userId = $userId;
    }

    /**
     * Returns an access token that can be used to make Dropbox API calls.  Pass this into the
     * {@link Client} constructor.
     *
     * @returns AccessToken
     */
    function getAccessToken() { return $this->accessToken; }

    /**
     * Returns the Dropbox user ID of the user who just approved your app for access to their
     * Dropbox account.
     *
     * @returns string
     */
    function getUserId() { return $this->userId; }
}
