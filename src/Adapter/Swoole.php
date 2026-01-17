<?php

declare(strict_types=1);

namespace Utopia\Fetch\Adapter;

use CURLFile;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client as CoClient;
use Throwable;
use Utopia\Fetch\Adapter;
use Utopia\Fetch\Chunk;
use Utopia\Fetch\Exception;
use Utopia\Fetch\Response;

/**
 * Swoole Adapter
 * HTTP adapter using Swoole's HTTP client
 * @package Utopia\Fetch\Adapter
 */
class Swoole implements Adapter
{
    /**
     * @var array<string, CoClient>
     */
    private array $clients = [];

    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * @var bool
     */
    private bool $coroutines;

    /**
     * Create a new Swoole adapter
     *
     * @param bool $coroutines If true, uses Swoole\Coroutine\Http\Client. If false, uses Swoole\Http\Client (sync/blocking).
     * @param bool $keepAlive Enable HTTP keep-alive for connection reuse
     * @param int $socketBufferSize Socket buffer size in bytes
     * @param bool $httpCompression Enable HTTP compression (gzip, br)
     * @param bool $sslVerifyPeer Verify the peer's SSL certificate
     * @param string|null $sslHostName Expected SSL hostname for verification
     * @param string|null $sslCafile Path to CA certificate file
     * @param bool $sslAllowSelfSigned Allow self-signed SSL certificates
     * @param int $packageMaxLength Maximum package length in bytes
     * @param bool $websocketMask Enable WebSocket masking (for WebSocket connections)
     * @param string|null $bindAddress Local address to bind to
     * @param int|null $bindPort Local port to bind to
     * @param bool $websocketCompression Enable WebSocket compression
     * @param int $lowaterMark Low water mark for write buffer
     */
    public function __construct(
        bool $coroutines = true,
        bool $keepAlive = true,
        int $socketBufferSize = 1048576,
        bool $httpCompression = true,
        bool $sslVerifyPeer = true,
        ?string $sslHostName = null,
        ?string $sslCafile = null,
        bool $sslAllowSelfSigned = false,
        int $packageMaxLength = 2097152,
        bool $websocketMask = true,
        ?string $bindAddress = null,
        ?int $bindPort = null,
        bool $websocketCompression = false,
        int $lowaterMark = 0,
    ) {
        if ($coroutines && !class_exists(CoClient::class)) {
            $this->coroutines = false;
        } else {
            $this->coroutines = $coroutines;
        }

        $this->config['keep_alive'] = $keepAlive;
        $this->config['socket_buffer_size'] = $socketBufferSize;
        $this->config['http_compression'] = $httpCompression;
        $this->config['ssl_verify_peer'] = $sslVerifyPeer;
        $this->config['ssl_allow_self_signed'] = $sslAllowSelfSigned;
        $this->config['package_max_length'] = $packageMaxLength;
        $this->config['websocket_mask'] = $websocketMask;
        $this->config['websocket_compression'] = $websocketCompression;
        $this->config['lowwater_mark'] = $lowaterMark;

        if ($sslHostName !== null) {
            $this->config['ssl_host_name'] = $sslHostName;
        }

        if ($sslCafile !== null) {
            $this->config['ssl_cafile'] = $sslCafile;
        }

        if ($bindAddress !== null) {
            $this->config['bind_address'] = $bindAddress;
        }

        if ($bindPort !== null) {
            $this->config['bind_port'] = $bindPort;
        }
    }

    /**
     * Check if Swoole coroutine client is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return class_exists(CoClient::class) || class_exists('Swoole\\Http\\Client');
    }

    /**
     * Get the sync client class name
     *
     * @return string
     */
    private static function getSyncClientClass(): string
    {
        return 'Swoole' . '\\' . 'Http' . '\\' . 'Client';
    }

    /**
     * Get or create a Swoole HTTP client for the given host/port/ssl configuration
     *
     * @param string $host
     * @param int $port
     * @param bool $ssl
     * @return CoClient
     */
    private function getClient(string $host, int $port, bool $ssl): CoClient
    {
        $key = "{$host}:{$port}:" . ($ssl ? '1' : '0');

        if (!isset($this->clients[$key])) {
            if ($this->coroutines) {
                $this->clients[$key] = new CoClient($host, $port, $ssl);
            } else {
                $syncClientClass = self::getSyncClientClass();
                if (!class_exists($syncClientClass)) {
                    throw new Exception('Swoole sync HTTP client is not available');
                }
                /** @var CoClient $client */
                $client = new $syncClientClass($host, $port, $ssl);
                $this->clients[$key] = $client;
            }
        }

        return $this->clients[$key];
    }

    /**
     * Close and remove a client from the cache
     *
     * @param string $host
     * @param int $port
     * @param bool $ssl
     * @return void
     */
    private function closeClient(string $host, int $port, bool $ssl): void
    {
        $key = "{$host}:{$port}:" . ($ssl ? '1' : '0');

        if (isset($this->clients[$key])) {
            $this->clients[$key]->close();
            unset($this->clients[$key]);
        }
    }

    /**
     * Configure body data on the client
     *
     * @param CoClient $client Swoole HTTP client
     * @param mixed $body
     * @param array<string, string> $headers
     * @return void
     */
    private function configureBody(CoClient $client, mixed $body, array $headers): void
    {
        if ($body === null) {
            return;
        }

        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);

        if (is_array($body)) {
            $hasFiles = false;
            $formData = [];

            foreach ($body as $key => $value) {
                if ($value instanceof CURLFile || (is_string($value) && str_starts_with($value, '@'))) {
                    $hasFiles = true;
                    if ($value instanceof CURLFile) {
                        $client->addFile(
                            $value->getFilename(),
                            $key,
                            $value->getMimeType() ?: 'application/octet-stream',
                            $value->getPostFilename() ?: basename($value->getFilename())
                        );
                    } elseif (str_starts_with($value, '@')) {
                        $filePath = substr($value, 1);
                        $client->addFile($filePath, $key);
                    }
                } else {
                    $formData[$key] = $value;
                }
            }

            if ($hasFiles) {
                foreach ($formData as $key => $value) {
                    $client->addData($value, $key);
                }
            } elseif (isset($normalizedHeaders['content-type']) && $normalizedHeaders['content-type'] === 'application/x-www-form-urlencoded') {
                $client->setData(http_build_query($body));
            } else {
                $client->setData($body);
            }
        } elseif (is_string($body)) {
            $client->setData($body);
        }
    }

    /**
     * Build client settings by merging defaults with custom config
     *
     * @param float $timeout
     * @param float $connectTimeout
     * @return array<string, mixed>
     */
    private function buildClientSettings(float $timeout, float $connectTimeout): array
    {
        return array_merge($this->config, [
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
        ]);
    }

    /**
     * Execute the HTTP request
     *
     * @param string $url
     * @param string $method
     * @param mixed $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $options
     * @param callable|null $chunkCallback
     * @return Response
     * @throws Exception
     */
    private function executeRequest(
        string $url,
        string $method,
        mixed $body,
        array $headers,
        array $options,
        ?callable $chunkCallback
    ): Response {
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'http://' . $url;
        }

        $parsedUrl = parse_url($url);
        if ($parsedUrl === false) {
            throw new Exception('Invalid URL');
        }

        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? (isset($parsedUrl['scheme']) && $parsedUrl['scheme'] === 'https' ? 443 : 80);
        $path = $parsedUrl['path'] ?? '/';
        $query = $parsedUrl['query'] ?? '';
        $ssl = ($parsedUrl['scheme'] ?? 'http') === 'https';

        if ($ssl && $port === 80) {
            $port = 443;
        }

        if ($query !== '') {
            $path .= '?' . $query;
        }

        $client = $this->getClient($host, $port, $ssl);

        $timeout = ($options['timeout'] ?? 15000) / 1000;
        $connectTimeout = ($options['connectTimeout'] ?? 60000) / 1000;
        $maxRedirects = $options['maxRedirects'] ?? 5;
        $allowRedirects = $options['allowRedirects'] ?? true;
        $userAgent = $options['userAgent'] ?? '';

        $client->set($this->buildClientSettings($timeout, $connectTimeout));

        $client->setMethod($method);

        $allHeaders = $headers;
        if ($userAgent !== '') {
            $allHeaders['User-Agent'] = $userAgent;
        }

        if (!empty($allHeaders)) {
            $client->setHeaders($allHeaders);
        }

        $this->configureBody($client, $body, $headers);

        $responseBody = '';
        $chunkIndex = 0;

        $redirectCount = 0;
        do {
            $success = $client->execute($path);

            if (!$success) {
                $errorCode = $client->errCode;
                $errorMsg = socket_strerror($errorCode);
                $this->closeClient($host, $port, $ssl);
                throw new Exception("Request failed: {$errorMsg} (Code: {$errorCode})");
            }

            $currentResponseBody = $client->body ?? '';

            if ($chunkCallback !== null && !empty($currentResponseBody)) {
                $chunk = new Chunk(
                    data: $currentResponseBody,
                    size: strlen($currentResponseBody),
                    timestamp: microtime(true),
                    index: $chunkIndex++
                );
                $chunkCallback($chunk);
            } else {
                $responseBody = $currentResponseBody;
            }

            $statusCode = $client->getStatusCode();

            if ($allowRedirects && in_array($statusCode, [301, 302, 303, 307, 308]) && $redirectCount < $maxRedirects) {
                $location = $client->headers['location'] ?? $client->headers['Location'] ?? null;
                if ($location !== null) {
                    $redirectCount++;
                    if (strpos($location, 'http') === 0) {
                        $parsedLocation = parse_url($location);
                        $newHost = $parsedLocation['host'] ?? $host;
                        $newPort = $parsedLocation['port'] ?? (isset($parsedLocation['scheme']) && $parsedLocation['scheme'] === 'https' ? 443 : 80);
                        $newSsl = ($parsedLocation['scheme'] ?? 'http') === 'https';
                        $path = ($parsedLocation['path'] ?? '/') . (isset($parsedLocation['query']) ? '?' . $parsedLocation['query'] : '');

                        if ($newHost !== $host || $newPort !== $port || $newSsl !== $ssl) {
                            $host = $newHost;
                            $port = $newPort;
                            $ssl = $newSsl;
                            $client = $this->getClient($host, $port, $ssl);
                            $client->set($this->buildClientSettings($timeout, $connectTimeout));
                            $client->setMethod($method);

                            // Filter sensitive headers on cross-origin redirects
                            $sensitiveHeaders = ['authorization', 'cookie', 'proxy-authorization', 'host'];
                            $redirectHeaders = array_filter(
                                $allHeaders,
                                fn ($key) => !in_array(strtolower($key), $sensitiveHeaders),
                                ARRAY_FILTER_USE_KEY
                            );

                            if (!empty($redirectHeaders)) {
                                $client->setHeaders($redirectHeaders);
                            }
                            $this->configureBody($client, $body, $headers);
                        }
                    } else {
                        $path = $location;
                    }
                    continue;
                }
            }

            break;
        } while (true);

        $responseHeaders = array_change_key_case($client->headers ?? [], CASE_LOWER);
        $statusCode = $client->getStatusCode();
        $responseStatusCode = is_int($statusCode) ? $statusCode : 0;

        return new Response(
            statusCode: $responseStatusCode,
            headers: $responseHeaders,
            body: $responseBody
        );
    }

    /**
     * Send an HTTP request using Swoole
     *
     * @param string $url The URL to send the request to
     * @param string $method The HTTP method (GET, POST, etc.)
     * @param mixed $body The request body (string, array, or null)
     * @param array<string, string> $headers The request headers (formatted as key-value pairs)
     * @param array<string, mixed> $options Additional options (timeout, connectTimeout, maxRedirects, allowRedirects, userAgent)
     * @param callable|null $chunkCallback Optional callback for streaming chunks
     * @return Response The HTTP response
     * @throws Exception If the request fails or Swoole is not available
     */
    public function send(
        string $url,
        string $method,
        mixed $body,
        array $headers,
        array $options = [],
        ?callable $chunkCallback = null
    ): Response {
        if (!self::isAvailable()) {
            throw new Exception('Swoole extension is not installed');
        }

        // If using sync client or already in a coroutine, execute directly
        if (!$this->coroutines || Coroutine::getCid() > 0) {
            return $this->executeRequest($url, $method, $body, $headers, $options, $chunkCallback);
        }

        // Wrap in coroutine scheduler for coroutine client
        $response = null;
        $exception = null;

        $coRun = 'Swoole\\Coroutine\\run';
        $coRun(function () use ($url, $method, $body, $headers, $options, $chunkCallback, &$response, &$exception) {
            try {
                $response = $this->executeRequest($url, $method, $body, $headers, $options, $chunkCallback);
            } catch (Throwable $e) {
                $exception = $e;
            }
        });

        if ($exception !== null) {
            throw new Exception($exception->getMessage());
        }

        if ($response === null) {
            throw new Exception('Failed to get response');
        }

        return $response;
    }

    /**
     * Close all cached clients when the adapter is destroyed
     */
    public function __destruct()
    {
        foreach ($this->clients as $client) {
            $client->close();
        }
        $this->clients = [];
    }
}
