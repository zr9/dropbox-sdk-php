<?php
namespace Dropbox;

class Exception_ProtocolError extends Exception
{
    function __construct($message)
    {
        parent::__construct($message);
    }
}
