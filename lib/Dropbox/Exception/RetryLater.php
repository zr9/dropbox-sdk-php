<?php
namespace Dropbox;

final class Exception_RetryLater extends Exception
{
    function __construct($message)
    {
        parent::__construct($message);
    }
}
