<?php
namespace Dropbox;

/**
 * Common parent class for {@link AccessToken} and {@link RequestToken}.
 */
abstract class Token
{
    /** @var string */
    private $key;

    /** @var string */
    private $secret;

    const SERIALIZE_DIVIDER = '|';

    /**
     * @param string $key
     * @param string $secret
     */
    function __construct($key, $secret)
    {
        self::checkKeyArg($key);
        self::checkSecretArg($key);

        $this->key = $key;
        $this->secret = $secret;
    }

    /**
     * Returns the 'key' part of this token, though there's really no reason for you to
     * deal with just the 'key' part.  If you want to save the token somewhere,
     * you can call {@link serialize()} to get a single string representation for
     * the whole object.
     *
     * @return string
     */
    function getKey() { return $this->key; }

    /**
     * Returns the 'secret' part of this token, though there's really no reason for you to
     * deal with just the 'secret' part.  If you want to save the token somewhere,
     * you can call {@link serialize()} to get a single string representation for
     * the whole object.
     *
     * @return string
     */
    function getSecret() { return $this->secret; }

    function __toString()
    {
        return "{key=\"" . $this->key . "\", secret=\"" . $this->secret . "\"}";
    }

    abstract function serialize();

    /**
     * @param string $typeTag
     * @return string
     */
    protected function serializeWithTag($typeTag)
    {
        return $typeTag . $this->key . self::SERIALIZE_DIVIDER . $this->secret;
    }

    /**
     * @param string $typeTag
     * @param string $data
     * @return string[]
     *
     * @throws DeserializeException
     *    If the format of the input message isn't correct.
     */
    static protected function deserializeWithTag($typeTag, $data)
    {
        $prefix = substr($data, 0, strlen($typeTag));
        if ($prefix !== $typeTag) throw new DeserializeException("expecting prefix \"" . $typeTag . "\"");

        $rest = substr($data, strlen($typeTag));
        $divPos = strpos($rest, self::SERIALIZE_DIVIDER);
        if ($divPos === false) throw new DeserializeException("missing \"".self::SERIALIZE_DIVIDER."\" divider");

        $key = substr($rest, 0, $divPos);
        $secret = substr($rest, $divPos+1, strlen($rest) - $divPos - 1);

        $keyError = self::getTokenPartError($key);
        if ($keyError !== null) throw new DeserializeException("invalid \"key\" part: " . $keyError);
        $secretError = self::getTokenPartError($secret);
        if ($secretError !== null) throw new DeserializeException("invalid \"secret\" part: " . $secretError);

        return array($key, $secret);
    }

    static function getTokenPartError($s)
    {
        if ($s === null) return "can't be null";
        if (strlen($s) === 0) return "can't be empty";
        if (strstr($s, ' ')) return "can't contain a space";
        if (strstr($s, self::SERIALIZE_DIVIDER)) return "can't contain a \"".self::SERIALIZE_DIVIDER."\"";
        return null;  // 'null' means "no error"
    }

    static function checkKeyArg($key)
    {
        $error = self::getTokenPartError($key);
        if ($error === null) return;
        throw new \InvalidArgumentException("Bad 'key': \"$key\": $error.");
    }

    static function checkSecretArg($secret)
    {
        $error = self::getTokenPartError($secret);
        if ($error === null) return;
        throw new \InvalidArgumentException("Bad 'secret': \"$secret\": $error.");
    }

    static function checkArg($argName, $argValue)
    {
        if (!($argValue instanceof self)) Checker::throwError($argName, $argValue, __CLASS__);
    }

    static function checkArgOrNull($argName, $argValue)
    {
        if ($argValue === null) return;
        if (!($argValue instanceof self)) Checker::throwError($argName, $argValue, __CLASS__);
    }
}
