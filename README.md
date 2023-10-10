# Utopia Fetch
Lite & fast micro PHP library that provides a convenient and flexible way to perform HTTP requests in PHP applications.

# Usage
The library provides a static method `Client::fetch()` that returns a `Response` object.

The `Client::fetch()` method accepts the following parameters:
- `url` - A **String** containing the URL to which the request is sent.
- `method` - A **String** containing the HTTP method for the request. The default method is `GET`.
- `headers` - An **associative array** of HTTP headers to send.
- `body` - An **associative array** of data to send as the body of the request.
- `query` - An **associative array** of query parameters.
- `timeout` - An **integer** representing the maximum number of seconds to allow cURL functions to execute, the default is `15`.
  
The `Response` object has the following methods:
- `isOk()` - Returns **true** if the response status code is in the range 200-299, **false** otherwise.
- `getBody()` - Returns the response body as a **String**.
- `getHeaders()` - Returns an **associative array** of response headers.
- `getStatusCode()` - Returns the response status code as an **integer**.
- `getMethod()` - Returns the request method as a **String**.
- `getUrl()` - Returns the request URL as a **String**.
- `text()` - Returns the response body as a **String**.
- `json()` - Returns the response body as an **associative array**.
- `blob()` - Converts the response body to blob and return it as a **String**.
  
## Examples
### GET request
```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
use Utopia\Fetch\Client;

$url = 'https://httpbin.org/get';
$method = 'GET';
$query = [
  'foo' => 'bar'
];

$resp = Client::fetch(
  url: $url,
  method: $method,
  query: $query
);

if($resp->isOk()) { 
  print("Status Code: " . $resp->getStatusCode() . "\n");
  print("Response Headers:\n");
  print_r($resp->getHeaders());
  print("Response Body:\n");
  print($resp->getBody());
}
else {
  print("Error: " . $resp->getStatusCode() . "\n");
  print("Response Headers:\n");
  print_r($resp->getHeaders());
  print("Response Body:\n");
  print($resp->getBody());
}
```
### POST request
```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
use Utopia\Fetch\Client;

$url = 'https://httpbin.org/post';
$method = 'POST';
$headers = [
  'Content-Type' => 'application/json',
];
$body = [
  'name' => 'John Doe',
];
$query = [
  'foo' => 'bar'
];

$resp = Client::fetch(
  url: $url,
  method: $method,
  headers: $headers,
  body: $body,
  query: $query
);

print_r($resp->json());
```
### Send a file
```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
use Utopia\Fetch\Client;

$url = 'http://localhost:8000';
$method = 'POST';
$headers = [
  'Content-Type' => 'application/json',
];
$filePath = strval(realpath(__DIR__ . '/tests/resources/logo.png')); // Absolute path to the file

$body = [
    'file' => new \CURLFile($filePath, 'image/png', 'logo.png')
];

$resp = Client::fetch(
  url: $url,
  method: $method,
  headers: $headers,
  body: $body
);

print_r($resp->json());
```
