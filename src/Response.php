<?php

declare(strict_types=1);

namespace Utopia\Fetch;

/**
 * Response class
 * This class is used to represent the response from the server
 * @package Utopia\Fetch
 */
class Response
{
    /**
     * Response Body
     *
     * @var mixed
     */
    private $body;
    /**
     * Response Headers
     *
     * @var array<string, string>
     */
    private array $headers;
    /**
     * Response Status Code
     *
     * @var int
     */
    private int $statusCode;

    /**
     * Response constructor
     * @param int $statusCode
     * @param mixed $body
     * @param array<string, string> $headers
     * @return void
     */
    public function __construct(
        int $statusCode,
        $body,
        array $headers,
    ) {
        $this->body = $body;
        $this->headers = $headers;
        $this->statusCode = $statusCode;
    }

    /**
     * This method is used to get the response body as string
     * @return mixed
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * This method is used to get the response headers
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * This method is used to get the response status code
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * This method is used to convert the response body to text
     * @return string
     */
    public function text(): string
    {
        if ($this->body === null) {
            return '';
        }
        if (is_string($this->body)) {
            return $this->body;
        }
        if (is_scalar($this->body)) {
            return \strval($this->body);
        }
        if (is_object($this->body) && method_exists($this->body, '__toString')) {
            return (string) $this->body;
        }
        return '';
    }

    /**
    * This method is used to convert the response body to JSON
    * @return mixed
    * @throws Exception If JSON decoding fails
    */
    public function json(): mixed
    {
        $bodyString = is_string($this->body) ? $this->body : '';
        $data = \json_decode($bodyString, true);

        // Check for JSON errors using json_last_error()
        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error decoding JSON: ' . \json_last_error_msg());
        }

        return $data;
    }

    /**
     * This method is used to convert the response body to blob
     * @return string
     */
    public function blob(): string
    {
        $bodyString = is_string($this->body) ? $this->body : '';
        $bin = [];
        for ($i = 0, $j = strlen($bodyString); $i < $j; $i++) {
            $bin[] = decbin(ord($bodyString[$i]));
        }
        return implode(" ", $bin);
    }
}
