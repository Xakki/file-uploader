<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Tests;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Xakki\FileUploader\Auth\NullUserResolver;
use Xakki\FileUploader\Clock\SystemClock;
use Xakki\FileUploader\Dto\FileMetadata;
use Xakki\FileUploader\Exception\AttentionException;
use Xakki\FileUploader\FileManager;
use Xakki\FileUploader\Storage\FlysystemStorage;
use Xakki\FileUploader\Tests\Support\ArrayChunkPayload;

class MaxFilesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/fu-maxfiles-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->root)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function test_zero_means_unlimited(): void
    {
        $manager = $this->manager(['max_files' => 0]);
        for ($i = 0; $i < 5; $i++) {
            self::assertInstanceOf(FileMetadata::class, $this->upload($manager, "f{$i}.txt", "data-{$i}"));
        }
    }

    public function test_allows_up_to_the_limit_then_rejects(): void
    {
        $manager = $this->manager(['max_files' => 2]);

        self::assertInstanceOf(FileMetadata::class, $this->upload($manager, 'a.txt', 'aaa'));
        self::assertInstanceOf(FileMetadata::class, $this->upload($manager, 'b.txt', 'bbb'));

        $this->expectException(AttentionException::class);
        $this->upload($manager, 'c.txt', 'ccc');
    }

    public function test_dedup_hit_does_not_count_against_the_limit(): void
    {
        $manager = $this->manager(['max_files' => 1]);

        $first = $this->upload($manager, 'a.txt', 'same-bytes');
        self::assertInstanceOf(FileMetadata::class, $first);

        // Re-uploading the same file: the client sends the known content hash, so
        // handleChunk short-circuits to the existing metadata before the capacity
        // guard — a dedup hit must not be rejected even at the limit.
        $again = $this->upload($manager, 'a.txt', 'same-bytes', $first->hash);
        self::assertInstanceOf(FileMetadata::class, $again);
        self::assertSame($first->hash, $again->hash);
    }

    public function test_soft_deleted_files_free_up_capacity(): void
    {
        $manager = $this->manager(['max_files' => 1]);

        $meta = $this->upload($manager, 'a.txt', 'aaa');
        self::assertInstanceOf(FileMetadata::class, $meta);

        self::assertTrue($manager->delete($meta->hash));

        // Slot freed by the soft delete — a new upload is allowed again.
        self::assertInstanceOf(FileMetadata::class, $this->upload($manager, 'b.txt', 'bbb'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function manager(array $overrides = []): FileManager
    {
        $storage = new FlysystemStorage(new Filesystem(new LocalFilesystemAdapter($this->root)));

        $config = array_merge([
            'disk' => 'test',
            'directory' => 'uploads',
            'temporary_directory' => '.chunks',
            'metadata_directory' => '.meta',
            'trash_directory' => '.trash',
            'allowed_extensions' => [],
            'max_size' => 0,
            'max_files' => 0,
            'soft_delete' => true,
            'trash_ttl_days' => 30,
            'allow_delete_all_files' => true,
            'full_access' => ['users' => [], 'roles' => []],
        ], $overrides);

        return new FileManager($config, $storage, new NullUserResolver, new NullLogger, new SystemClock);
    }

    private function upload(FileManager $manager, string $name, string $bytes, ?string $fileHash = null): true|FileMetadata
    {
        return $manager->handleChunk(new ArrayChunkPayload(
            uploadId: 'upload-1700000000000-'.bin2hex(random_bytes(4)),
            chunkIndex: 0,
            totalChunks: 1,
            fileName: $name,
            fileSize: strlen($bytes),
            mimeType: 'text/plain',
            fileLastModified: 1700000000000,
            fileHash: $fileHash,
            bytes: $bytes,
        ));
    }
}
