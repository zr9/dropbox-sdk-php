<?php
namespace Dropbox;

/**
 * Path validation functions.
 */
final class Path
{
    /**
     * @param string $path
     * @return bool
     */
    static function isValid($path)
    {
        $error = self::findError($path);
        return ($error === null);
    }

    /**
     * @param string $path
     * @return string|null
     */
    static function findError($path)
    {
        if (\substr_compare($path, "/", 0, 1) !== 0) return "must start with \"/\"";
        $l = strlen($path);
        if ($l === 1) return null;  // Special case for "/"

        if ($path[$l-1] === "/") return "must not end with \"/\"";

        // TODO: More checks.

        return null;
    }

    /**
     * @param string $argName
     * @param mixed $value
     * @throws \InvalidArgumentException
     */
    static function checkArg($argName, $value)
    {
        if ($value === null) throw new \InvalidArgumentException("'$argName' must not be null");
        if (!is_string($value)) throw new \InvalidArgumentException("'$argName' must be a string");
        $error = self::findError($value);
        if ($error !== null) throw new \InvalidArgumentException("'$argName'': bad path: $error: ".var_export($value, true));
    }

    /**
     * @param string $argName
     * @param mixed $value
     * @throws \InvalidArgumentException
     */
    static function checkArgNonRoot($argName, $value)
    {
        if ($value === null) throw new \InvalidArgumentException("'$argName' must not be null");
        if (!is_string($value)) throw new \InvalidArgumentException("'$argName' must be a string");
        if ($value === "/") throw new \InvalidArgumentException("'$argName' must not be the root path");
        $error = self::findError($value);
        if ($error !== null) throw new \InvalidArgumentException("'$argName'': bad path: $error: ".var_export($value, true));
    }
}