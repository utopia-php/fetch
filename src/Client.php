<?php

namespace Utopia\Fetch;

/**
 * Client class
 * @package Utopia\Fetch
 */
class Client
{
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_HEAD = 'HEAD';
    public const METHOD_OPTIONS = 'OPTIONS';
    public const METHOD_CONNECT = 'CONNECT';
    public const METHOD_TRACE = 'TRACE';

    public const CONTENT_TYPE_APPLICATION_JSON = 'application/json';
    public const CONTENT_TYPE_APPLICATION_FORM_URLENCODED = 'application/x-www-form-urlencoded';
    public const CONTENT_TYPE_MULTIPART_FORM_DATA = 'multipart/form-data';
    public const CONTENT_TYPE_GRAPHQL = 'application/graphql';

    private array $headers = [];
    private array $curlOptions = [];


    /**
     * @param array<string, string> $headers
     * @return self
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * @param array $curlOptions
     * @return self
     */
    public function setCurlOptions(array $curlOptions): self
    {
        $this->curlOptions = $curlOptions;
        return $this;
    }

    /**
     * Flatten request body array to PHP multiple format
     *
     * @param array<mixed> $data
     * @param string $prefix
     * @return array<mixed>
     */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $output = [];
        foreach ($data as $key => $value) {
            $finalKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                $output += self::flatten($value, $finalKey); // @todo: handle name collision here if needed
            } else {
                $output[$finalKey] = $value;
            }
        }

        return $output;
    }

    /**
     * This method is used to make a request to the server.
     * 
     * @param string $url
     * @param string $method
     * @param array<string>|array<string, mixed> $body
     * @param array<string, mixed> $query
     * @return Response
     */
    public function fetch(
        string $url,
        string $method = self::METHOD_GET,
        array $body = [],
        array $query = [],
    ): Response {
        // Check for supported HTTP method
        if (!in_array($method, [self::METHOD_PATCH, self::METHOD_GET, self::METHOD_CONNECT, self::METHOD_DELETE, self::METHOD_POST, self::METHOD_HEAD, self::METHOD_OPTIONS, self::METHOD_PUT, self::METHOD_TRACE])) {
            throw new FetchException("Unsupported HTTP method");
        }

        // Process headers and body based on content-type
        if (isset($this->headers['content-type'])) {
            $body = match ($this->headers['content-type']) {
                self::CONTENT_TYPE_APPLICATION_JSON => json_encode($body),
                self::CONTENT_TYPE_APPLICATION_FORM_URLENCODED, self::CONTENT_TYPE_MULTIPART_FORM_DATA => self::flatten($body),
                self::CONTENT_TYPE_GRAPHQL => $body[0],
                default => $body,
            };
        }

        // Convert headers to CURL format
        $formattedHeaders = array_map(function ($key, $value) {
            return $key . ':' . $value;
        }, array_keys($this->headers), $this->headers);

        // Prepare URL with query parameters if any
        if ($query) {
            $url = rtrim($url, '?') . '?' . http_build_query($query);
        }

        // Initialize CURL and set options
        $ch = curl_init();
        $defaultCurlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
                // Header processing remains unchanged...
            },
            CURLOPT_CONNECTTIMEOUT => 60,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
        ];

        // Merge user-defined CURL options with defaults
        $mergedCurlOptions = $this->curlOptions + $defaultCurlOptions;
        foreach ($mergedCurlOptions as $option => $value) {
            curl_setopt($ch, $option, $value);
        }

        $responseBody = curl_exec($ch);
        $responseStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
        }
        
        curl_close($ch);
        
        if (isset($errorMsg)) {
            throw new FetchException($errorMsg);
        }

        $response = new Response(
            statusCode: $responseStatusCode,
            headers: $responseHeaders,
            body: $responseBody
        );
        
        return $response;
    }
}
