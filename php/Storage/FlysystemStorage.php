<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Storage;

use Closure;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Throwable;
use Xakki\FileUploader\Contracts\Storage;

/**
 * Storage adapter over a League\Flysystem operator. Used by the Symfony binding and
 * by custom/standalone projects. URL resolution is delegated to an optional closure
 * since Flysystem adapters do not uniformly expose public URLs.
 */
final class FlysystemStorage implements Storage
{
    /**
     * @param  (Closure(string): ?string)|null  $urlResolver
     */
    public function __construct(
        private readonly FilesystemOperator $op,
        private readonly ?Closure $urlResolver = null,
    ) {}

    public function exists(string $path): bool
    {
        return $this->op->fileExists($path) || $this->op->directoryExists($path);
    }

    public function read(string $path): string
    {
        return $this->op->read($path);
    }

    public function write(string $path, string $contents): bool
    {
        $this->op->write($path, $contents);

        return true;
    }

    public function readStream(string $path)
    {
        try {
            return $this->op->readStream($path);
        } catch (Throwable) {
            return null;
        }
    }

    public function writeStream(string $path, $resource): bool
    {
        $this->op->writeStream($path, $resource);

        return true;
    }

    public function delete(string $path): bool
    {
        try {
            $this->op->delete($path);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function deleteDirectory(string $path): bool
    {
        try {
            $this->op->deleteDirectory($path);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function move(string $from, string $to): bool
    {
        try {
            $this->op->move($from, $to);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function makeDirectory(string $path): bool
    {
        $this->op->createDirectory($path);

        return true;
    }

    public function files(string $directory): array
    {
        return $this->list($directory, false);
    }

    public function allFiles(string $directory): array
    {
        return $this->list($directory, true);
    }

    public function size(string $path): int
    {
        return $this->op->fileSize($path);
    }

    public function mimeType(string $path): string
    {
        return $this->op->mimeType($path);
    }

    public function url(string $path): ?string
    {
        return $this->urlResolver ? ($this->urlResolver)($path) : null;
    }

    /**
     * @return string[]
     */
    private function list(string $directory, bool $recursive): array
    {
        if ($directory !== '' && ! $this->op->directoryExists($directory)) {
            return [];
        }

        return $this->op
            ->listContents($directory, $recursive)
            ->filter(static fn (StorageAttributes $a) => $a->isFile())
            ->map(static fn (StorageAttributes $a) => $a->path())
            ->toArray();
    }
}
