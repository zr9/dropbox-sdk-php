<?php

require_once __DIR__."/../test/strict.php";

// NOTE: You should be using Composer's global autoloader.  But just so these examples
// work for people who don't have Composer, we'll use the library's "autoload.php".
require_once __DIR__.'/../lib/Dropbox/autoload.php';
use \Dropbox as dbx;

/**
 * A helper function that checks for the correct number of command line arguments,
 * loads the auth-file, and creates a \Dropbox\Client object.
 *
 * It returns an array where the first element is the \Dropbox\Client object and the
 * rest of the elements are the arguments you wanted.
 */
function parseArgs($exampleName, $argv, $requiredParams = null, $optionalParams = null)
{

    if ($requiredParams === null) $requiredParams = array();
    if ($optionalParams === null) $optionalParams = array();

    $minArgs = 1 + count($requiredParams);
    $maxArgs = $minArgs + count($optionalParams);
    $givenArgs = count($argv) - 1;

    // If no args.  Print help message.
    if ($givenArgs === 0) {
        // Construct the param list for the "Usage" line.
        $paramSpec = "";
        foreach ($requiredParams as $p) {
            $paramSpec .= " ".$p[0];
        }
        if (count($optionalParams) > 0) {
            $paramSpec .= " [";
            foreach ($optionalParams as $p) {
                $paramSpec .= " ".$p[0];
            }
            $paramSpec .= " ]";
        }

        echo "\n";
        echo "Usage: ".$argv[0]." auth-file".$paramSpec."\n";
        echo "\n";

        // Print out help for each param.
        printParamHelp("auth-file",
            "A file with authorization information.  You can use the \"examples/authorize.php\" ".
            "program to generate this file.");
        foreach (array_merge($requiredParams, $optionalParams) as $param) {
            list($paramName, $paramDesc) = $param;
            printParamHelp($paramName, $paramDesc);
        }
        exit(0);
    }

    // Make sure the argument count is compatible with the parmaeter count.
    if ($minArgs === $maxArgs) {
        if ($givenArgs != $minArgs) {
            fwrite(STDERR, "Expecting exactly $minArgs arguments, got $givenArgs.\n");
            fwrite(STDERR, "Run with no arguments for help.\n");
            die;
        }
    }
    else {
        if ($givenArgs < $minArgs) {
            fwrite(STDERR, "Expecting at least $minArgs arguments, got $givenArgs.\n");
            fwrite(STDERR, "Run with no arguments for help.\n");
            die;
        }
        else if ($givenArgs > $maxArgs) {
            fwrite(STDERR, "Expecting at most $maxArgs arguments, got $givenArgs.\n");
            fwrite(STDERR, "Run with no arguments for help.\n");
            die;
        }
    }

    try {
        list($appInfo, $accessToken) = dbx\AuthInfo::loadFromJsonFile($argv[1]);
    }
    catch (dbx\AuthInfoLoadException $ex) {
        fwrite(STDERR, "Error loading <auth-file>: ".$ex->getMessage()."\n");
        die;
    }

    $config = new dbx\Config($appInfo, "examples-$exampleName");
    $client = new dbx\Client($config, $accessToken);

    // Fill in the extra/optional arg slots with nulls.
    $ret = array_slice($argv, 2);
    while (count($ret) < $maxArgs) {
        array_push($ret, null);
    }

    // Return the args they need, plus the $client object in front.
    array_unshift($ret, $client);
    return $ret;
}

function printParamHelp($paramName, $paramDesc)
{
    $wordWrapWidth = 70;
    $lines = wordwrap("$paramName: $paramDesc", $wordWrapWidth, "\n  ");
    echo "$lines\n\n";
}
