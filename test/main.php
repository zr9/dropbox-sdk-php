<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../vendor/vierbergenlars/simpletest/autorun.php';

/*
 * To run everything: php main.php
 * To run a class of tests, eg the stuff in helper.php: php main.php --case=HelperTest
 * To run a single test: php main.php --test=testMissingAuthJson
 */

class MainTests extends TestSuite
{
    function MainTests()
    {
        $this->TestSuite("Main");
        $this->addFile(__DIR__."/api.php");
        $this->addFile(__DIR__."/loading.php");
    }
}
