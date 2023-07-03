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
    /**
     * Flatten request body array to PHP multiple format
     *
     * @param array<mixed> $data
     * @param string $prefix
     * @return array<mixed>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $output = [];
        foreach ($data as $key => $value) {
            $finalKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                $output += $this->flatten($value, $finalKey); // @todo: handle name collision here if needed
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
     * @param array<string>|array<string, mixed> $body
     * @param array<string, mixed> $query
     * @return Response
     */
    private function buildResponse(
        string $url,
        array $headers,
        string $method,
        array $body,
        array $query
    ): Response {
        // Process the data before making the request
        if(!$method) { // if method is not set, set it to GET by default
            $method = self::METHOD_GET;
        } else { // else convert the method to uppercase
            $method = strtoupper($method);
        }
        // if there are no headers but a body set header to json by default
        if(!isset($headers['content-type']) && is_array($body) && count($body) > 0) {
            $headers['content-type'] = 'application/json';
        }
        if(isset($headers['content-type'])) {
            match ($headers['content-type']) { // Convert the body to the appropriate format
                'application/json' => $body = json_encode($body),
                'application/x-www-form-urlencoded' => $body = $this->flatten($body),
                'multipart/form-data' => $body = $this->flatten($body),
                'application/graphql' => $body = $body[0],
            };
        }
        if(!is_array($headers)) {
            $headers = [];
        }
        foreach ($headers as $i => $header) { // convert headers to appropriate format
            $headers[] = $i . ':' . $header;
            unset($headers[$i]);
        }
        if($query) {  // if the request has a query string, append it to the request URI
            $url .= '?' . http_build_query($query);
        }
        $resp_headers = [];
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
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$resp_headers) {
            $len = strlen($header);
            $header = explode(':', $header, 2);

            if (count($header) < 2) { // ignore invalid headers
                return $len;
            }

            $resp_headers[strtolower(trim($header[0]))] = trim($header[1]);
            return $len;
        });
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp_body = curl_exec($ch); // Execute the curl session
        $resp_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $resp_type = $resp_headers['content-type'] ?? '';

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            print_r(curl_errno($ch));
        }
        curl_close($ch);

        if (isset($error_msg)) {
            // TODO - Handle cURL error accordingly
            throw new FetchException($error_msg);
        }
        $resp = new Response(
            method: $method,
            url: $url,
            statusCode: $resp_status,
            headers: $resp_headers,
            body: strval($resp_body),
            type: $resp_type
        );
        return $resp;
    }
    /**
     * This method is used to make a call to the private buildResponse method
     * @param string $url
     * @param array<string, string> $headers
     * @param string $method
     * @param array<string>|array<string, mixed> $body
     * @param array<string, mixed> $query
     * @return Response
     */
    public static function fetch(
        string $url,
        array $headers = [
            'content-type' => ''
        ],
        string $method = self::METHOD_GET,
        array $body = [],
        array $query = []
    ): Response {
        $client = new Client();
        return $client->buildResponse(
            $url,
            $headers,
            $method,
            $body,
            $query
        );
    }
}
