<?php

declare(strict_types=1);

namespace Utopia\Fetch\Adapter;

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

        $ch = curl_init();
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

        foreach ($curlOptions as $option => $value) {
            curl_setopt($ch, $option, $value);
        }

        try {
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
        } finally {
            curl_close($ch);
        }
    }
}
