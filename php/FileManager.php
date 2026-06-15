<?php

declare(strict_types=1);

namespace Xakki\FileUploader;

use DateTimeImmutable;
use Throwable;
use Xakki\FileUploader\Dto\FileMetadata;
use Xakki\FileUploader\Exception\AuthorizationException;

class FileManager extends FileUploader
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $metadataDirectory = $this->metadataDirectory();
        if (! $this->storage->exists($metadataDirectory)) {
            return [];
        }

        $files = [];
        foreach ($this->storage->files($metadataDirectory) as $path) {
            if (! str_ends_with($path, '.json')) {
                continue;
            }

            try {
                $metadata = $this->readMetadataByPath($path);
            } catch (Throwable $e) {
                $this->logger->warning((string) $e);

                continue;
            }

            if ($metadata->deletedAt) {
                continue;
            }

            if (! $this->storage->exists((string) $metadata->path)) {
                $metadata->setDeleted('', $this->nowIso());
                $this->writeMetadata($metadata);
                $this->logger->notice('File not found: '.$metadata->path);

                continue;
            }

            $files[] = $this->formatFileForResponse($metadata);
        }

        usort($files, static fn (array $a, array $b) => strcmp($b['createdAt'], $a['createdAt']));

        return $files;
    }

    public function delete(string $fileHash): bool
    {
        try {
            $metadata = $this->readMetadata($fileHash);
        } catch (Throwable $e) {
            $this->logger->notice((string) $e);

            return false;
        }

        if (! $this->canManageMetadata($metadata)) {
            throw new AuthorizationException('error.not_authorized_delete');
        }

        if ($metadata->deletedAt) {
            $this->logger->notice('File ['.$fileHash.'] already move to trash');

            return true;
        }

        $softDelete = (bool) ($this->config['soft_delete'] ?? true);

        if ($softDelete) {
            $trashDirectory = $this->trashDirectory();
            $this->ensureDirectory($trashDirectory);

            $trashPath = trim($trashDirectory, '/').'/'.$metadata->name;

            if (! $metadata->path || ! $this->storage->exists($metadata->path)) {
                $this->deleteMetadata($fileHash);

                return false;
            }

            if (! $this->storage->move($metadata->path, $trashPath)) {
                $this->logger->notice('Cant move to trash ['.$fileHash.']');

                return false;
            }

            $metadata->setDeleted($trashPath, $this->nowIso());

            $this->logger->info('Update meta ['.$fileHash.'] : '.json_encode($metadata->toArray()));
            $this->writeMetadata($metadata);

            return true;
        }

        if ($metadata->path && $this->storage->exists($metadata->path)) {
            $this->storage->delete($metadata->path);
        }

        $this->deleteMetadata($fileHash);

        return true;
    }

    public function restore(string $fileHash): bool
    {
        try {
            $metadata = $this->readMetadata($fileHash);
        } catch (Throwable $e) {
            $this->logger->notice((string) $e);

            return false;
        }

        if (! $metadata->deletedAt) {
            $this->logger->notice('Cant restore metadata #'.$metadata->id);

            return false;
        }

        if (! $this->canManageMetadata($metadata)) {
            throw new AuthorizationException('error.not_authorized_restore');
        }

        $trashPath = $metadata->trashPath;
        if (! $trashPath || ! $this->storage->exists($trashPath)) {
            $this->deleteMetadata($fileHash);

            return false;
        }

        $this->uploadDirectory();

        if (! $this->storage->move($trashPath, (string) $metadata->path)) {
            return false;
        }

        $metadata->deletedAt = null;
        $metadata->trashPath = null;
        $metadata->url = $this->resolvePublicUrl((string) $metadata->path);
        $this->writeMetadata($metadata);

        return true;
    }

    public function cleanupTrash(): int
    {
        $ttl = (int) ($this->config['trash_ttl_days'] ?? 30);
        $threshold = $ttl > 0 ? $this->clock->now()->modify("-{$ttl} days") : $this->clock->now();

        $metadataDirectory = $this->metadataDirectory();
        if (! $this->storage->exists($metadataDirectory)) {
            return 0;
        }

        $removed = 0;
        foreach ($this->storage->files($metadataDirectory) as $path) {
            if (! str_ends_with($path, '.json')) {
                continue;
            }

            try {
                $metadata = $this->readMetadataByPath($path);
            } catch (Throwable $e) {
                $this->logger->warning((string) $e);

                continue;
            }

            if (! $metadata->deletedAt) {
                continue;
            }

            if (new DateTimeImmutable($metadata->deletedAt) <= $threshold) {
                if ($metadata->trashPath !== null && $this->storage->exists($metadata->trashPath)) {
                    $this->storage->delete($metadata->trashPath);
                }

                $this->storage->delete($path);
                $removed++;
            }
        }

        return $removed;
    }

    protected function canManageMetadata(FileMetadata $metadata): bool
    {
        if ($this->hasFullAccess()) {
            return true;
        }

        if ($this->config['allow_delete_all_files'] ?? false) {
            return true;
        }

        $currentUserId = $this->users->id();
        if ($currentUserId === null) {
            return false;
        }

        $ownerId = $metadata->userId;
        if ($ownerId === null) {
            return false;
        }

        return (string) $ownerId === $currentUserId;
    }

    protected function hasFullAccess(): bool
    {
        $fullAccess = $this->config['full_access'] ?? [];

        $userIds = array_map(static fn ($value) => (string) $value, $fullAccess['users'] ?? []);
        $currentUserId = $this->users->id();
        if ($currentUserId !== null && $userIds && in_array($currentUserId, $userIds, true)) {
            return true;
        }

        $roles = $fullAccess['roles'] ?? [];
        if ($roles && $this->users->hasAnyRole($roles)) {
            return true;
        }

        return false;
    }

    protected function isOwnFile(FileMetadata $metadata): bool
    {
        $currentUserId = $this->currentUserId();
        if ($currentUserId === null) {
            return false;
        }

        if ($metadata->userId === null) {
            return false;
        }

        return (string) $metadata->userId === $currentUserId;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatFileForResponse(FileMetadata $metadata): array
    {
        $response = parent::formatFileForResponse($metadata);
        $response['own'] = $this->isOwnFile($metadata);

        return $response;
    }
}
