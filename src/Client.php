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

    /** @var array<string, string> headers */
    private array $headers = [];
    private int $timeout = 15;
    private int $connectTimeout = 60;
    private int $maxRedirects = 5;
    private bool $allowRedirects = true;
    private string $userAgent = '';
    private int $maxRetries = 0;
    private int $retryDelay = 1000; // milliseconds

    /** @var array<int> $retryStatusCodes */
    private array $retryStatusCodes = [500, 503];

    /**
     * @param string $key
     * @param string $value
     * @return self
     */
    public function addHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Set the request timeout.
     *
     * @param int $timeout
     * @return self
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Set whether to allow redirects.
     *
     * @param bool $allow
     * @return self
     */
    public function setAllowRedirects(bool $allow): self
    {
        $this->allowRedirects = $allow;
        return $this;
    }

    /**
     * Set the maximum number of redirects.
     *
     * @param int $maxRedirects
     * @return self
     */
    public function setMaxRedirects(int $maxRedirects): self
    {
        $this->maxRedirects = $maxRedirects;
        return $this;
    }

    /**
     * Set the connection timeout.
     *
     * @param int $connectTimeout
     * @return self
     */
    public function setConnectTimeout(int $connectTimeout): self
    {
        $this->connectTimeout = $connectTimeout;
        return $this;
    }

    /**
     * Set the user agent.
     *
     * @param string $userAgent
     * @return self
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Set the maximum number of retries.
     *
     * The client will automatically retry the request if the response status code is 500 or 503, indicating a temporary error.
     * If the request fails after the maximum number of retries, the normal response will be returned.
     *
     * @param int $maxRetries
     * @return self
     */
    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    /**
     * Set the retry delay in milliseconds.
     *
     * @param int $retryDelay
     * @return self
     */
    public function setRetryDelay(int $retryDelay): self
    {
        $this->retryDelay = $retryDelay;
        return $this;
    }

    /**
     * Set the retry status codes.
     *
     * @param array<int> $retryStatusCodes
     * @return self
     */
    public function setRetryStatusCodes(array $retryStatusCodes): self
    {
        $this->retryStatusCodes = $retryStatusCodes;
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
     * Retry a callback with exponential backoff
     *
     * @param callable $callback
     * @return mixed
     * @throws \Exception
     */
    private function withRetries(callable $callback): mixed
    {
        $attempts = 1;

        while (true) {
            $res = $callback();

            if (!in_array($res->getStatusCode(), $this->retryStatusCodes) || $attempts >= $this->maxRetries) {
                return $res;
            }

            usleep($this->retryDelay * 1000); // Convert milliseconds to microseconds
            $attempts++;
        }
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
        ?array $body = [],
        ?array $query = [],
    ): Response {
        if (!in_array($method, [self::METHOD_PATCH, self::METHOD_GET, self::METHOD_CONNECT, self::METHOD_DELETE, self::METHOD_POST, self::METHOD_HEAD, self::METHOD_OPTIONS, self::METHOD_PUT, self::METHOD_TRACE])) {
            throw new FetchException("Unsupported HTTP method");
        }

        if (isset($this->headers['content-type']) && $body !== null) {
            $body = match ($this->headers['content-type']) {
                self::CONTENT_TYPE_APPLICATION_JSON => json_encode($body),
                self::CONTENT_TYPE_APPLICATION_FORM_URLENCODED, self::CONTENT_TYPE_MULTIPART_FORM_DATA => self::flatten($body),
                self::CONTENT_TYPE_GRAPHQL => $body[0],
                default => $body,
            };
        }

        $formattedHeaders = array_map(function ($key, $value) {
            return $key . ':' . $value;
        }, array_keys($this->headers), $this->headers);

        if ($query) {
            $url = rtrim($url, '?') . '?' . http_build_query($query);
        }

        $responseHeaders = [];
        $ch = curl_init();
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) {  // ignore invalid headers
                    return $len;
                }
                $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);
                return $len;
            },
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_MAXREDIRS => $this->maxRedirects,
            CURLOPT_FOLLOWLOCATION => $this->allowRedirects,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => $this->userAgent
        ];

        // Merge user-defined CURL options with defaults
        foreach ($curlOptions as $option => $value) {
            curl_setopt($ch, $option, $value);
        }

        $sendRequest = function () use ($ch, &$responseHeaders) {
            $responseHeaders = [];

            $responseBody = curl_exec($ch);
            $responseStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (curl_errno($ch)) {
                $errorMsg = curl_error($ch);
            }

            curl_close($ch);

            if (isset($errorMsg)) {
                throw new FetchException($errorMsg);
            }

            return new Response(
                statusCode: $responseStatusCode,
                headers: $responseHeaders,
                body: $responseBody
            );
        };

        if ($this->maxRetries > 0) {
            $response = $this->withRetries($sendRequest);
        } else {
            $response = $sendRequest();
        }

        return $response;
    }

    /**
     * Get the request timeout.
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Get whether redirects are allowed.
     *
     * @return bool
     */
    public function getAllowRedirects(): bool
    {
        return $this->allowRedirects;
    }

    /**
     * Get the maximum number of redirects.
     *
     * @return int
     */
    public function getMaxRedirects(): int
    {
        return $this->maxRedirects;
    }

    /**
     * Get the connection timeout.
     *
     * @return int
     */
    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    /**
     * Get the user agent.
     *
     * @return string
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * Get the maximum number of retries.
     *
     * @return int
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Get the retry delay.
     *
     * @return int
     */
    public function getRetryDelay(): int
    {
        return $this->retryDelay;
    }

    /**
     * Get the retry status codes.
     *
     * @return array<int>
     */
    public function getRetryStatusCodes(): array
    {
        return $this->retryStatusCodes;
    }
}
