#!/usr/bin/env php
<?php
require_once __DIR__.'/../vendor/autoload.php';  // Use Composer's autoloader.
use \Dropbox as dbx;

if ($argc == 1) {
    echo "\n";
    echo "Usage: ".$argv[0]." <auth-file>\n";
    echo "\n";
    echo "<auth-file>: A file with authorization information.  You can use the\n";
    echo "  \"examples/authorize.php\" program to generate this file.\n";
    echo "\n";
    die;
}
else if ($argc != 2) {
    echo "Expecting exactly 1 argument, got ".($argc - 1)."\n";
    echo "Run with no arguments for help\n";
    die;
}

try {
    list($appInfo, $accessToken) = dbx\AuthInfo::loadFromJsonFile($argv[1]);
}
catch (dbx\AuthInfoLoadException $ex) {
    echo "Error loading <auth-file>: ".$ex->getMessage()."\n";
    die;
}

$dbxConfig = new dbx\Config($appInfo, "examples-account-info");
$dbxClient = new dbx\Client($dbxConfig, $accessToken);

$accountInfo = $dbxClient->getAccountInfo();

print_r($accountInfo);
