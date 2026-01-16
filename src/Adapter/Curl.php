<?php

declare(strict_types=1);

namespace Utopia\Fetch\Adapter;

use CurlHandle;
use Utopia\Fetch\Adapter;
use Utopia\Fetch\Chunk;
use Utopia\Fetch\Exception;
use Utopia\Fetch\Response;

/**
 * Curl Adapter
 * HTTP adapter using PHP's cURL extension
 * @package Utopia\Fetch\Adapter
 */
class Curl implements Adapter
{
    private ?CurlHandle $handle = null;

    /**
     * @var array<int, mixed>
     */
    private array $config = [];

    /**
     * Create a new Curl adapter
     *
     * @param bool $sslVerifyPeer Verify the peer's SSL certificate
     * @param bool $sslVerifyHost Verify the host's SSL certificate (2 = verify, 0 = don't verify)
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
        bool $sslVerifyPeer = true,
        bool $sslVerifyHost = true,
        ?string $sslCertificate = null,
        ?string $sslKey = null,
        ?string $caInfo = null,
        ?string $caPath = null,
        ?string $proxy = null,
        ?string $proxyUserPwd = null,
        int $proxyType = CURLPROXY_HTTP,
        int $httpVersion = CURL_HTTP_VERSION_NONE,
        bool $tcpKeepAlive = false,
        int $tcpKeepIdle = 60,
        int $tcpKeepInterval = 60,
        int $bufferSize = 16384,
        bool $verbose = false,
    ) {
        $this->config[CURLOPT_SSL_VERIFYPEER] = $sslVerifyPeer;
        $this->config[CURLOPT_SSL_VERIFYHOST] = $sslVerifyHost ? 2 : 0;

        if ($sslCertificate !== null) {
            $this->config[CURLOPT_SSLCERT] = $sslCertificate;
        }

        if ($sslKey !== null) {
            $this->config[CURLOPT_SSLKEY] = $sslKey;
        }

        if ($caInfo !== null) {
            $this->config[CURLOPT_CAINFO] = $caInfo;
        }

        if ($caPath !== null) {
            $this->config[CURLOPT_CAPATH] = $caPath;
        }

        if ($proxy !== null) {
            $this->config[CURLOPT_PROXY] = $proxy;
            $this->config[CURLOPT_PROXYTYPE] = $proxyType;

            if ($proxyUserPwd !== null) {
                $this->config[CURLOPT_PROXYUSERPWD] = $proxyUserPwd;
            }
        }

        $this->config[CURLOPT_HTTP_VERSION] = $httpVersion;
        $this->config[CURLOPT_TCP_KEEPALIVE] = $tcpKeepAlive ? 1 : 0;
        $this->config[CURLOPT_TCP_KEEPIDLE] = $tcpKeepIdle;
        $this->config[CURLOPT_TCP_KEEPINTVL] = $tcpKeepInterval;
        $this->config[CURLOPT_BUFFERSIZE] = $bufferSize;
        $this->config[CURLOPT_VERBOSE] = $verbose;
    }

    /**
     * Get or create the cURL handle
     *
     * @return CurlHandle
     * @throws Exception If cURL initialization fails
     */
    private function getHandle(): CurlHandle
    {
        if ($this->handle === null) {
            $handle = curl_init();
            if ($handle === false) {
                throw new Exception('Failed to initialize cURL handle');
            }
            $this->handle = $handle;
        } else {
            curl_reset($this->handle);
        }

        return $this->handle;
    }

    /**
     * Send an HTTP request using cURL
     *
     * @param string $url The URL to send the request to
     * @param string $method The HTTP method (GET, POST, etc.)
     * @param mixed $body The request body (string, array, or null)
     * @param array<string, string> $headers The request headers (formatted as key-value pairs)
     * @param array<string, mixed> $options Additional options (timeout, connectTimeout, maxRedirects, allowRedirects, userAgent)
     * @param callable|null $chunkCallback Optional callback for streaming chunks
     * @return Response The HTTP response
     * @throws Exception If the request fails
     */
    public function send(
        string $url,
        string $method,
        mixed $body,
        array $headers,
        array $options = [],
        ?callable $chunkCallback = null
    ): Response {
        $formattedHeaders = array_map(function ($key, $value) {
            return $key . ':' . $value;
        }, array_keys($headers), $headers);

        $responseHeaders = [];
        $responseBody = '';
        $chunkIndex = 0;

        $ch = $this->getHandle();
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) {
                    return $len;
                }
                $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);
                return $len;
            },
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($chunkCallback, &$responseBody, &$chunkIndex) {
                if ($chunkCallback !== null) {
                    $chunk = new Chunk(
                        data: $data,
                        size: strlen($data),
                        timestamp: microtime(true),
                        index: $chunkIndex++
                    );
                    $chunkCallback($chunk);
                } else {
                    $responseBody .= $data;
                }
                return strlen($data);
            },
            CURLOPT_CONNECTTIMEOUT_MS => $options['connectTimeout'] ?? 5000,
            CURLOPT_TIMEOUT_MS => $options['timeout'] ?? 15000,
            CURLOPT_MAXREDIRS => $options['maxRedirects'] ?? 5,
            CURLOPT_FOLLOWLOCATION => $options['allowRedirects'] ?? true,
            CURLOPT_USERAGENT => $options['userAgent'] ?? ''
        ];

        if ($body !== null && $body !== [] && $body !== '') {
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }

        // Merge adapter config (adapter config takes precedence)
        $curlOptions = $this->config + $curlOptions;

        foreach ($curlOptions as $option => $value) {
            curl_setopt($ch, $option, $value);
        }

        $success = curl_exec($ch);
        if ($success === false) {
            $errorMsg = curl_error($ch);
            throw new Exception($errorMsg);
        }

        $responseStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return new Response(
            statusCode: $responseStatusCode,
            headers: $responseHeaders,
            body: $responseBody
        );
    }

    /**
     * Close the cURL handle when the adapter is destroyed
     */
    public function __destruct()
    {
        if ($this->handle !== null) {
            curl_close($this->handle);
            $this->handle = null;
        }
    }
}
