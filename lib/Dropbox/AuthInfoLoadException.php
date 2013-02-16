<?php
namespace Dropbox;

final class AuthInfoLoadException extends \Exception
{
    /**
     * @param string $message
     */
    function __construct($message)
    {
        parent::__construct($message);
    }
}
