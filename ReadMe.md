# Dropbox SDK for PHP 5.3+

A PHP library to access [Dropbox's HTTP-based API](http://dropbox.com/developers/reference/api).

License: [MIT](License.txt)

Requirements:
  * PHP 5.3+, [with 64-bit integers](http://stackoverflow.com/questions/864058/how-to-have-64-bit-integer-on-php).
  * PHP [cURL extension](http://php.net/manual/en/curl.installation.php) with SSL enabled (it's usually built-in).

## Setup

If you're using [Composer](http://getcomposer.org/) for your project's dependencies, add the following to your "composer.json":

```
"require": {
  "dropbox/dropbox-sdk": "1.1.*",
}
```

### Without Composer

If you're not using Composer, download the code, copy the "lib/" folder into your project somewhere, and include the "lib/Dropbox/autoload.php" in your code.  For example, if you copied the "lib/" and named it "dropbox-sdk/", you would do:

```php
// Do this only if you're not using a global autoloader (such as Composer's).
require_once "dropbox-sdk/Dropbox/autoload.php";
```

## Get a Dropbox API key

You need a Dropbox API key to make API requests.
  * Go to: [https://dropbox.com/developers/apps](https://dropbox.com/developers/apps)
  * If you've already registered an app, click on the "Options" link to see the app's API key and secret.
  * Otherwise, click "Create an app" to get register an app.  Choose "Full Dropbox" or "App Folder" [depending on your needs](https://www.dropbox.com/developers/start/core).

Save the API key to a JSON file called, say, "test.app":

```
{
  "key": "Your Dropbox API app key",
  "secret": "Your Dropbox API app secret",
}
```

## Using the Dropbox API

Before your app can access a Dropbox user's files, the user must authorize your application using OAuth 2.  Successfully completing this authorization flow gives you an _access token_ for the user's Dropbox account, which grants you the ability to make Dropbox API calls to access their files.

You only need to perform the authorization process once per user.  Once you have an access token for a user, save it somewhere persistent, like in a database.  The next time that user visits your app's website, you can skip the authorization process and go straight to making regular API calls.  See: [API documentation](http://dropbox.github.io/dropbox-sdk-php/api-docs/v1.1.x/).

### Command-Line Example

Here's a minimal command-line program that runs through the authorization process.  NOTE: A typical web application will need to structure the authorization process differently -- see ["examples/web-file-browser.php"](examples/web-file-browser.php).  

```php
use \Dropbox as dbx;

// Assuming your app's API key and secret are stored in "test.app".
$appInfo = dbx\AppInfo::loadFromJsonFile("test.app");

$clientIdentifier = "auth-example";  // For the HTTP User-Agent header.
$webAuth = new dbx\WebAuth($appInfo, $clientIdentifier);

// Send the user to the Dropbox app authorization page.
// NOTE: A web app would pass in a redirect URL.
$authorizeUrl = $webAuth->getAuthorizeUrlNoRedirect();
// NOTE: A real web app would redirect the user's browser.
echo "1. Go to $authorizeUrl\n";
echo "2. Click \"Allow\" (you might have to log in first).\n";

// NOTE: A real web app would have the authorization code delivered to
// its redirect URL.  But since we're a command-line program, just ask
// the user to copy/paste it.
echo "3. Copy the authorization code.\n";
$authCode = trim(readline("Enter the authorization code here: "));

// Get an access token from Dropbox.
// NOTE: A real web app would save the access token in a database.
list($accessToken, $dropboxUserId) = $webAuth->finish($authCode);
echo "Access Token: $accessToken\n";

// We can now access the user's Dropbox account.  Let's get the user's
// Dropbox account information using the getAccountInfo() method.
$client = new dbx\Client($accessToken, $clientIdentifier);
$accountInfo = $client->getAccountInfo();
print_r($accountInfo);
```

The [`Dropbox\Client`](lib/Dropbox/Client.php) class has methods for most of the public Dropbox API calls.  The documentation comments in that file has an overview of each API call.

## Running the Examples and Tests

1. Download this repository.
2. Save your Dropbox API key in, say, "test.app".  (See: [Get a Dropbox API key](#get-a-dropbox-api-key), above.)

### authorize.php

This example runs through the OAuth authorization flow.

```
./examples/authorize.php test.app test.auth
```

This produces a file named "test.auth" that has the access token.  This file can passed in to the other examples.

### account-info.php

```
./examples/account-info.php test.auth
```

(You can generate "test.auth" using the "authorize.php" example script.)

### web-file-browser.php

A tiny web app that runs through the OAuth authorization flow and then uses Dropbox API calls to let the user browse their Dropbox files.  If you have PHP 5.4+, you can run it using PHP's built-in web server:

```
cp test.app examples/web-file-browser.app
php -S localhost:8080 examples/web-file-browser.php
```

### Running the Tests

1. run: `composer install --dev` to download the dependencies.  (You'll need [Composer](http://getcomposer.org/download/).)
2. Put an "auth info" file in "test/test.auth".  (You can generate "test/test.auth" using the "authorize.php" example script.)

```
./vendor/bin/phpunit test/
```
