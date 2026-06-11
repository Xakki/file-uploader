<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Contracts;

/**
 * One uploaded chunk, framework-agnostic. A binding adapts its request object
 * (Laravel FormRequest, Symfony Request, PSR-7 request) to this interface.
 *
 * Fields mirror Upload Protocol v1 (see protocol/SPEC.md §2).
 */
interface ChunkPayload
{
    public function uploadId(): string;

    public function chunkIndex(): int;

    public function totalChunks(): int;

    public function fileName(): string;

    public function fileSize(): int;

    public function mimeType(): string;

    public function fileLastModified(): int;

    public function fileHash(): ?string;

    public function locale(): ?string;

    /**
     * MIME type detected from the chunk bytes by the binding, used only as a
     * fallback when the client-declared mimeType is empty.
     */
    public function detectedMimeType(): ?string;

    /**
     * A readable stream of this chunk's bytes. Caller is responsible for closing
     * it (or handing it to a Storage write that closes it).
     *
     * @return resource
     */
    public function chunkStream();
}
