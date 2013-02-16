<?php
namespace Dropbox;

final class Exception_ServerError extends Exception
{
    function __construct($message = "")
    {
        parent::__construct($message);
    }
}
