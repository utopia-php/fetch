<?php

namespace Utopia\Fetch;

use PHPUnit\Framework\TestCase;

final class ChunkTest extends TestCase
{
    /**
     * Test chunk creation and getters
     * @return void
     */
    public function testChunkCreation(): void
    {
        $data = '{"message": "test data"}';
        $size = strlen($data);
        $timestamp = microtime(true);
        $index = 0;

        $chunk = new Chunk($data, $size, $timestamp, $index);

        // Test getData method
        $this->assertEquals($data, $chunk->getData());
        $this->assertIsString($chunk->getData());

        // Test getSize method
        $this->assertEquals($size, $chunk->getSize());
        $this->assertIsInt($chunk->getSize());
        $this->assertEquals(strlen($chunk->getData()), $chunk->getSize());

        // Test getTimestamp method
        $this->assertEquals($timestamp, $chunk->getTimestamp());
        $this->assertIsFloat($chunk->getTimestamp());

        // Test getIndex method
        $this->assertEquals($index, $chunk->getIndex());
        $this->assertIsInt($chunk->getIndex());
    }

    /**
     * Test chunk with empty data
     * @return void
     */
    public function testEmptyChunk(): void
    {
        $data = '';
        $size = 0;
        $timestamp = microtime(true);
        $index = 1;

        $chunk = new Chunk($data, $size, $timestamp, $index);

        $this->assertEquals('', $chunk->getData());
        $this->assertEquals(0, $chunk->getSize());
        $this->assertEquals($timestamp, $chunk->getTimestamp());
        $this->assertEquals(1, $chunk->getIndex());
    }

    /**
     * Test chunk with binary data
     * @return void
     */
    public function testBinaryChunk(): void
    {
        $data = pack('C*', 0x48, 0x65, 0x6c, 0x6c, 0x6f); // "Hello" in binary
        $size = strlen($data);
        $timestamp = microtime(true);
        $index = 2;

        $chunk = new Chunk($data, $size, $timestamp, $index);

        $this->assertEquals($data, $chunk->getData());
        $this->assertEquals(5, $chunk->getSize());
        $this->assertEquals($timestamp, $chunk->getTimestamp());
        $this->assertEquals(2, $chunk->getIndex());
        $this->assertEquals("Hello", $chunk->getData());
    }

    /**
     * Test chunk with special characters
     * @return void
     */
    public function testSpecialCharactersChunk(): void
    {
        $data = "Special chars: ñ, é, 漢字, 🌟";
        $size = strlen($data);
        $timestamp = microtime(true);
        $index = 3;

        $chunk = new Chunk($data, $size, $timestamp, $index);

        $this->assertEquals($data, $chunk->getData());
        $this->assertEquals($size, $chunk->getSize());
        $this->assertEquals($timestamp, $chunk->getTimestamp());
        $this->assertEquals(3, $chunk->getIndex());
    }

    /**
     * Test chunk immutability
     * @return void
     */
    public function testChunkImmutability(): void
    {
        $data = "test data";
        $size = strlen($data);
        $timestamp = microtime(true);
        $index = 4;

        $chunk = new Chunk($data, $size, $timestamp, $index);
        $originalData = $chunk->getData();
        $originalSize = $chunk->getSize();
        $originalTimestamp = $chunk->getTimestamp();
        $originalIndex = $chunk->getIndex();

        // Try to modify the data (this should create a new string, not modify the chunk)
        $modifiedData = $chunk->getData() . " modified";

        // Verify original chunk remains unchanged
        $this->assertEquals($originalData, $chunk->getData());
        $this->assertEquals($originalSize, $chunk->getSize());
        $this->assertEquals($originalTimestamp, $chunk->getTimestamp());
        $this->assertEquals($originalIndex, $chunk->getIndex());
        $this->assertNotEquals($modifiedData, $chunk->getData());
    }
}
