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
    # Getters
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
