<?php

namespace Utopia\Fetch;

use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    /**
     * End to end test for Client::fetch
     * Uses the PHP inbuilt server to test the Client::fetch method
     * @runInSeparateProcess
     * @dataProvider dataSet
     * @param string $url
     * @param string $method
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @return void
     */
    public function testFetch(
        $url,
        $method,
        $body = [],
        $headers = [],
        $query = []
    ): void {
        $resp = null;

        try {
            $client = new Client();
            foreach ($headers as $key => $value) {
                $client->addHeader($key, $value);
            }

            $resp = $client->fetch(
                url: $url,
                method: $method,
                body: $body,
                query: $query
            );
        } catch (Exception $e) {
            echo $e;
            return;
        }
        if ($resp->getStatusCode() === 200) { // If the response is OK
            $respData = $resp->json(); // Convert body to array
            $this->assertEquals($respData['method'], $method); // Assert that the method is equal to the response's method
            if ($method != Client::METHOD_GET) {
                if (empty($body)) { // if body is empty then response body should be an empty string
                    $this->assertEquals($respData['body'], '');
                } else {
                    if ($headers['content-type'] != "application/x-www-form-urlencoded") {
                        $this->assertEquals( // Assert that the body is equal to the response's body
                            $respData['body'],
                            json_encode($body) // Converting the body to JSON string
                        );
                    }
                }
            }
            $this->assertEquals($respData['url'], $url); // Assert that the url is equal to the response's url
            $this->assertEquals(
                json_encode($respData['query']), // Converting the query to JSON string
                json_encode($query) // Converting the query to JSON string
            ); // Assert that the args are equal to the response's args
            $respHeaders = json_decode($respData['headers'], true); // Converting the headers to array
            $host = $respHeaders['Host'];
            if (array_key_exists('Content-Type', $respHeaders)) {
                $contentType = $respHeaders['Content-Type'];
            } else {
                $contentType = $respHeaders['content-type'];
            }
            $contentType = explode(';', $contentType)[0];
            $this->assertEquals($host, $url); // Assert that the host is equal to the response's host
            if (empty($headers)) {
                if (empty($body)) {
                    $this->assertEquals($contentType, 'application/x-www-form-urlencoded');
                } else {
                    $this->assertEquals($contentType, 'application/json');
                }
            } else {
                $this->assertEquals($contentType, $headers['content-type']); // Assert that the content-type is equal to the response's content-type
            }
        } else { // If the response is not OK
            echo "Please configure your PHP inbuilt SERVER";
        }
    }

    /**
     * Test for sending a file in the request body
     * @dataProvider sendFileDataSet
     * @return void
     */
    public function testSendFile(
        string $path,
        string $contentType,
        string $fileName
    ): void {
        $resp = null;
        try {
            $client = new Client();
            $client->addHeader('Content-type', 'multipart/form-data');
            $resp = $client->fetch(
                url: 'localhost:8000',
                method: Client::METHOD_POST,
                body: [
                    'file' => new \CURLFile(strval(realpath($path)), $contentType, $fileName)
                ],
                query: []
            );
        } catch (Exception $e) {
            echo $e;
            return;
        }
        if ($resp->getStatusCode() === 200) { // If the response is OK
            $respData = $resp->json(); // Convert body to array
            if (isset($respData['method'])) {
                $this->assertEquals($respData['method'], Client::METHOD_POST);
            } // Assert that the method is equal to the response's method
            $this->assertEquals($respData['url'], 'localhost:8000'); // Assert that the url is equal to the response's url
            $this->assertEquals(
                json_encode($respData['query']), // Converting the query to JSON string
                json_encode([]) // Converting the query to JSON string
            ); // Assert that the args are equal to the response's args
            $files = [ // Expected files array from response
                'file' => [
                    'name' => $fileName,
                    'full_path' => $fileName,
                    'type' => $contentType,
                    'error' => 0
                ]
            ];
            $resp_files = json_decode($respData['files'], true);
            $this->assertEquals($files['file']['name'], $resp_files['file']['name']);
            $this->assertEquals($files['file']['full_path'], $resp_files['file']['full_path']);
            $this->assertEquals($files['file']['type'], $resp_files['file']['type']);
            $this->assertEquals($files['file']['error'], $resp_files['file']['error']);
        } else { // If the response is not OK
            echo "Please configure your PHP inbuilt SERVER";
        }
    }

    /**
     * Test FormData fetch
     * @dataProvider formDataSet
     * @runInSeparateProcess
     * @param FormData $formData
     * @param array<string, string> $expectedFields
     * @param array<string, array<string, string>> $expectedFiles = []
     */
    public function testFormData(
        FormData $formData,
        array $expectedFields,
        array $expectedFiles = []
    ): void {
        $resp = null;
        try {
            $client = new Client();
            $resp = $client->fetch(
                url: 'localhost:8000/form-data',
                method: Client::METHOD_POST,
                body: $formData,
                query: []
            );
        } catch (Exception $e) {
            echo $e;
            return;
        }

        if ($resp->getStatusCode() === 200) {
            $respData = $resp->json();

            // Check method and URL
            $this->assertEquals(Client::METHOD_POST, $respData['method']);
            $this->assertEquals('localhost:8000', $respData['url']);

            // Check content type header
            $respHeaders = json_decode($respData['headers'], true);
            $contentType = $respHeaders['Content-Type'] ?? $respHeaders['content-type'] ?? '';

            if (empty($expectedFiles)) {
                $this->assertEquals('application/x-www-form-urlencoded', $formData->getContentType());
            } else {
                $this->assertStringStartsWith('multipart/form-data; boundary=', $formData->getContentType());
                $this->assertStringStartsWith('multipart/form-data; boundary=', $contentType);
            }

            // Check for expected fields in the response
            foreach ($expectedFields as $field => $value) {
                // Check if field exists in POST data
                $this->assertArrayHasKey($field, $respData['post']);
                $this->assertEquals($value, $respData['post'][$field]);
                
                // Also verify presence in body format string for compatibility
                $this->assertStringContainsString('name="' . $field . '"', $respData['body']);
                $this->assertStringContainsString($value, $respData['body']);
            }

            // Check for expected files in response
            if (!empty($expectedFiles)) {
                $filesJson = json_decode($respData['files'], true);
                $this->assertNotNull($filesJson, "Unable to decode files JSON");
                
                foreach ($expectedFiles as $fileField => $fileInfo) {
                    $this->assertArrayHasKey($fileField, $filesJson);
                    if (isset($fileInfo['filename'])) {
                        $this->assertEquals($fileInfo['filename'], $filesJson[$fileField]['name']);
                    }
                    
                    // Content verification can be done through the formatted body
                    if (isset($fileInfo['content'])) {
                        $this->assertStringContainsString($fileInfo['content'], $respData['body']);
                    }
                }
            }
        } else {
            $this->markTestSkipped("Test server is not running at localhost:8000");
        }
    }

    /**
     * Test for getting a file as a response
     * @dataProvider getFileDataSet
     * @return void
     */
    public function testGetFile(
        string $path,
        string $type
    ): void {
        $resp = null;
        try {
            $client = new Client();
            $resp = $client->fetch(
                url: 'localhost:8000/' . $type,
                method: Client::METHOD_GET,
                body: [],
                query: []
            );
        } catch (Exception $e) {
            echo $e;
            return;
        }
        if ($resp->getStatusCode() === 200) { // If the response is OK
            $data = fopen($path, 'rb');
            $size = filesize($path);
            if ($data && $size) {
                $contents = fread($data, $size);
                fclose($data);
                $this->assertEquals($resp->getBody(), $contents); // Assert that the body is equal to the expected file contents
            } else {
                echo "Invalid file path in testcase";
            }
        } else { // If the response is not OK
            echo "Please configure your PHP inbuilt SERVER";
        }
    }

    /**
     * Test for redirect
     * @return void
     */
    public function testRedirect(): void
    {
        $resp = null;
        try {
            $client = new Client();
            $resp = $client->fetch(
                url: 'localhost:8000/redirect',
                method: Client::METHOD_GET,
                body: [],
                query: []
            );
        } catch (Exception $e) {
            echo $e;
            return;
        }
        if ($resp->getStatusCode() === 200) { // If the response is OK
            $respData = $resp->json(); // Convert body to array
            $this->assertEquals($respData['page'], "redirectedPage"); // Assert that the page is the redirected page
        } else { // If the response is not OK
            echo "Please configure your PHP inbuilt SERVER";
        }
    }

    /**
     * Test setting and getting the timeout.
     * @return void
     */
    public function testSetGetTimeout(): void
    {
        $client = new Client();
        $timeout = 10;

        $client->setTimeout($timeout);

        $this->assertEquals($timeout, $client->getTimeout());
    }

    /**
     * Test setting and getting the allowRedirects flag.
     * @return void
     */
    public function testSetGetAllowRedirects(): void
    {
        $client = new Client();
        $allowRedirects = true;

        $client->setAllowRedirects($allowRedirects);

        $this->assertEquals($allowRedirects, $client->getAllowRedirects());
    }

    /**
     * Test setting and getting the maxRedirects.
     * @return void
     */
    public function testSetGetMaxRedirects(): void
    {
        $client = new Client();
        $maxRedirects = 5;

        $client->setMaxRedirects($maxRedirects);

        $this->assertEquals($maxRedirects, $client->getMaxRedirects());
    }

    /**
     * Test setting and getting the connectTimeout.
     * @return void
     */
    public function testSetGetConnectTimeout(): void
    {
        $client = new Client();
        $connectTimeout = 5;

        $client->setConnectTimeout($connectTimeout);

        $this->assertEquals($connectTimeout, $client->getConnectTimeout());
    }

    /**
     * Test setting and getting the userAgent.
     * @return void
     */
    public function testSetGetUserAgent(): void
    {
        $client = new Client();
        $userAgent = "MyCustomUserAgent/1.0";

        $client->setUserAgent($userAgent);

        $this->assertEquals($userAgent, $client->getUserAgent());
    }

    /**
     * Data provider for testFetch
     * @return array<string, array<mixed>>
     */
    public function dataSet(): array
    {
        return [
            'get' => [
                'localhost:8000',
                Client::METHOD_GET
            ],
            'getWithQuery' => [
                'localhost:8000',
                Client::METHOD_GET,
                [],
                [],
                [
                    'name' => 'John Doe',
                    'age' => '30',
                ],
            ],
            'postNoBody' => [
                'localhost:8000',
                Client::METHOD_POST
            ],
            'postJsonBody' => [
                'localhost:8000',
                Client::METHOD_POST,
                [
                    'name' => 'John Doe',
                    'age' => 30,
                ],
                [
                    'content-type' => 'application/json'
                ],
            ],
            'postFormDataBody' => [
                'localhost:8000',
                Client::METHOD_POST,
                [
                    'name' => 'John Doe',
                    'age' => 30,
                ],
                [
                    'content-type' => 'application/x-www-form-urlencoded'
                ],
            ]
        ];
    }

    /**
     * Data provider for testSendFile
     * @return array<string, array<mixed>>
     */
    public function sendFileDataSet(): array
    {
        return [
            'imageFile' => [
                __DIR__ . '/resources/logo.png',
                'image/png',
                'logo.png'
            ],
            'textFile' => [
                __DIR__ . '/resources/test.txt',
                'text/plain',
                'text.txt'
            ],
        ];
    }

    /**
     * Data provider for testClientWithFormData
     * @return array<string, array<mixed>>
     */
    public function formDataSet(): array
    {
        $textFilePath = __DIR__ . '/resources/test.txt';
        $imageFilePath = __DIR__ . '/resources/logo.png';

        // Create test files if they don't exist for testing
        if (!file_exists($textFilePath)) {
            $dir = dirname($textFilePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($textFilePath, 'Text file content');
        }

        if (!file_exists($imageFilePath)) {
            $dir = dirname($imageFilePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            // Create a simple 1x1 transparent PNG
            $im = imagecreatetruecolor(1, 1);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            if (!$trans_colour) {
                throw new \Exception('Failed to allocate transparent color');
            }
            imagefill($im, 0, 0, $trans_colour);
            imagepng($im, $imageFilePath);
            imagedestroy($im);
        }

        // Basic form data with fields only
        $basicFormData = new FormData();
        $basicFormData->addField('name', 'John Doe');
        $basicFormData->addField('email', 'john@example.com');

        // Form data with custom headers
        $customHeaderFormData = new FormData();
        $customHeaderFormData->addField('name', 'Jane Doe', ['X-Custom-Header' => 'Custom Value']);

        // Form data with a file
        $fileFormData = new FormData();
        $fileFormData->addField('description', 'File upload test');
        $fileFormData->addFile('file', $textFilePath);

        // Form data with direct content
        $contentFormData = new FormData();
        $contentFormData->addField('description', 'Content upload test');
        $contentFormData->addContent('file', 'Custom file content', 'custom.txt', 'text/plain');

        // Complex form data with multiple fields and files
        $complexFormData = new FormData();
        $complexFormData->addField('name', 'John Doe');
        $complexFormData->addField('email', 'john@example.com');
        $complexFormData->addField('custom', 'Custom value', ['X-Custom-Field' => 'Test']);
        $complexFormData->addFile('textFile', $textFilePath);
        $complexFormData->addContent('jsonContent', '{"test":"value"}', 'data.json', 'application/json');

        return [
            'basicFormData' => [
                $basicFormData,
                [
                    'name' => 'John Doe',
                    'email' => 'john@example.com'
                ]
            ],
            'customHeaderFormData' => [
                $customHeaderFormData,
                [
                    'name' => 'Jane Doe'
                ]
            ],
            'fileFormData' => [
                $fileFormData,
                [
                    'description' => 'File upload test'
                ],
                [
                    'file' => [
                        'filename' => 'test.txt'
                    ]
                ]
            ],
            'contentFormData' => [
                $contentFormData,
                [
                    'description' => 'Content upload test'
                ],
                [
                    'file' => [
                        'filename' => 'custom.txt',
                        'content' => 'Custom file content'
                    ]
                ]
            ],
            'complexFormData' => [
                $complexFormData,
                [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'custom' => 'Custom value'
                ],
                [
                    'textFile' => [
                        'filename' => 'test.txt'
                    ],
                    'jsonContent' => [
                        'filename' => 'data.json',
                        'content' => '{"test":"value"}'
                    ]
                ]
            ]
        ];
    }

    /**
     * Data provider for testGetFile
     * @return array<string, array<mixed>>
     */
    public function getFileDataset(): array
    {
        return [
            'imageFile' => [
                __DIR__ . '/resources/logo.png',
                'image'
            ],
            'textFile' => [
                __DIR__ . '/resources/test.txt',
                'text'
            ],
        ];
    }

    /**
     * Test for retry functionality
     * @return void
     */
    public function testRetry(): void
    {
        $client = new Client();
        $client->setMaxRetries(3);
        $client->setRetryDelay(1000);

        $this->assertEquals(3, $client->getMaxRetries());
        $this->assertEquals(1000, $client->getRetryDelay());

        $res = $client->fetch('localhost:8000/mock-retry');
        $this->assertEquals(200, $res->getStatusCode());

        unlink(__DIR__ . '/state.json');

        // Test if we get a 500 error if we go under the server's max retries
        $client->setMaxRetries(1);
        $res = $client->fetch('localhost:8000/mock-retry');
        $this->assertEquals(503, $res->getStatusCode());

        unlink(__DIR__ . '/state.json');
    }

    /**
     * Test if the retry delay is working
     * @return void
     */
    public function testRetryWithDelay(): void
    {
        $client = new Client();
        $client->setMaxRetries(3);
        $client->setRetryDelay(3000);
        $now = microtime(true);

        $res = $client->fetch('localhost:8000/mock-retry');
        $this->assertGreaterThan($now + 3.0, microtime(true));
        $this->assertEquals(200, $res->getStatusCode());
        unlink(__DIR__ . '/state.json');
    }

    /**
     * Test custom retry status codes
     * @return void
     */
    public function testCustomRetryStatusCodes(): void
    {
        $client = new Client();
        $client->setMaxRetries(3);
        $client->setRetryDelay(3000);
        $client->setRetryStatusCodes([401]);
        $now = microtime(true);

        $res = $client->fetch('localhost:8000/mock-retry-401');
        $this->assertEquals(200, $res->getStatusCode());
        $this->assertGreaterThan($now + 3.0, microtime(true));
        unlink(__DIR__ . '/state.json');
    }

    /**
     * Test for chunk handling
     * @return void
     */
    public function testChunkHandling(): void
    {
        $client = new Client();
        $chunks = [];
        $lastChunk = null;

        $response = $client->fetch(
            url: 'localhost:8000/chunked',
            method: Client::METHOD_GET,
            chunks: function (Chunk $chunk) use (&$chunks, &$lastChunk) {
                $chunks[] = $chunk;
                $lastChunk = $chunk;
            }
        );

        $this->assertGreaterThan(0, count($chunks));
        $this->assertEquals(200, $response->getStatusCode());

        // Test chunk metadata
        foreach ($chunks as $index => $chunk) {
            $this->assertEquals($index, $chunk->getIndex());
            $this->assertGreaterThan(0, $chunk->getSize());
            $this->assertGreaterThan(0, $chunk->getTimestamp());
            $this->assertNotEmpty($chunk->getData());
        }

        // Verify last chunk exists
        $this->assertNotNull($lastChunk);
    }

    /**
     * Test chunk handling with JSON response
     * @return void
     */
    public function testChunkHandlingWithJson(): void
    {
        $client = new Client();
        $client->addHeader('content-type', 'application/json');

        $chunks = [];
        $response = $client->fetch(
            url: 'localhost:8000/chunked-json',
            method: Client::METHOD_POST,
            body: ['test' => 'data'],
            chunks: function (Chunk $chunk) use (&$chunks) {
                $chunks[] = $chunk;
            }
        );

        $this->assertGreaterThan(0, count($chunks));

        // Test JSON handling
        foreach ($chunks as $chunk) {
            $data = $chunk->getData();
            $this->assertNotEmpty($data);

            // Verify each chunk is valid JSON
            $decoded = json_decode($data, true);
            $this->assertNotNull($decoded);
            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('chunk', $decoded);
            $this->assertArrayHasKey('data', $decoded);
        }
    }

    /**
     * Test chunk handling with error response
     * @return void
     */
    public function testChunkHandlingWithError(): void
    {
        $client = new Client();
        $errorChunk = null;

        $response = $client->fetch(
            url: 'localhost:8000/error',
            method: Client::METHOD_GET,
            chunks: function (Chunk $chunk) use (&$errorChunk) {
                if ($errorChunk === null) {
                    $errorChunk = $chunk;
                }
            }
        );

        $this->assertNotNull($errorChunk);
        if ($errorChunk !== null) {
            $this->assertNotEmpty($errorChunk->getData());
        }
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * Test chunk handling with chunked error response
     * @return void
     */
    public function testChunkHandlingWithChunkedError(): void
    {
        $client = new Client();
        $client->addHeader('content-type', 'application/json');
        $chunks = [];
        $errorMessages = [];

        $response = $client->fetch(
            url: 'localhost:8000/chunked-error',
            method: Client::METHOD_GET,
            chunks: function (Chunk $chunk) use (&$chunks, &$errorMessages) {
                $chunks[] = $chunk;
                $data = json_decode($chunk->getData(), true);
                if ($data && isset($data['error'])) {
                    $errorMessages[] = $data['error'];
                }
            }
        );

        // Verify response status code
        $this->assertEquals(400, $response->getStatusCode());

        // Verify we received chunks
        $this->assertCount(3, $chunks);

        // Verify error messages were received in order
        $this->assertEquals([
            'Validation error',
            'Additional details',
            'Final error message'
        ], $errorMessages);

        // Test the content of specific chunks
        $this->assertArrayHasKey(0, $chunks);
        $firstChunk = json_decode($chunks[0]->getData(), true);
        $this->assertIsArray($firstChunk);
        $this->assertEquals('username', $firstChunk['field']);
    }
}
