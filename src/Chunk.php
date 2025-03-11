<?php

namespace Utopia\Fetch;

/**
 * Chunk class
 * Represents a chunk of data received from an HTTP response
 * @package Utopia\Fetch
 */
class Chunk
{
    /**
     * @param string $data The raw chunk data
     * @param int $size The size of the chunk in bytes
     * @param float $timestamp The timestamp when the chunk was received
     * @param int $index The sequential index of this chunk in the response
     */
    public function __construct(
        private readonly string $data,
        private readonly int $size,
        private readonly float $timestamp,
        private readonly int $index,
    ) {
    }

    /**
     * Get the raw chunk data
     * 
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Get the size of the chunk in bytes
     * 
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get the timestamp when the chunk was received
     * 
     * @return float
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    /**
     * Get the sequential index of this chunk
     * 
     * @return int
     */
    public function getIndex(): int
    {
        return $this->index;
    }
} 