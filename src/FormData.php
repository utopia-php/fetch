<?php

namespace Utopia\Fetch;

class FormData
{
    /**
     * @var string
     */
    private string $boundary;

    /**
     * @var array<array<string, mixed>>
     */
    private array $fields = [];

    /**
     * @var array<array<string, mixed>>
     */
    private array $files = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Generate a unique boundary
        $this->boundary = '----WebKitFormBoundary' . bin2hex(random_bytes(16));
    }

    /**
     * Add a text field to the multipart request
     *
     * @param string $name
     * @param string $value
     * @param array<string, string> $headers Optional additional headers
     * @return self
     */
    public function addField(string $name, string $value, array $headers = []): self
    {
        $this->fields[] = [
            'name' => $name,
            'value' => $value,
            'headers' => $headers
        ];

        return $this;
    }

    /**
     * Add a file to the multipart request
     *
     * @param string $name Field name
     * @param string $filePath Path to the file
     * @param string|null $fileName Custom filename (optional)
     * @param string|null $mimeType Custom mime type (optional)
     * @param array<string, string> $headers Optional additional headers
     * @return self
     * @throws \Exception If file doesn't exist or isn't readable
     */
    public function addFile(
        string $name,
        string $filePath,
        ?string $fileName = null,
        ?string $mimeType = null,
        array $headers = []
    ): self {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception("File doesn't exist or isn't readable: {$filePath}");
        }

        $this->files[] = [
            'name' => $name,
            'path' => $filePath,
            'filename' => $fileName ?? basename($filePath),
            'mime_type' => $mimeType ?? mime_content_type($filePath) ?: 'application/octet-stream',
            'headers' => $headers
        ];

        return $this;
    }

    /**
     * Add file content directly to the multipart request
     *
     * @param string $name Field name
     * @param string $content File content
     * @param string $fileName Filename to use
     * @param string|null $mimeType Custom mime type (optional)
     * @param array<string, string> $headers Optional additional headers
     * @return self
     */
    public function addContent(
        string $name,
        string $content,
        string $fileName,
        ?string $mimeType = null,
        array $headers = []
    ): self {
        $this->files[] = [
            'name' => $name,
            'content' => $content,
            'filename' => $fileName,
            'mime_type' => $mimeType ?? 'application/octet-stream',
            'headers' => $headers
        ];

        return $this;
    }

    /**
     * Build multipart body
     *
     * @return string
     */
    public function build(): string
    {
        $body = '';

        // Add fields
        foreach ($this->fields as $field) {
            $body .= "--{$this->boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$field['name']}\"\r\n";

            // Add custom headers
            foreach ($field['headers'] as $key => $value) {
                $body .= "{$key}: {$value}\r\n";
            }

            $body .= "\r\n";
            $body .= $field['value'] . "\r\n";
        }

        // Add files
        foreach ($this->files as $file) {
            $body .= "--{$this->boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$file['name']}\"; filename=\"{$file['filename']}\"\r\n";
            $body .= "Content-Type: {$file['mime_type']}\r\n";

            // Add custom headers
            foreach ($file['headers'] as $key => $value) {
                $body .= "{$key}: {$value}\r\n";
            }

            $body .= "\r\n";

            // Add file content
            if (isset($file['content'])) {
                $body .= $file['content'];
            } else {
                $body .= file_get_contents($file['path']);
            }

            $body .= "\r\n";
        }

        // End boundary
        $body .= "--{$this->boundary}--\r\n";

        return $body;
    }

    public function setBoundary(string $boundary): void
    {
        $this->boundary = $boundary;
    }

    /**
     * Get content type with boundary
     *
     * @return string
     */
    public function getContentType(): string
    {
        if (empty($this->files)) {
            return 'application/x-www-form-urlencoded';
        }

        return 'multipart/form-data; boundary=' . $this->boundary;
    }
}
