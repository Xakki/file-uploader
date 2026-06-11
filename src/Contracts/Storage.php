<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Contracts;

/**
 * Storage seam used by the core. A binding adapts its filesystem (Laravel disk,
 * Flysystem operator, native fs) to this interface.
 */
interface Storage
{
    public function exists(string $path): bool;

    public function read(string $path): string;

    public function write(string $path, string $contents): bool;

    /**
     * @return resource|null
     */
    public function readStream(string $path);

    /**
     * @param  resource  $resource
     */
    public function writeStream(string $path, $resource): bool;

    public function delete(string $path): bool;

    public function deleteDirectory(string $path): bool;

    public function move(string $from, string $to): bool;

    public function makeDirectory(string $path): bool;

    /**
     * Non-recursive list of file paths in a directory.
     *
     * @return string[]
     */
    public function files(string $directory): array;

    /**
     * Recursive list of file paths under a directory.
     *
     * @return string[]
     */
    public function allFiles(string $directory): array;

    public function size(string $path): int;

    public function mimeType(string $path): string;

    /**
     * Public URL for a stored path, or null when the storage cannot resolve one.
     */
    public function url(string $path): ?string;
}
