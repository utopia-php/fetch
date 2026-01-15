<?php

namespace Utopia\Fetch\Adapter;

use PHPUnit\Framework\TestCase;
use Utopia\Fetch\Chunk;
use Utopia\Fetch\Response;

final class SwooleTest extends TestCase
{
    private ?Swoole $adapter;

    protected function setUp(): void
    {
        if (!class_exists('Swoole\Coroutine\Http\Client')) {
            $this->markTestSkipped('Swoole extension is not installed');
        }
        $this->adapter = new Swoole();
    }

    /**
     * Test basic GET request
     */
    public function testGetRequest(): void
    {
        if ($this->adapter === null) {
            $this->markTestSkipped('Swoole extension is not installed');
        }

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
        $this->assertSame('GET', $data['method']);
    }

    /**
     * Test POST request with JSON body
     */
    public function testPostWithJsonBody(): void
    {
        if ($this->adapter === null) {
            $this->markTestSkipped('Swoole extension is not installed');
        }

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
        $this->assertSame('POST', $data['method']);
        $this->assertSame($body, $data['body']);
    }

    /**
     * Test request with custom timeout
     */
    public function testCustomTimeout(): void
    {
        if ($this->adapter === null) {
            $this->markTestSkipped('Swoole extension is not installed');
        }

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
        if ($this->adapter === null) {
            $this->markTestSkipped('Swoole extension is not installed');
        }

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
        $this->assertSame('redirectedPage', $data['page']);
    }

    /**
     * Test redirect disabled
     */
    public function testRedirectDisabled(): void
    {
        if ($this->adapter === null) {
            $this->markTestSkipped('Swoole extension is not installed');
        }

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
        if ($this->adapter === null) {
            $this->markTestSkipped('Swoole extension is not installed');
        }

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
        if ($this->adapter === null) {
            $this->markTestSkipped('Swoole extension is not installed');
        }

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
        if ($this->adapter === null) {
            $this->markTestSkipped('Swoole extension is not installed');
        }

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
     * Test class availability check
     */
    public function testSwooleAvailability(): void
    {
        $classExists = class_exists('Swoole\Coroutine\Http\Client');
        if ($classExists) {
            $this->assertNotNull($this->adapter);
        } else {
            $this->assertNull($this->adapter);
        }
    }
}
