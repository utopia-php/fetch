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
     * @param array<string>|array<string, mixed>|FormData|null $body
     * @param array<string, mixed> $query
     * @param ?callable $chunks Optional callback function that receives a Chunk object
     * @return Response
     * @throws FetchException
     */
    public function fetch(
        string $url,
        string $method = self::METHOD_GET,
        mixed $body = [],
        ?array $query = [],
        ?callable $chunks = null,
    ): Response {
        if (!in_array($method, [self::METHOD_PATCH, self::METHOD_GET, self::METHOD_CONNECT, self::METHOD_DELETE, self::METHOD_POST, self::METHOD_HEAD, self::METHOD_OPTIONS, self::METHOD_PUT, self::METHOD_TRACE])) {
            throw new Exception("Unsupported HTTP method");
        }

        if ($body !== null) {
            if ($body instanceof FormData) {
                $this->headers['content-type'] = $body->getContentType();
                $body = $body->build();
            } elseif (isset($this->headers['content-type'])) {
                $body = match ($this->headers['content-type']) {
                    self::CONTENT_TYPE_APPLICATION_JSON => json_encode($body),
                    self::CONTENT_TYPE_GRAPHQL => $body[0],
                    default => $body,
                };
            }
        }

        $formattedHeaders = array_map(function ($key, $value) {
            return $key . ':' . $value;
        }, array_keys($this->headers), $this->headers);

        if ($query) {
            $url = rtrim($url, '?') . '?' . http_build_query($query);
        }

        $responseHeaders = [];
        $responseBody = '';
        $chunkIndex = 0;
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
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($chunks, &$responseBody, &$chunkIndex) {
                if ($chunks !== null) {
                    $chunk = new Chunk(
                        data: $data,
                        size: strlen($data),
                        timestamp: microtime(true),
                        index: $chunkIndex++
                    );
                    $chunks($chunk);
                } else {
                    $responseBody .= $data;
                }
                return strlen($data);
            },
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_MAXREDIRS => $this->maxRedirects,
            CURLOPT_FOLLOWLOCATION => $this->allowRedirects,
            CURLOPT_USERAGENT => $this->userAgent
        ];

        // Merge user-defined CURL options with defaults
        foreach ($curlOptions as $option => $value) {
            curl_setopt($ch, $option, $value);
        }

        $sendRequest = function () use ($ch, &$responseHeaders, &$responseBody) {
            $responseHeaders = [];

            $success = curl_exec($ch);
            if ($success === false) {
                $errorMsg = curl_error($ch);
                curl_close($ch);
                throw new Exception($errorMsg);
            }

            $responseStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

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
