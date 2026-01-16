<?php

declare(strict_types=1);

namespace Utopia\Fetch\Adapter;

use CurlHandle;
use Utopia\Fetch\Adapter;
use Utopia\Fetch\Chunk;
use Utopia\Fetch\Exception;
use Utopia\Fetch\Response;

/**
 * Curl Adapter
 * HTTP adapter using PHP's cURL extension
 * @package Utopia\Fetch\Adapter
 */
class Curl implements Adapter
{
    private ?CurlHandle $handle = null;

    /**
     * @var array<int, mixed>
     */
    private array $config;

    /**
     * Create a new Curl adapter
     *
     * @param array<int, mixed> $config Custom cURL options (CURLOPT_* constants as keys)
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get or create the cURL handle
     *
     * @return CurlHandle
     * @throws Exception If cURL initialization fails
     */
    private function getHandle(): CurlHandle
    {
        if ($this->handle === null) {
            $handle = curl_init();
            if ($handle === false) {
                throw new Exception('Failed to initialize cURL handle');
            }
            $this->handle = $handle;
        } else {
            curl_reset($this->handle);
        }

        return $this->handle;
    }

    /**
     * Send an HTTP request using cURL
     *
     * @param string $url The URL to send the request to
     * @param string $method The HTTP method (GET, POST, etc.)
     * @param mixed $body The request body (string, array, or null)
     * @param array<string, string> $headers The request headers (formatted as key-value pairs)
     * @param array<string, mixed> $options Additional options (timeout, connectTimeout, maxRedirects, allowRedirects, userAgent)
     * @param callable|null $chunkCallback Optional callback for streaming chunks
     * @return Response The HTTP response
     * @throws Exception If the request fails
     */
    public function send(
        string $url,
        string $method,
        mixed $body,
        array $headers,
        array $options = [],
        ?callable $chunkCallback = null
    ): Response {
        $formattedHeaders = array_map(function ($key, $value) {
            return $key . ':' . $value;
        }, array_keys($headers), $headers);

        $responseHeaders = [];
        $responseBody = '';
        $chunkIndex = 0;

        $ch = $this->getHandle();
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) {
                    return $len;
                }
                $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);
                return $len;
            },
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($chunkCallback, &$responseBody, &$chunkIndex) {
                if ($chunkCallback !== null) {
                    $chunk = new Chunk(
                        data: $data,
                        size: strlen($data),
                        timestamp: microtime(true),
                        index: $chunkIndex++
                    );
                    $chunkCallback($chunk);
                } else {
                    $responseBody .= $data;
                }
                return strlen($data);
            },
            CURLOPT_CONNECTTIMEOUT_MS => $options['connectTimeout'] ?? 5000,
            CURLOPT_TIMEOUT_MS => $options['timeout'] ?? 15000,
            CURLOPT_MAXREDIRS => $options['maxRedirects'] ?? 5,
            CURLOPT_FOLLOWLOCATION => $options['allowRedirects'] ?? true,
            CURLOPT_USERAGENT => $options['userAgent'] ?? ''
        ];

        if ($body !== null && $body !== [] && $body !== '') {
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }

        // Merge custom config (user config takes precedence)
        $curlOptions = $this->config + $curlOptions;

        foreach ($curlOptions as $option => $value) {
            curl_setopt($ch, $option, $value);
        }

        $success = curl_exec($ch);
        if ($success === false) {
            $errorMsg = curl_error($ch);
            throw new Exception($errorMsg);
        }

        $responseStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return new Response(
            statusCode: $responseStatusCode,
            headers: $responseHeaders,
            body: $responseBody
        );
    }

    /**
     * Close the cURL handle when the adapter is destroyed
     */
    public function __destruct()
    {
        if ($this->handle !== null) {
            curl_close($this->handle);
            $this->handle = null;
        }
    }
}
