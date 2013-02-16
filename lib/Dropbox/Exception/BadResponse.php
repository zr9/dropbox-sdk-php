<?php
namespace Dropbox;

class Exception_BadResponse extends Exception_ProtocolError
{
    function __construct($message)
    {
        parent::__construct($message);
    }
}
