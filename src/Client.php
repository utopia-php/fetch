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
     * This method is used to make a request to the server
     * @param string $url
     * @param array<string, string> $headers
     * @param string $method
     * @param string $body
     * @param array<string, mixed> $query
     * @param int $timeout
     * @return Response
     */
    public static function fetch(
        string $url,
        array $headers = [],
        string $method = self::METHOD_GET,
        string $body = '',
        array $query = [],
        int $timeout = 15
    ): Response {
        // Process the data before making the request
        if (!in_array($method, [self::METHOD_PATCH, self::METHOD_GET, self::METHOD_CONNECT, self::METHOD_DELETE, self::METHOD_POST, self::METHOD_HEAD, self::METHOD_OPTIONS, self::METHOD_PUT, self::METHOD_TRACE ])) { // If the method is not supported
            throw new FetchException("Unsupported HTTP method");
        }
        if(isset($headers['content-type'])) {
            match ($headers['content-type']) { // Convert the body to the appropriate format
                self::CONTENT_TYPE_APPLICATION_JSON => $body = json_encode($body),
                self::CONTENT_TYPE_APPLICATION_FORM_URLENCODED, self::CONTENT_TYPE_MULTIPART_FORM_DATA => $body = self::flatten($body),
                self::CONTENT_TYPE_GRAPHQL => $body = $body[0],
                default => $body = $body,
            };
        }
        $headers = array_map(function ($i, $header) { // convert headers to appropriate format
            return $i . ':' . $header;
        }, array_keys($headers), $headers);
        if($query) {  // if the request has a query string, append it to the request URI
            $url = rtrim($url, '?');
            $url .= '?' . http_build_query($query);
        }
        $responseHeaders = [];
        // Initialize the curl session
        $ch = curl_init();
        // Set the request URI
        curl_setopt($ch, CURLOPT_URL, $url);
        // Set the request headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // Set the request method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        // Set the request body
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        // Save the response headers
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $header = explode(':', $header, 2);

            if (count($header) < 2) { // ignore invalid headers
                return $len;
            }

            $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);
            return $len;
        });
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseBody = curl_exec($ch); // Execute the curl session
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
