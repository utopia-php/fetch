<?php

declare(strict_types=1);

namespace Utopia\Fetch\Options;

/**
 * Curl Options
 * Configuration options for the Curl adapter
 * @package Utopia\Fetch\Options
 */
class Curl
{
    /**
     * Create Curl adapter options
     *
     * @param bool $sslVerifyPeer Verify the peer's SSL certificate
     * @param bool $sslVerifyHost Verify the host's SSL certificate
     * @param string|null $sslCertificate Path to SSL certificate file
     * @param string|null $sslKey Path to SSL private key file
     * @param string|null $caInfo Path to CA bundle file
     * @param string|null $caPath Path to directory containing CA certificates
     * @param string|null $proxy Proxy URL (e.g., "http://proxy:8080")
     * @param string|null $proxyUserPwd Proxy authentication (username:password)
     * @param int $proxyType Proxy type (CURLPROXY_HTTP, CURLPROXY_SOCKS5, etc.)
     * @param int $httpVersion HTTP version (CURL_HTTP_VERSION_1_1, CURL_HTTP_VERSION_2_0, etc.)
     * @param bool $tcpKeepAlive Enable TCP keep-alive
     * @param int $tcpKeepIdle TCP keep-alive idle time in seconds
     * @param int $tcpKeepInterval TCP keep-alive interval in seconds
     * @param int $bufferSize Buffer size for reading response
     * @param bool $verbose Enable verbose output for debugging
     */
    public function __construct(
        private bool $sslVerifyPeer = true,
        private bool $sslVerifyHost = true,
        private ?string $sslCertificate = null,
        private ?string $sslKey = null,
        private ?string $caInfo = null,
        private ?string $caPath = null,
        private ?string $proxy = null,
        private ?string $proxyUserPwd = null,
        private int $proxyType = CURLPROXY_HTTP,
        private int $httpVersion = CURL_HTTP_VERSION_NONE,
        private bool $tcpKeepAlive = false,
        private int $tcpKeepIdle = 60,
        private int $tcpKeepInterval = 60,
        private int $bufferSize = 16384,
        private bool $verbose = false,
    ) {
    }

    public function getSslVerifyPeer(): bool
    {
        return $this->sslVerifyPeer;
    }

    public function getSslVerifyHost(): bool
    {
        return $this->sslVerifyHost;
    }

    public function getSslCertificate(): ?string
    {
        return $this->sslCertificate;
    }

    public function getSslKey(): ?string
    {
        return $this->sslKey;
    }

    public function getCaInfo(): ?string
    {
        return $this->caInfo;
    }

    public function getCaPath(): ?string
    {
        return $this->caPath;
    }

    public function getProxy(): ?string
    {
        return $this->proxy;
    }

    public function getProxyUserPwd(): ?string
    {
        return $this->proxyUserPwd;
    }

    public function getProxyType(): int
    {
        return $this->proxyType;
    }

    public function getHttpVersion(): int
    {
        return $this->httpVersion;
    }

    public function getTcpKeepAlive(): bool
    {
        return $this->tcpKeepAlive;
    }

    public function getTcpKeepIdle(): int
    {
        return $this->tcpKeepIdle;
    }

    public function getTcpKeepInterval(): int
    {
        return $this->tcpKeepInterval;
    }

    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    public function getVerbose(): bool
    {
        return $this->verbose;
    }
}
