<?php

declare(strict_types=1);

namespace Utopia\Fetch\Options;

/**
 * Swoole Options
 * Configuration options for the Swoole adapter
 * @package Utopia\Fetch\Options
 */
class Swoole
{
    /**
     * Create Swoole adapter options
     *
     * @param bool $coroutines If true, uses Swoole\Coroutine\Http\Client. If false, uses Swoole\Http\Client (sync/blocking).
     * @param bool $keepAlive Enable HTTP keep-alive for connection reuse
     * @param int $socketBufferSize Socket buffer size in bytes
     * @param bool $httpCompression Enable HTTP compression (gzip, br)
     * @param bool $sslVerifyPeer Verify the peer's SSL certificate
     * @param string|null $sslHostName Expected SSL hostname for verification
     * @param string|null $sslCafile Path to CA certificate file
     * @param bool $sslAllowSelfSigned Allow self-signed SSL certificates
     * @param int $packageMaxLength Maximum package length in bytes
     * @param bool $websocketMask Enable WebSocket masking (for WebSocket connections)
     * @param string|null $bindAddress Local address to bind to
     * @param int|null $bindPort Local port to bind to
     * @param bool $websocketCompression Enable WebSocket compression
     * @param int $lowaterMark Low water mark for write buffer
     */
    public function __construct(
        private bool $coroutines = true,
        private bool $keepAlive = true,
        private int $socketBufferSize = 1048576,
        private bool $httpCompression = true,
        private bool $sslVerifyPeer = true,
        private ?string $sslHostName = null,
        private ?string $sslCafile = null,
        private bool $sslAllowSelfSigned = false,
        private int $packageMaxLength = 2097152,
        private bool $websocketMask = true,
        private ?string $bindAddress = null,
        private ?int $bindPort = null,
        private bool $websocketCompression = false,
        private int $lowaterMark = 0,
    ) {
    }

    public function getCoroutines(): bool
    {
        return $this->coroutines;
    }

    public function getKeepAlive(): bool
    {
        return $this->keepAlive;
    }

    public function getSocketBufferSize(): int
    {
        return $this->socketBufferSize;
    }

    public function getHttpCompression(): bool
    {
        return $this->httpCompression;
    }

    public function getSslVerifyPeer(): bool
    {
        return $this->sslVerifyPeer;
    }

    public function getSslHostName(): ?string
    {
        return $this->sslHostName;
    }

    public function getSslCafile(): ?string
    {
        return $this->sslCafile;
    }

    public function getSslAllowSelfSigned(): bool
    {
        return $this->sslAllowSelfSigned;
    }

    public function getPackageMaxLength(): int
    {
        return $this->packageMaxLength;
    }

    public function getWebsocketMask(): bool
    {
        return $this->websocketMask;
    }

    public function getBindAddress(): ?string
    {
        return $this->bindAddress;
    }

    public function getBindPort(): ?int
    {
        return $this->bindPort;
    }

    public function getWebsocketCompression(): bool
    {
        return $this->websocketCompression;
    }

    public function getLowaterMark(): int
    {
        return $this->lowaterMark;
    }
}
