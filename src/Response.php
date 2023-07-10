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
     * @var string
     */
    private string $body;
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
     * Response Method
     *
     * @var string
     */
    private string $method;
    /**
     * Response URL
     *
     * @var string
     */
    private string $url;
    /**
     * Response OK
     *
     * @var bool
     */
    private bool $ok;

    /**
     * Response constructor
     * @param string $method
     * @param string $url
     * @param int $statusCode
     * @param string $body
     * @param array<string, string> $headers
     * @return void
     */
    public function __construct(
        string $method,
        string $url,
        int $statusCode=200,
        string $body='',
        array $headers=[],
    ) {
        $this->body = $body;
        $this->headers = $headers;
        $this->statusCode = $statusCode;
        $this->method = $method;
        $this->url = $url;
        $this->ok = $statusCode >= 200 && $statusCode < 300;
    }
    # Getters
    /**
     * This method is used to check if the response is OK
     * @return bool
     */
    public function isOk(): bool
    {
        return $this->ok;
    }
    /**
     * This method is used to get the response body as string
     * @return string
     */
    public function getBody(): string
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
     * This method is used to get the response method
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }
    /**
     * This method is used to get the response URL
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }
    // Methods

    /**
      * This method is used to convert the response body to text
      * @return string
    */
    public function text(): string
    {
        return \strval($this->body);
    }
    /**
      * This method is used to convert the response body to JSON
      * @return mixed
    */
    public function json(): mixed
    {
        $data = \json_decode($this->body, true);
        if($data === null) { // Throw an exception if the data is null
            throw new \Exception('Error decoding JSON');
        }
        return $data;
    }

    /*
    * This method is used to convert the response body to blob
    * @return string
    */
    public function blob(): string
    {
        $bin = "";
        for($i = 0, $j = strlen($this->body); $i < $j; $i++) {
            $bin .= decbin(ord($this->body)) . " ";
        }
        return $bin;
    }
}
