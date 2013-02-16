<?php
namespace Dropbox;

final class Exception_BadRequest extends Exception_ProtocolError
{
    function __construct($message = "")
    {
        parent::__construct($message);
    }
}
