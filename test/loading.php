<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../vendor/vierbergenlars/simpletest/autorun.php';

use \Dropbox as dbx;

class LoadingTest extends UnitTestCase
{
    function setUp()
    {
    }

    function tearDown()
    {
        @unlink("test.json");
    }

    function testMissingAppJson()
    {
        $this->expectException('Dropbox\AppInfoLoadException');
        dbx\AppInfo::loadFromJsonFile("missing.json");
    }

    function testBadAppJson()
    {
        $this->expectException('Dropbox\AppInfoLoadException');
        file_put_contents("test.json", "Not JSON.  At all.");
        dbx\AppInfo::loadFromJsonFile("test.json");
    }

    function testNonHashAppJson()
    {
        $this->expectException('Dropbox\AppInfoLoadException');
        file_put_contents("test.json", json_encode( 123, TRUE ));
        dbx\AppInfo::loadFromJsonFile("test.json");
    }

    function testBadAppJsonFields()
    {
        $correct = array(
            "key" => "an_app_key",
            "secret" => "an_app_secret",
            "access_type" => "AppFolder"
        );

        // check that we detect every missing field
        foreach ($correct as $key => $value)
        {
            $tmp = $correct;
            unset($tmp[$key]);

            file_put_contents("test.json", json_encode($tmp, TRUE));

            try
            {
                dbx\AppInfo::loadFromJsonFile("test.json");
                $this->fail("Expected exception");
            }
            catch (dbx\AppInfoLoadException $e)
            {
                print $e->getMessage()."\n";
            }
        }

        // check that we detect non-string fields
        foreach ($correct as $key => $value)
        {
            $tmp = $correct;
            $tmp[$key] = 123;

            file_put_contents("test.json", json_encode($tmp, TRUE));

            try
            {
                dbx\AppInfo::loadFromJsonFile("test.json");
                $this->fail("Expected exception");
            }
            catch (dbx\AppInfoLoadException $e)
            {
                print $e->getMessage()."\n";
            }
        }
    }

    function testAppJsonServer()
    {
        $correct = array(
            "key" => "an_app_key",
            "secret" => "an_app_secret",
            "access_type" => "AppFolder",
            "host" => "test.droppishbox.com"
        );

        file_put_contents("test.json", json_encode($correct, TRUE));
        $appInfo = dbx\AppInfo::loadFromJsonFile("test.json");
        $this->assertEqual($appInfo->getHost()->getContent(), "api-content-test.droppishbox.com");
        $this->assertEqual($appInfo->getHost()->getApi(), "api-test.droppishbox.com");
        $this->assertEqual($appInfo->getHost()->getWeb(), "meta-test.droppishbox.com");
    }

    function testMissingAuthJson()
    {
        $this->expectException('Dropbox\AuthInfoLoadException');
        dbx\AuthInfo::loadFromJsonFile("missing.json");
    }

    function testBadAuthJson()
    {
        $this->expectException('Dropbox\AuthInfoLoadException');
        file_put_contents("test.json", "Not JSON.  At all.");
        dbx\AuthInfo::loadFromJsonFile("test.json");
    }

    function testNonHashAuthJson()
    {
        $this->expectException('Dropbox\AuthInfoLoadException');
        file_put_contents("test.json", json_encode( 123, TRUE ));
        dbx\AuthInfo::loadFromJsonFile("test.json");
    }

    function testBadAuthJsonFields()
    {
        $correct = array(
            "app" => array(
                "key" => "an_app_key",
                "secret" => "an_app_secret",
                "access_type" => "AppFolder"
            ),
            "access_token" => "an_access_token"
        );

        // check that we detect every missing field
        foreach ($correct as $key => $value)
        {
            $tmp = $correct;
            unset($tmp[$key]);

            file_put_contents("test.json", json_encode($tmp, TRUE));

            try
            {
                dbx\AuthInfo::loadFromJsonFile("test.json");
                $this->fail("Expected exception");
            }
            catch (dbx\AuthInfoLoadException $e)
            {
                print $e->getMessage()."\n";
            }
        }

        // check that we detect non-string fields
        foreach ($correct as $key => $value)
        {
            $tmp = $correct;
            $tmp[$key] = 123;

            file_put_contents("test.json", json_encode($tmp, TRUE));

            try
            {
                dbx\AuthInfo::loadFromJsonFile("test.json");
                $this->fail("Expected exception");
            }
            catch (dbx\AuthInfoLoadException $e)
            {
                print $e->getMessage()."\n";
            }
        }
    }

}
