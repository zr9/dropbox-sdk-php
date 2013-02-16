<?php
namespace Dropbox;

final class Exception_NetworkIO extends Exception
{
    function __construct($message, $cause = null)
    {
        parent::__construct($message, $cause);
    }
}
