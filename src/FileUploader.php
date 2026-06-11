<?php

declare(strict_types=1);

namespace Xakki\FileUploader;

use DateTimeInterface;
use LogicException;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Xakki\FileUploader\Contracts\ChunkPayload;
use Xakki\FileUploader\Contracts\Storage;
use Xakki\FileUploader\Contracts\UserResolver;
use Xakki\FileUploader\Dto\FileMetadata;
use Xakki\FileUploader\Exception\AttentionException;
use Xakki\FileUploader\Exception\MetadataNotFoundException;

class FileUploader
{
    public const ROUTE_PARAM_PLACEHOLDER = '__ID__';

    protected string $diskName;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config,
        protected Storage $storage,
        protected UserResolver $users,
        protected LoggerInterface $logger,
        protected ClockInterface $clock,
    ) {
        $this->diskName = (string) ($config['disk'] ?? 'default');
    }

    public function handleChunk(ChunkPayload $payload): true|FileMetadata
    {
        if ($payload->fileHash()) {
            try {
                return $this->readMetadata($payload->fileHash());
            } catch (MetadataNotFoundException) {
                // not yet uploaded — continue
            }
        }
        $this->guardFile($payload->fileName(), $payload->mimeType(), $payload->fileSize(), $payload->detectedMimeType());

        $temporaryDirectory = $this->temporaryDirectoryUpload($payload->uploadId());
        $this->ensureDirectory($temporaryDirectory);

        $pathChunk = $temporaryDirectory.'/'.$payload->chunkIndex();
        $stream = $payload->chunkStream();
        $written = $this->storage->writeStream($pathChunk, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        if (! $written) {
            throw new AttentionException('Failed to persist chunk.');
        }

        $completed = ($payload->chunkIndex() + 1) >= $payload->totalChunks();

        if ($completed) {
            return $this->assembleFile($payload);
        }

        return true;
    }

    protected function assembleFile(ChunkPayload $payload): FileMetadata
    {
        $userId = $this->users->id();
        $temporaryDirectory = $this->temporaryDirectoryUpload($payload->uploadId());
        $finalDirectory = $this->uploadDirectory();
        $fileName = $payload->fileName();
        $i = 0;
        while (true) {
            $path = $this->normalizeStoragePath($finalDirectory.'/'.$fileName);
            if ($this->storage->exists($path)) {
                $fileName = ++$i.'-'.$payload->fileName();
            } else {
                break;
            }
        }

        $resource = fopen('php://temp', 'w+b');
        if ($resource === false) {
            throw new LogicException('Failed to open temporary stream.');
        }

        $calculatedHash = null;

        try {
            $hashContext = hash_init('sha256');
            for ($i = 0; $i < $payload->totalChunks(); $i++) {
                $chunkPath = $temporaryDirectory.'/'.$i;
                if (! $this->storage->exists($chunkPath)) {
                    throw new LogicException("Missing chunk {$i} for {$payload->uploadId()}.");
                }

                $stream = $this->storage->readStream($chunkPath);
                if (! $stream) {
                    throw new LogicException("Unable to read chunk {$i} for {$payload->uploadId()}.");
                }

                while (! feof($stream)) {
                    $buffer = fread($stream, 1048576);
                    if ($buffer === false) {
                        fclose($stream);

                        throw new LogicException("Unable to read data from chunk {$i} for {$payload->uploadId()}.");
                    }
                    if ($buffer === '') {
                        break;
                    }
                    hash_update($hashContext, $buffer);
                    fwrite($resource, $buffer);
                }
                fclose($stream);
            }

            rewind($resource);
            $written = $this->storage->writeStream($path, $resource);
            if ($written === false) {
                throw new LogicException('Failed to persist assembled file.');
            }

            $calculatedHash = hash_final($hashContext);
            if ($payload->fileHash() && ! hash_equals($payload->fileHash(), $calculatedHash)) {
                throw new LogicException('File hash mismatch.');
            }
        } finally {
            fclose($resource);
            $this->storage->deleteDirectory($temporaryDirectory);
        }

        try {
            $metadata = $this->readMetadata($calculatedHash);
            $this->storage->delete($path);
            if ($metadata->trashPath) {
                $this->storage->move($metadata->trashPath, $path);
            } else {
                return $metadata;
            }
            $metadata->name = $payload->fileName();
            $metadata->size = $payload->fileSize();
            $metadata->mime = $payload->mimeType();
            $metadata->path = $path;
            $metadata->disk = $this->diskName;
            $metadata->url = $this->resolvePublicUrl($path);
            $metadata->deletedAt = null;
            $metadata->trashPath = null;
            $metadata->hash = $calculatedHash;
            $metadata->lastModified = $payload->fileLastModified();
            if ($userId) {
                $metadata->userId = $userId;
            }
        } catch (Throwable) {
            $metadata = new FileMetadata(
                id: $payload->uploadId(),
                name: $payload->fileName(),
                size: $payload->fileSize(),
                mime: $payload->mimeType(),
                path: $path,
                disk: $this->diskName,
                hash: $calculatedHash,
                createdAt: $this->nowIso(),
                lastModified: $payload->fileLastModified(),
                url: $this->resolvePublicUrl($path),
                deletedAt: null,
                userId: $userId,
            );
        }
        $this->writeMetadata($metadata);

        return $metadata;
    }

    protected function guardFile(string $fileName, ?string $mimeType, int $size, ?string $detectedMime): void
    {
        $maxSize = (int) ($this->config['max_size'] ?? 0);
        if ($maxSize > 0 && $size > $maxSize) {
            throw new AttentionException('File exceeds maximum allowed size.');
        }

        $allowedExtensions = $this->config['allowed_extensions'] ?? [];
        if (! empty($allowedExtensions)) {
            $extension = $this->sanitizeExtension(pathinfo($fileName, PATHINFO_EXTENSION));
            $mime = $mimeType ?: $detectedMime;

            $extensionAllowList = [];
            $mimeAllowList = [];

            if (is_string($allowedExtensions)) {
                $allowedExtensions = explode(',', $allowedExtensions);
            }

            foreach ($allowedExtensions as $key => $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }
                if (str_contains($value, ':')) {
                    [$key, $value] = array_map('trim', explode(':', $value, 2));
                    if ($key === '') {
                        continue;
                    }
                    if (! $value) {
                        $value = '*';
                    }
                }
                if (is_string($key)) {
                    $mimeAllowList[$key] = $value;

                    continue;
                }
                if ($value === '*') {
                    // Allow all extension
                    return;
                }
                $extensionAllowList[] = (string) $value;
            }

            if ($mime !== null && array_key_exists($mime, $mimeAllowList)) {
                $allowedExtension = $mimeAllowList[$mime];

                if ($allowedExtension === '*') {
                    return;
                }

                if ($extension === $this->sanitizeExtension((string) $allowedExtension)) {
                    return;
                }

                throw new AttentionException('Extension `'.$extension.'` is not allowed for MIME type `'.$mime.'`.');
            }

            if (! empty($extensionAllowList)) {
                $normalizedExtensions = array_map(fn ($ext) => $this->sanitizeExtension((string) $ext), $extensionAllowList);

                if (in_array($extension, $normalizedExtensions, true)) {
                    return;
                }

                throw new AttentionException('Extension `'.$extension.'` is not allowed.');
            }

            if ($mime !== null) {
                throw new AttentionException('MIME type `'.$mime.'` is not allowed.');
            }

            throw new AttentionException('MIME type is not allowed.');
        }
    }

    protected function ensureDirectory(string $path): void
    {
        $directory = trim($path, '/');
        if ($directory === '') {
            return;
        }

        if (! $this->storage->exists($directory)) {
            $this->storage->makeDirectory($directory);
        }
    }

    public function uploadDirectory(): string
    {
        $path = trim($this->config['directory'] ?? '', '/');
        $this->ensureDirectory($path);

        return $path;
    }

    protected function temporaryDirectoryUpload(string $uploadId): string
    {
        return $this->temporaryDirectory().'/'.$uploadId;
    }

    protected function temporaryDirectory(): string
    {
        return trim($this->config['temporary_directory'] ?? '.chunks', '/');
    }

    public function metadataDirectory(): string
    {
        return trim($this->config['metadata_directory'] ?? '.meta', '/');
    }

    protected function trashDirectory(): string
    {
        return trim($this->config['trash_directory'] ?? '.trash', '/');
    }

    public function metadataPath(string $fileHash): string
    {
        return $this->metadataDirectory().'/'.$fileHash.'.json';
    }

    public function readMetadata(string $fileHash): FileMetadata
    {
        return $this->readMetadataByPath($this->metadataPath($fileHash));
    }

    protected function readMetadataByPath(string $path): FileMetadata
    {
        if (! $this->storage->exists($path)) {
            throw new MetadataNotFoundException('Metadata file cant read or not exist: '.$path);
        }

        $content = $this->storage->read($path);
        $data = json_decode($content, true);

        if (! is_array($data)) {
            throw new MetadataNotFoundException('Metadata has wrong data: '.$path);
        }

        return FileMetadata::fromArray($data);
    }

    protected function writeMetadata(FileMetadata $metadata): void
    {
        $this->ensureDirectory($this->metadataDirectory());
        $this->storage->write($this->metadataPath($metadata->hash), (string) json_encode($metadata->toArray(), JSON_PRETTY_PRINT));
    }

    protected function deleteMetadata(string $fileHash): void
    {
        $path = $this->metadataPath($fileHash);
        if ($this->storage->exists($path)) {
            $this->storage->delete($path);
        }
    }

    /**
     * Synchronize metadata files with the actual files stored on disk.
     *
     * @return array{created: int, updated: int, deleted: int}
     */
    public function syncMetadata(): array
    {
        $created = 0;
        $updated = 0;
        $deleted = 0;
        $knownFileHash = [];

        $metadataDirectory = $this->metadataDirectory();
        if ($this->storage->exists($metadataDirectory)) {
            foreach ($this->storage->files($metadataDirectory) as $metadataPath) {
                if (! str_ends_with($metadataPath, '.json')) {
                    continue;
                }

                try {
                    $metadata = $this->readMetadataByPath($metadataPath);
                } catch (Throwable $e) {
                    $this->logger->error((string) $e);
                    $this->storage->delete($metadataPath);
                    $deleted++;

                    continue;
                }

                $needsUpdate = false;
                $filePath = $this->normalizeStoragePath((string) $metadata->path);
                $fileTrashPath = $this->normalizeStoragePath((string) $metadata->trashPath);

                if ($filePath && $this->storage->exists($filePath)) {
                    if ($fileTrashPath) {
                        $metadata->trashPath = null;
                        $metadata->deletedAt = null;
                        if ($this->storage->exists($fileTrashPath)) {
                            $this->storage->delete($fileTrashPath);
                        }
                        $needsUpdate = true;
                    }

                    if ($metadata->path !== $filePath) {
                        $metadata->path = $filePath;
                        $needsUpdate = true;
                    }
                } elseif ($fileTrashPath && $this->storage->exists($fileTrashPath)) {
                    if ($metadata->path) {
                        $metadata->path = null;
                        $needsUpdate = true;
                    }
                    if ($metadata->trashPath !== $fileTrashPath) {
                        $metadata->setDeleted($fileTrashPath, $this->nowIso());
                        $needsUpdate = true;
                    }
                    $filePath = $fileTrashPath;
                } else {
                    $this->storage->delete($metadataPath);
                    $deleted++;

                    continue;
                }

                $knownFileHash[$metadata->hash] = $filePath;

                $size = $this->storage->size($filePath);
                if ($metadata->size !== $size) {
                    $metadata->size = $size;
                    $needsUpdate = true;
                }

                $mime = $this->detectMimeType($filePath);
                if ($metadata->mime !== $mime) {
                    $metadata->mime = $mime;
                    $needsUpdate = true;
                }

                $hash = $this->calculateHashForPath($filePath);
                if ($metadata->hash !== $hash) {
                    $metadata->hash = $hash;
                    $needsUpdate = true;
                }

                if ($metadata->disk !== $this->diskName) {
                    $metadata->disk = $this->diskName;
                    $needsUpdate = true;
                }

                $url = $this->resolvePublicUrl($filePath);
                if ($metadata->url !== $url) {
                    $metadata->url = $url;
                    $needsUpdate = true;
                }

                if ($needsUpdate) {
                    $this->writeMetadata($metadata);
                    $updated++;
                }
            }
        }

        $ignoredDirectories = array_values(array_filter([
            $this->metadataDirectory(),
            $this->temporaryDirectory(),
            $this->trashDirectory(),
        ]));

        foreach ($this->storage->allFiles($this->uploadDirectory()) as $path) {
            $normalizedPath = $this->normalizeStoragePath($path);
            if ($this->shouldIgnorePath($normalizedPath, $ignoredDirectories)) {
                continue;
            }

            $metadata = $this->buildMetadataForFile($normalizedPath);
            if (isset($knownFileHash[$metadata->hash])) {
                continue;
            }
            $knownFileHash[$metadata->hash] = $normalizedPath;

            $this->writeMetadata($metadata);
            $created++;
        }

        foreach ($this->storage->allFiles($this->trashDirectory()) as $path) {
            $normalizedPath = $this->normalizeStoragePath($path);
            if (! $this->storage->exists($normalizedPath)) {
                continue;
            }

            $metadata = $this->buildMetadataForFile($normalizedPath);
            if (isset($knownFileHash[$metadata->hash])) {
                $this->storage->delete($normalizedPath);

                continue;
            }
            $knownFileHash[$metadata->hash] = $normalizedPath;

            $metadata->setDeleted((string) $metadata->path, $this->nowIso());
            $metadata->path = null;
            $this->writeMetadata($metadata);
            $updated++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
        ];
    }

    protected function normalizeStoragePath(string $path): string
    {
        return ltrim($path, '/');
    }

    /**
     * @param  array<int, string>  $ignoredDirectories
     */
    protected function shouldIgnorePath(string $path, array $ignoredDirectories): bool
    {
        foreach ($ignoredDirectories as $directory) {
            if ($directory === '') {
                continue;
            }

            if ($path === $directory || str_starts_with($path, $directory.'/')) {
                return true;
            }
        }

        return false;
    }

    protected function buildMetadataForFile(string $path): FileMetadata
    {
        $size = $this->storage->size($path);
        $mime = $this->detectMimeType($path);
        $hash = $this->calculateHashForPath($path);

        return new FileMetadata(
            id: $this->uuid4(),
            name: basename($path),
            size: $size,
            mime: $mime,
            path: $path,
            disk: $this->diskName,
            hash: $hash,
            createdAt: $this->nowIso(),
            lastModified: null,
            url: $this->resolvePublicUrl($path),
            deletedAt: null,
            userId: null,
        );
    }

    protected function detectMimeType(string $path): string
    {
        try {
            return $this->storage->mimeType($path);
        } catch (Throwable $e) {
            $this->logger->notice((string) $e);

            return '';
        }
    }

    protected function calculateHashForPath(string $path): string
    {
        $stream = $this->storage->readStream($path);
        if (! $stream) {
            $this->logger->error('Unable to read stream: '.$path);

            return md5($path);
        }

        try {
            $context = hash_init('sha256');
            while (! feof($stream)) {
                $buffer = fread($stream, 1048576);
                if ($buffer === false) {
                    $this->logger->error('Cant fread: '.$path);

                    return md5($path);
                }

                if ($buffer === '') {
                    break;
                }

                hash_update($context, $buffer);
            }

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    protected function resolvePublicUrl(string $path): ?string
    {
        return $this->storage->url($path);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatFileForResponse(FileMetadata $metadata): array
    {
        $metadata->url = $metadata->path ? $this->resolvePublicUrl($metadata->path) : null;

        return [
            'id' => $metadata->hash,
            'name' => $metadata->name,
            'size' => $metadata->size,
            'mime' => $metadata->mime,
            'url' => $metadata->url,
            'createdAt' => $metadata->createdAt,
            'deletedAt' => $metadata->deletedAt,
            'lastModified' => $metadata->lastModified,
        ];
    }

    protected function currentUserId(): ?string
    {
        return $this->users->id();
    }

    protected function sanitizeExtension(?string $extension): string
    {
        return $extension ? (string) preg_replace('/[^a-z0-9]+/i', '', mb_strtolower($extension)) : '';
    }

    protected function nowIso(): string
    {
        return $this->clock->now()->format(DateTimeInterface::ATOM);
    }

    protected function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0F) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
