<?php
namespace Dropbox;

final class Exception_InvalidAccessToken extends Exception
{
    function __construct($message = "")
    {
        parent::__construct($message);
    }
}
