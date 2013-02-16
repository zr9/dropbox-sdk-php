<?php
namespace Dropbox;

class Exception extends \Exception
{
    function __construct($message, $cause = null)
    {
        parent::__construct($message, 0, $cause);
    }
}
