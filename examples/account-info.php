#!/usr/bin/env php
<?php

// NOTE: You should be using Composer's global autoloader.  But just so these examples
// work for people who don't have Composer, we'll use the library's "autoload.php".
require_once __DIR__.'/../lib/Dropbox/autoload.php';
require_once __DIR__.'/helper.php';

list($client) = example_init("account-info", $argv);

$accountInfo = $client->getAccountInfo();

print_r($accountInfo);
