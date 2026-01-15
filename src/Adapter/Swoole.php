<?php

declare(strict_types=1);

namespace Utopia\Fetch\Adapter;

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
     * Check if Swoole is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return class_exists('Swoole\Coroutine\Http\Client');
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
                // Add scheme if missing for proper parsing
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

                $client = new \Swoole\Coroutine\Http\Client($host, $port, $ssl);

                $timeout = ($options['timeout'] ?? 15000) / 1000;
                $connectTimeout = ($options['connectTimeout'] ?? 60000) / 1000;
                $maxRedirects = $options['maxRedirects'] ?? 5;
                $allowRedirects = $options['allowRedirects'] ?? true;
                $userAgent = $options['userAgent'] ?? '';

                $client->set([
                    'timeout' => $timeout,
                    'connect_timeout' => $connectTimeout,
                    'keep_alive' => false,
                ]);

                $client->setMethod($method);

                $allHeaders = $headers;
                if ($userAgent !== '') {
                    $allHeaders['User-Agent'] = $userAgent;
                }

                if (!empty($allHeaders)) {
                    $client->setHeaders($allHeaders);
                }

                if ($body !== null) {
                    if (is_array($body)) {
                        // Check for file uploads in the body
                        $hasFiles = false;
                        $formData = [];

                        foreach ($body as $key => $value) {
                            if ($value instanceof \CURLFile || (is_string($value) && str_starts_with($value, '@'))) {
                                $hasFiles = true;
                                // Handle file uploads
                                if ($value instanceof \CURLFile) {
                                    $client->addFile($value->getFilename(), $key, $value->getMimeType() ?: 'application/octet-stream', $value->getPostFilename() ?: basename($value->getFilename()));
                                } elseif (str_starts_with($value, '@')) {
                                    $filePath = substr($value, 1);
                                    $client->addFile($filePath, $key);
                                }
                            } else {
                                $formData[$key] = $value;
                            }
                        }

                        // If there are files, set form data separately
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

                $responseBody = '';
                $chunkIndex = 0;

                $redirectCount = 0;
                do {
                    $success = $client->execute($path);

                    if (!$success) {
                        $errorCode = $client->errCode;
                        $errorMsg = socket_strerror($errorCode);
                        $client->close();
                        throw new Exception("Request failed: {$errorMsg} (Code: {$errorCode})");
                    }

                    // Swoole doesn't support real-time chunk streaming like cURL
                    // So we receive the full body and send it as chunks if callback is provided
                    $currentResponseBody = $client->body ?? '';

                    if ($chunkCallback !== null && !empty($currentResponseBody)) {
                        // Split body into chunks for callback
                        // For chunked transfer encoding, split by newlines or send as single chunk
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
                                // Absolute URL redirect - update host, port, SSL, and path
                                $parsedLocation = parse_url($location);
                                $newHost = $parsedLocation['host'] ?? $host;
                                $newPort = $parsedLocation['port'] ?? (isset($parsedLocation['scheme']) && $parsedLocation['scheme'] === 'https' ? 443 : 80);
                                $newSsl = ($parsedLocation['scheme'] ?? 'http') === 'https';
                                $path = ($parsedLocation['path'] ?? '/') . (isset($parsedLocation['query']) ? '?' . $parsedLocation['query'] : '');

                                // If host changed, close old client and create new one
                                if ($newHost !== $host || $newPort !== $port || $newSsl !== $ssl) {
                                    $client->close();
                                    $host = $newHost;
                                    $port = $newPort;
                                    $ssl = $newSsl;
                                    $client = new \Swoole\Coroutine\Http\Client($host, $port, $ssl);
                                    $client->set([
                                        'timeout' => $timeout,
                                        'connect_timeout' => $connectTimeout,
                                        'keep_alive' => false,
                                    ]);
                                    $client->setMethod($method);
                                    if (!empty($allHeaders)) {
                                        $client->setHeaders($allHeaders);
                                    }
                                    if ($body !== null) {
                                        if (is_array($body)) {
                                            // Check for file uploads in the body
                                            $hasFiles = false;
                                            $formData = [];

                                            foreach ($body as $key => $value) {
                                                if ($value instanceof \CURLFile || (is_string($value) && str_starts_with($value, '@'))) {
                                                    $hasFiles = true;
                                                    // Handle file uploads
                                                    if ($value instanceof \CURLFile) {
                                                        $client->addFile($value->getFilename(), $key, $value->getMimeType() ?: 'application/octet-stream', $value->getPostFilename() ?: basename($value->getFilename()));
                                                    } elseif (str_starts_with($value, '@')) {
                                                        $filePath = substr($value, 1);
                                                        $client->addFile($filePath, $key);
                                                    }
                                                } else {
                                                    $formData[$key] = $value;
                                                }
                                            }

                                            // If there are files, set form data separately
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
                                }
                            } else {
                                // Relative URL redirect - keep same host/port/SSL
                                $path = $location;
                            }
                            continue;
                        }
                    }

                    break;
                } while (true);

                $responseHeaders = array_change_key_case($client->headers ?? [], CASE_LOWER);
                $responseStatusCode = $client->getStatusCode();

                $client->close();

                $response = new Response(
                    statusCode: $responseStatusCode,
                    headers: $responseHeaders,
                    body: $responseBody
                );
            } catch (\Throwable $e) {
                $exception = $e;
            }
        };

        // Check if we're already in a coroutine context
        if (\Swoole\Coroutine::getCid() > 0) {
            // Already in a coroutine, execute directly
            $executeRequest();
        } else {
            // Not in a coroutine, create a new scheduler
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
}
