# Utopia Fetch
Lite & fast micro PHP library that provides a convenient and flexible way to perform HTTP requests in PHP applications.

## Usage
Create an instance of the `Client` class to perform HTTP requests. The instance methods allow for setting request options like headers, timeout, connection timeout, and more.

The `fetch()` method on a `Client` instance accepts the following parameters:
- `url` - A **String** containing the URL to which the request is sent.
- `method` - A **String** containing the HTTP method for the request. The default method is `GET`.
- `body` - An **array** of data to send as the body of the request, which can be an associative array for form data or a JSON string.
- `query` - An **associative array** of query parameters.

The `Response` object returned by `fetch()` provides several methods to inspect the response:
- `isOk()` - Checks if the response status code is within the 200-299 range.
- `getBody()` - Retrieves the response body as a string.
- `getHeaders()` - Fetches the response headers as an associative array.
- `getStatusCode()` - Gets the response status code.
- `json()` - Decodes the response body to an associative array.
- `text()` - Alias for `getBody()`, returns the response body as a string.
- `blob()` - Returns the response body as a blob.

## Examples

### GET Request
```php
require_once __DIR__ . '/vendor/autoload.php';
use Utopia\Fetch\Client;

$client = new Client();
$url = 'https://httpbin.org/get';
$method = 'GET';
$query = ['foo' => 'bar'];

// Optionally set more configurations
$client
  ->setUserAgent('CustomUserAgent/1.0')
  ->setAllowRedirects(true)
  ->setMaxRedirects(1)
  ->setConnectionTimeout(10)
  ->setTimeout(30);

$resp = $client->fetch(
    url: $url,
    method: $method,
    query: $query
);

if ($resp->isOk()) {
    echo "Status Code: " . $resp->getStatusCode() . "\n";
    echo "Response Headers:\n";
    print_r($resp->getHeaders());
    echo "Response Body:\n";
    echo $resp->getBody();
} else {
    echo "Error: " . $resp->getStatusCode() . "\n";
}
```


### POST Request

```php
require_once __DIR__ . '/vendor/autoload.php';
use Utopia\Fetch\Client;

$client = new Client();
$url = 'https://httpbin.org/post';
$method = 'POST';
$headers = ['Content-Type' => 'application/json'];
$body = ['name' => 'John Doe'];
$query = ['foo' => 'bar'];

// Set request headers
$client->addHeader('Content-Type', 'application/json');

$resp = $client->fetch(
    url: $url,
    method: $method,
    body: $body,
    query: $query
);

print_r($resp->json());
```

### Sending a file

```php
require_once __DIR__ . '/vendor/autoload.php';
use Utopia\Fetch\Client;

$client = new Client();
$url = 'http://localhost:8000/upload';
$method = 'POST';

// Ensure you set the appropriate Content-Type for file upload
$client->addHeader('Content-Type', 'multipart/form-data');

$filePath = realpath(__DIR__ . '/tests/resources/logo.png'); // Absolute path to the file
$body = ['file' => new \CURLFile($filePath, 'image/png', 'logo.png')];

$resp = $client->fetch(
    url: $url,
    method: $method,
    body: $body
);

print_r($resp->json());
```