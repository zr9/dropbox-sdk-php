<?php
use \Dropbox as dbx;

/**
 * A helper function that checks for the correct number of command line arguments,
 * loads the auth-file, and creates a \Dropbox\Client object.
 *
 * It returns an array where the first element is the \Dropbox\Client object and the
 * rest of the elements are the arguments you wanted.
 */
function example_init($example_name, $argv, $params = null)
{
    if ($params === null) $params = array();

    $required_args = 1 + count($params);
    $given_args = count($argv) - 1;

    // If no args.  Print help message.
    if ($given_args === 0) {
        $params_in_angles = array_map(
            function ($param_spec) { return "<".$param_spec[0].">"; },
            $params);
        echo "\n";
        echo "Usage: ".$argv[0]." <auth-file> ".implode(" ", $params_in_angles)."\n";
        echo "\n";
        echo "<auth-file>: A file with authorization information.  You can use the\n";
        echo "  \"examples/authorize.php\" program to generate this file.\n";
        echo "\n";
        foreach ($params as $param) {
            list($param_name, $param_desc) = $param;
            $lines = wordwrap("<$param_name>: $param_desc", 70, "\n  ");
            echo "$lines\n\n";
        }
        exit(0);
    }

    if ($given_args !== $required_args) {
        fwrite(STDERR, "Expecting exactly $required_args arguments, got $given_args.\n");
        fwrite(STDERR, "Run with no arguments for help.\n");
        die;
    }

    try {
        list($appInfo, $accessToken) = dbx\AuthInfo::loadFromJsonFile($argv[1]);
    }
    catch (dbx\AuthInfoLoadException $ex) {
        fwrite(STDERR, "Error loading <auth-file>: ".$ex->getMessage()."\n");
        die;
    }

    $config = new dbx\Config($appInfo, "examples-$example_name");
    $client = new dbx\Client($config, $accessToken);

    // Return the args they need, but prepend the $client object.
    $ret = array_slice($argv, 2);
    array_unshift($ret, $client);
    return $ret;
}
