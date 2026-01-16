<?php

namespace Utopia\Fetch\Adapter;

use PHPUnit\Framework\TestCase;
use Utopia\Fetch\Chunk;
use Utopia\Fetch\Exception;
use Utopia\Fetch\Response;

final class CurlTest extends TestCase
{
    private Curl $adapter;

    protected function setUp(): void
    {
        $this->adapter = new Curl();
    }

    /**
     * Test basic GET request
     */
    public function testGetRequest(): void
    {
        $response = $this->adapter->send(
            url: '127.0.0.1:8000',
            method: 'GET',
            body: null,
            headers: [],
            options: [
                'timeout' => 15000,
                'connectTimeout' => 60000,
                'maxRedirects' => 5,
                'allowRedirects' => true,
                'userAgent' => ''
            ]
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertSame('GET', $data['method']);
    }

    /**
     * Test POST request with JSON body
     */
    public function testPostWithJsonBody(): void
    {
        $body = json_encode(['name' => 'John Doe', 'age' => 30]);
        $response = $this->adapter->send(
            url: '127.0.0.1:8000',
            method: 'POST',
            body: $body,
            headers: ['content-type' => 'application/json'],
            options: [
                'timeout' => 15000,
                'connectTimeout' => 60000,
                'maxRedirects' => 5,
                'allowRedirects' => true,
                'userAgent' => ''
            ]
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertSame('POST', $data['method']);
        $this->assertSame($body, $data['body']);
    }

    /**
     * Test request with custom timeout
     */
    public function testCustomTimeout(): void
    {
        $response = $this->adapter->send(
            url: '127.0.0.1:8000',
            method: 'GET',
            body: null,
            headers: [],
            options: [
                'timeout' => 5000,
                'connectTimeout' => 10000,
                'maxRedirects' => 5,
                'allowRedirects' => true,
                'userAgent' => 'TestAgent/1.0'
            ]
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Test redirect handling
     */
    public function testRedirectHandling(): void
    {
        $response = $this->adapter->send(
            url: '127.0.0.1:8000/redirect',
            method: 'GET',
            body: null,
            headers: [],
            options: [
                'timeout' => 15000,
                'connectTimeout' => 60000,
                'maxRedirects' => 5,
                'allowRedirects' => true,
                'userAgent' => ''
            ]
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertSame('redirectedPage', $data['page']);
    }

    /**
     * Test redirect disabled
     */
    public function testRedirectDisabled(): void
    {
        $response = $this->adapter->send(
            url: '127.0.0.1:8000/redirect',
            method: 'GET',
            body: null,
            headers: [],
            options: [
                'timeout' => 15000,
                'connectTimeout' => 60000,
                'maxRedirects' => 0,
                'allowRedirects' => false,
                'userAgent' => ''
            ]
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(302, $response->getStatusCode());
    }

    /**
     * Test chunk callback
     */
    public function testChunkCallback(): void
    {
        $chunks = [];
        $response = $this->adapter->send(
            url: '127.0.0.1:8000/chunked',
            method: 'GET',
            body: null,
            headers: [],
            options: [
                'timeout' => 15000,
                'connectTimeout' => 60000,
                'maxRedirects' => 5,
                'allowRedirects' => true,
                'userAgent' => ''
            ],
            chunkCallback: function (Chunk $chunk) use (&$chunks) {
                $chunks[] = $chunk;
            }
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertGreaterThan(0, count($chunks));

        foreach ($chunks as $index => $chunk) {
            $this->assertInstanceOf(Chunk::class, $chunk);
            $this->assertSame($index, $chunk->getIndex());
            $this->assertGreaterThan(0, $chunk->getSize());
            $this->assertNotEmpty($chunk->getData());
        }
    }

    /**
     * Test form data body
     */
    public function testFormDataBody(): void
    {
        $body = ['name' => 'John Doe', 'age' => '30'];
        $response = $this->adapter->send(
            url: '127.0.0.1:8000',
            method: 'POST',
            body: $body,
            headers: ['content-type' => 'application/x-www-form-urlencoded'],
            options: [
                'timeout' => 15000,
                'connectTimeout' => 60000,
                'maxRedirects' => 5,
                'allowRedirects' => true,
                'userAgent' => ''
            ]
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Test response headers
     */
    public function testResponseHeaders(): void
    {
        $response = $this->adapter->send(
            url: '127.0.0.1:8000',
            method: 'GET',
            body: null,
            headers: [],
            options: [
                'timeout' => 15000,
                'connectTimeout' => 60000,
                'maxRedirects' => 5,
                'allowRedirects' => true,
                'userAgent' => ''
            ]
        );

        $headers = $response->getHeaders();
        $this->assertIsArray($headers);
        $this->assertArrayHasKey('content-type', $headers);
    }

    /**
     * Test invalid URL throws exception
     */
    public function testInvalidUrlThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->adapter->send(
            url: 'invalid://url',
            method: 'GET',
            body: null,
            headers: [],
            options: [
                'timeout' => 15000,
                'connectTimeout' => 60000,
                'maxRedirects' => 5,
                'allowRedirects' => true,
                'userAgent' => ''
            ]
        );
    }

    /**
     * Test file upload with CURLFile
     */
    public function testFileUpload(): void
    {
        $filePath = __DIR__ . '/../resources/logo.png';
        $body = [
            'file' => new \CURLFile(strval(realpath($filePath)), 'image/png', 'logo.png')
        ];

        $response = $this->adapter->send(
            url: '127.0.0.1:8000',
            method: 'POST',
            body: $body,
            headers: ['content-type' => 'multipart/form-data'],
            options: [
                'timeout' => 15000,
                'connectTimeout' => 60000,
                'maxRedirects' => 5,
                'allowRedirects' => true,
                'userAgent' => ''
            ]
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertIsString($data['files']);
        $files = json_decode($data['files'], true);
        $this->assertIsArray($files);
        $this->assertSame('logo.png', $files['file']['name']);
    }
}
