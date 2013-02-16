<?php
namespace Dropbox;

final class DeserializeException extends \Exception
{
    /**
     * @param string $message
     */
    function __construct($message)
    {
        parent::__construct($message);
    }
}
