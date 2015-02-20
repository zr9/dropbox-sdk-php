<?php
namespace Dropbox;

class Util
{
    /**
     * @internal
     */
    public static function q($object)
    {
        return var_export($object, true);
    }
}