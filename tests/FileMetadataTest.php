<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Tests;

use PHPUnit\Framework\TestCase;
use Xakki\FileUploader\Dto\FileMetadata;
use Xakki\FileUploader\Exception\MetadataNotFoundException;

class FileMetadataTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sample(): array
    {
        return [
            'id' => 'id-1',
            'name' => 'a.txt',
            'size' => 12,
            'mime' => 'text/plain',
            'path' => 'uploads/a.txt',
            'disk' => 'test',
            'hash' => 'abc',
            'createdAt' => '2026-01-01T00:00:00+00:00',
            'lastModified' => 1700000000000,
            'url' => 'http://example.test/a.txt',
            'userId' => 'u1',
            'deletedAt' => null,
            'trashPath' => null,
        ];
    }

    public function test_from_array_builds_dto_and_captures_unknown_keys_as_extra(): void
    {
        $data = $this->sample();
        $data['custom'] = 'kept';

        $meta = FileMetadata::fromArray($data);

        self::assertSame('id-1', $meta->id);
        self::assertSame(12, $meta->size);
        self::assertSame('uploads/a.txt', $meta->path);
        self::assertSame(['custom' => 'kept'], $meta->extra);
    }

    public function test_from_array_coerces_numeric_string_size_to_int(): void
    {
        $data = $this->sample();
        $data['size'] = '34';

        self::assertSame(34, FileMetadata::fromArray($data)->size);
    }

    public function test_from_array_throws_when_required_string_missing(): void
    {
        $data = $this->sample();
        unset($data['id']);

        $this->expectException(MetadataNotFoundException::class);
        FileMetadata::fromArray($data);
    }

    public function test_from_array_throws_when_size_is_not_numeric(): void
    {
        $data = $this->sample();
        $data['size'] = 'not-a-number';

        $this->expectException(MetadataNotFoundException::class);
        FileMetadata::fromArray($data);
    }

    public function test_to_array_merges_extra_and_round_trips(): void
    {
        $data = $this->sample();
        $data['custom'] = 'kept';

        $original = FileMetadata::fromArray($data);
        $arr = $original->toArray();

        self::assertSame('kept', $arr['custom']);
        self::assertArrayNotHasKey('extra', $arr);
        self::assertEquals($original, FileMetadata::fromArray($arr));
    }

    public function test_set_deleted_records_trash_path_and_timestamp(): void
    {
        $meta = FileMetadata::fromArray($this->sample());

        $meta->setDeleted('.trash/a.txt', '2026-02-02T00:00:00+00:00');

        self::assertSame('.trash/a.txt', $meta->trashPath);
        self::assertSame('2026-02-02T00:00:00+00:00', $meta->deletedAt);
    }
}
