#!/usr/bin/env php
<?php

// NOTE: You should be using Composer's global autoloader.  But just so these examples
// work for people who don't have Composer, we'll use the library's "autoload.php".
require_once __DIR__.'/../lib/Dropbox/autoload.php';
require_once __DIR__.'/helper.php';

use \Dropbox as dbx;

list($client, $localPath, $dropboxPath) = example_init("upload-file", $argv, array(
        array("local-path", "The local path of the file to upload."),
        array("dropbox-path", "The path (on Dropbox) to save the file to."),
    ));

$pathError = dbx\Path::findError($dropboxPath);
if ($pathError !== null) {
    fwrite(STDERR, "Invalid <dropbox-path>: $pathError\n");
    die;
}

if ($dropboxPath === "/") {
    fwrite(STDERR, "Can't upload a file to \"/\".\n");
    die;
}

$metadata = $client->uploadFile($dropboxPath, dbx\WriteMode::add(), fopen($localPath, "rb"));

print_r($metadata);
