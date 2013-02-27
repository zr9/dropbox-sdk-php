#!/usr/bin/env php
<?php

require_once __DIR__.'/helper.php';
use \Dropbox as dbx;

list($client, $dropboxPath, $localPath) = parseArgs("get-shareable-link", $argv,
    // Required parameters
    array(
        array("dropbox-path", "The path of the file (on Dropbox) to get a shareable link for."),
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

$url = $client->createShareableLink($dropboxPath);
if ($url === null) {
    fwrite(STDERR, "File not found on Dropbox.\n");
    die;
}

print "$url\n";
