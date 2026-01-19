<?php

declare(strict_types=1);

namespace Utopia\Fetch\Options;

/**
 * Request Options
 * Configuration options for HTTP requests
 * @package Utopia\Fetch\Options
 */
class Request
{
    /**
     * Create request options
     *
     * @param int $timeout Request timeout in milliseconds
     * @param int $connectTimeout Connection timeout in milliseconds
     * @param int $maxRedirects Maximum number of redirects to follow
     * @param bool $allowRedirects Whether to follow redirects
     * @param string $userAgent User agent string
     */
    public function __construct(
        private int $timeout = 15000,
        private int $connectTimeout = 5000,
        private int $maxRedirects = 5,
        private bool $allowRedirects = true,
        private string $userAgent = '',
    ) {
    }

    /**
     * Get request timeout in milliseconds
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Get connection timeout in milliseconds
     */
    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    /**
     * Get maximum number of redirects to follow
     */
    public function getMaxRedirects(): int
    {
        return $this->maxRedirects;
    }

    /**
     * Get whether to follow redirects
     */
    public function getAllowRedirects(): bool
    {
        return $this->allowRedirects;
    }

    /**
     * Get user agent string
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }
}
