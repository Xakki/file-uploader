<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Dto;

use Xakki\FileUploader\Exception\MetadataNotFoundException;

class FileMetadata
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $size,
        public string $mime,
        public ?string $path,
        public string $disk,
        public string $hash,
        public string $createdAt,
        public ?int $lastModified,
        public ?string $url,
        public ?string $userId,
        public ?string $deletedAt = null,
        public ?string $trashPath = null,
        public array $extra = [],
    ) {}

    public function setDeleted(string $trashPath, string $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
        $this->trashPath = $trashPath;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws MetadataNotFoundException
     */
    public static function fromArray(array $data): self
    {
        $requiredString = ['id', 'name', 'mime', 'disk', 'createdAt'];
        foreach ($requiredString as $key) {
            if (! isset($data[$key]) || ! is_string($data[$key])) {
                throw new MetadataNotFoundException("Metadata field `{$key}` is required and must be a string.");
            }
        }

        if (! isset($data['size']) || ! is_numeric($data['size'])) {
            throw new MetadataNotFoundException('Metadata field `size` is required and must be an integer.');
        }

        $knownKeys = [
            'id', 'name', 'size', 'mime', 'path', 'disk', 'hash',
            'createdAt', 'lastModified', 'url', 'deletedAt', 'userId', 'trashPath',
        ];

        /** @var array<string, mixed> $extra */
        $extra = array_diff_key($data, array_flip($knownKeys));

        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            size: (int) $data['size'],
            mime: (string) $data['mime'],
            path: isset($data['path']) ? (string) $data['path'] : null,
            disk: (string) $data['disk'],
            hash: isset($data['hash']) ? (string) $data['hash'] : '',
            createdAt: (string) $data['createdAt'],
            lastModified: isset($data['lastModified']) ? (int) $data['lastModified'] : null,
            url: isset($data['url']) ? (string) $data['url'] : null,
            userId: isset($data['userId']) ? (string) $data['userId'] : null,
            deletedAt: isset($data['deletedAt']) ? (string) $data['deletedAt'] : null,
            trashPath: isset($data['trashPath']) ? (string) $data['trashPath'] : null,
            extra: $extra,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = get_object_vars($this);
        $extra = $data['extra'];
        unset($data['extra']);

        if (count($extra)) {
            $data = array_merge($extra, $data);
        }

        return $data;
    }
}
