<?php

namespace Utopia\Fetch;

use PHPUnit\Framework\TestCase;

final class FormDataTest extends TestCase
{
    /**
     * Test adding fields to FormData
     * @return void
     */
    public function testAddField(): void
    {
        $formData = new FormData();
        $formData->addField('name', 'John Doe');
        $formData->addField('email', 'john@example.com');

        $body = $formData->build();

        $this->assertStringContainsString('Content-Disposition: form-data; name="name"', $body);
        $this->assertStringContainsString('John Doe', $body);
        $this->assertStringContainsString('Content-Disposition: form-data; name="email"', $body);
        $this->assertStringContainsString('john@example.com', $body);
    }

    /**
     * Test adding fields with custom headers
     * @return void
     */
    public function testAddFieldWithCustomHeaders(): void
    {
        $formData = new FormData();
        $formData->addField('name', 'John Doe', ['X-Custom-Header' => 'Custom Value']);

        $body = $formData->build();

        $this->assertStringContainsString('Content-Disposition: form-data; name="name"', $body);
        $this->assertStringContainsString('X-Custom-Header: Custom Value', $body);
        $this->assertStringContainsString('John Doe', $body);
    }

    /**
     * Test adding a file to FormData
     * @return void
     */
    public function testAddFile(): void
    {
        $filePath = __DIR__ . '/resources/test.txt';

        $formData = new FormData();
        $formData->addFile('file', $filePath);

        $body = $formData->build();

        $this->assertStringContainsString('Content-Disposition: form-data; name="file"; filename="test.txt"', $body);
        $this->assertStringContainsString('Content-Type: text/plain', $body);
        $this->assertStringContainsString('Lorem ipsum dolor sit amet', $body);
        $this->assertStringContainsString('Vestibulum tempus sit amet purus et congue.', $body);
    }

    /**
     * Test adding file content directly to FormData
     * @return void
     */
    public function testAddContent(): void
    {
        $content = 'Custom file content';
        $fileName = 'custom.txt';
        $mimeType = 'text/plain';

        $formData = new FormData();
        $formData->addContent('file', $content, $fileName, $mimeType);

        $body = $formData->build();

        $this->assertStringContainsString('Content-Disposition: form-data; name="file"; filename="custom.txt"', $body);
        $this->assertStringContainsString('Content-Type: text/plain', $body);
        $this->assertStringContainsString('Custom file content', $body);
    }

    /**
     * Test setting a custom boundary
     * @return void
     */
    public function testSetBoundary(): void
    {
        $formData = new FormData();
        $formData->setBoundary('custom-boundary');
        $formData->addContent('file', 'Custom file content', 'custom.txt', 'text/plain');

        $body = $formData->build();

        $this->assertStringContainsString('--custom-boundary', $body);
        $this->assertEquals('multipart/form-data; boundary=custom-boundary', $formData->getContentType());
    }

    /**
     * Test getContentType returns the correct type for form fields
     * @return void
     */
    public function testGetContentTypeForFields(): void
    {
        $formData = new FormData();
        $formData->addField('name', 'John Doe');

        $this->assertEquals('application/x-www-form-urlencoded', $formData->getContentType());
    }

    /**
     * Test getContentType returns the correct type for files
     * @return void
     */
    public function testGetContentTypeForFiles(): void
    {
        $content = 'Test content';
        $formData = new FormData();
        $formData->addContent('file', $content, 'test.txt');

        $contentType = $formData->getContentType();
        $this->assertStringStartsWith('multipart/form-data; boundary=', $contentType);
    }
}
