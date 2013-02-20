#!/usr/bin/env php
<?php

// NOTE: You should be using Composer's global autoloader.  But just so these examples
// work for people who don't have Composer, we'll use the library's "autoload.php".
require_once __DIR__.'/../lib/Dropbox/autoload.php';
require_once __DIR__.'/helper.php';

use \Dropbox as dbx;

list($client, $dropboxPath, $localPath) = example_init("download-file", $argv, array(
        array("dropbox-path", "The path of the file (on Dropbox) to download."),
        array("local-path", "The local path to save the downloaded file contents to."),
    ));

$pathError = dbx\Path::findError($dropboxPath);
if ($pathError !== null) {
    fwrite(STDERR, "Invalid <dropbox-path>: $pathError\n");
    die;
}

if ($dropboxPath === "/") {
    fwrite(STDERR, "There's no file at \"/\".\n");
    die;
}

$metadata = $client->getFile($dropboxPath, fopen($localPath, "wb"));

print_r($metadata);
echo "File contents written to \"$localPath\"\n";
