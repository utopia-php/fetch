<?php

declare(strict_types=1);

namespace Utopia\Fetch\Adapter;

use CURLFile;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client as SwooleClient;
use Throwable;
use Utopia\Fetch\Adapter;
use Utopia\Fetch\Chunk;
use Utopia\Fetch\Exception;
use Utopia\Fetch\Response;

/**
 * Swoole Adapter
 * HTTP adapter using Swoole's coroutine HTTP client
 * @package Utopia\Fetch\Adapter
 */
class Swoole implements Adapter
{
    /**
     * @var array<string, SwooleClient>
     */
    private array $clients = [];

    /**
     * Check if Swoole is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return class_exists(SwooleClient::class);
    }

    /**
     * Get or create a Swoole HTTP client for the given host/port/ssl configuration
     *
     * @param string $host
     * @param int $port
     * @param bool $ssl
     * @return SwooleClient
     */
    private function getClient(string $host, int $port, bool $ssl): SwooleClient
    {
        $key = "{$host}:{$port}:" . ($ssl ? '1' : '0');

        if (!isset($this->clients[$key])) {
            $this->clients[$key] = new SwooleClient($host, $port, $ssl);
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
     * @param SwooleClient $client
     * @param mixed $body
     * @param array<string, string> $headers
     * @return void
     */
    private function configureBody(SwooleClient $client, mixed $body, array $headers): void
    {
        if ($body === null) {
            return;
        }

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
            } elseif (isset($headers['content-type']) && $headers['content-type'] === 'application/x-www-form-urlencoded') {
                $client->setData(http_build_query($body));
            } else {
                $client->setData($body);
            }
        } else {
            $client->setData($body);
        }
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

        $response = null;
        $exception = null;

        $executeRequest = function () use ($url, $method, $body, $headers, $options, $chunkCallback, &$response, &$exception) {
            try {
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

                $client->set([
                    'timeout' => $timeout,
                    'connect_timeout' => $connectTimeout,
                    'keep_alive' => true,
                ]);

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
                                    $client->set([
                                        'timeout' => $timeout,
                                        'connect_timeout' => $connectTimeout,
                                        'keep_alive' => true,
                                    ]);
                                    $client->setMethod($method);
                                    if (!empty($allHeaders)) {
                                        $client->setHeaders($allHeaders);
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
                $responseStatusCode = $client->getStatusCode();

                $response = new Response(
                    statusCode: $responseStatusCode,
                    headers: $responseHeaders,
                    body: $responseBody
                );
            } catch (Throwable $e) {
                $exception = $e;
            }
        };

        if (Coroutine::getCid() > 0) {
            $executeRequest();
        } else {
            \Swoole\Coroutine\run($executeRequest);
        }

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
