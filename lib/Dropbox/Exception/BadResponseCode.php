<?php
namespace Dropbox;

/**
 * Thrown when the the Dropbox server responds with an HTTP status code we didn't expect.
 */
final class Exception_BadResponseCode extends Exception_BadResponse
{
    /** @var int */
    var $statusCode;

    /**
     * @param string $message
     * @param int $statusCode
     */
    function __construct($message, $statusCode)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
