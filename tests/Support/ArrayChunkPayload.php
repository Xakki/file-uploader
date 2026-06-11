<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Tests\Support;

use Xakki\FileUploader\Contracts\ChunkPayload;

/**
 * In-memory ChunkPayload backed by a byte string, for core unit tests.
 */
final class ArrayChunkPayload implements ChunkPayload
{
    public function __construct(
        private string $uploadId,
        private int $chunkIndex,
        private int $totalChunks,
        private string $fileName,
        private int $fileSize,
        private string $mimeType,
        private int $fileLastModified,
        private ?string $fileHash,
        private string $bytes,
        private ?string $detectedMime = null,
        private ?string $locale = null,
    ) {}

    public function uploadId(): string
    {
        return $this->uploadId;
    }

    public function chunkIndex(): int
    {
        return $this->chunkIndex;
    }

    public function totalChunks(): int
    {
        return $this->totalChunks;
    }

    public function fileName(): string
    {
        return $this->fileName;
    }

    public function fileSize(): int
    {
        return $this->fileSize;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function fileLastModified(): int
    {
        return $this->fileLastModified;
    }

    public function fileHash(): ?string
    {
        return $this->fileHash;
    }

    public function locale(): ?string
    {
        return $this->locale;
    }

    public function detectedMimeType(): ?string
    {
        return $this->detectedMime;
    }

    public function chunkStream()
    {
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, $this->bytes);
        rewind($stream);

        return $stream;
    }
}
