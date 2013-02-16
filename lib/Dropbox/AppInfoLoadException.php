<?php
namespace Dropbox;

final class AppInfoLoadException extends \Exception
{
    /**
     * @param string $message
     */
    function __construct($message)
    {
        parent::__construct($message);
    }
}
