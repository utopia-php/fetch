<?php

declare(strict_types=1);

namespace Utopia\Fetch;

use Utopia\Fetch\Options\Request as RequestOptions;

/**
 * Adapter interface
 * Defines the contract for HTTP adapters
 * @package Utopia\Fetch
 */
interface Adapter
{
    /**
     * Send an HTTP request
     *
     * @param string $url The URL to send the request to
     * @param string $method The HTTP method (GET, POST, etc.)
     * @param mixed $body The request body (string, array, or null)
     * @param array<string, string> $headers The request headers (formatted as key-value pairs)
     * @param RequestOptions $options Request options (timeout, connectTimeout, maxRedirects, allowRedirects, userAgent)
     * @param callable|null $chunkCallback Optional callback for streaming chunks
     * @return Response The HTTP response
     * @throws Exception If the request fails
     */
    public function send(
        string $url,
        string $method,
        mixed $body,
        array $headers,
        RequestOptions $options,
        ?callable $chunkCallback = null
    ): Response;
}
