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
use Xakki\FileUploader\FileManager;
use Xakki\FileUploader\Storage\FlysystemStorage;
use Xakki\FileUploader\Tests\Support\ArrayChunkPayload;

class FileManagerTest extends TestCase
{
    private string $root;

    private FileManager $manager;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/fu-core-'.bin2hex(random_bytes(6));
        $storage = new FlysystemStorage(new Filesystem(new LocalFilesystemAdapter($this->root)));

        $config = [
            'disk' => 'test',
            'directory' => 'uploads',
            'temporary_directory' => '.chunks',
            'metadata_directory' => '.meta',
            'trash_directory' => '.trash',
            'allowed_extensions' => [],
            'soft_delete' => true,
            'trash_ttl_days' => 30,
            'allow_delete_all_files' => true,
            'full_access' => ['users' => [], 'roles' => []],
        ];

        $this->manager = new FileManager($config, $storage, new NullUserResolver, new NullLogger, new SystemClock);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function test_chunked_upload_assembles_file_and_verifies_hash(): void
    {
        $content = random_bytes(3000);
        $hash = hash('sha256', $content);
        $chunks = [substr($content, 0, 1500), substr($content, 1500)];
        $uploadId = 'upload-1700000000000-abcd1234';

        $first = $this->manager->handleChunk($this->chunk($uploadId, 0, 2, $content, $hash, $chunks[0]));
        self::assertTrue($first);

        $meta = $this->manager->handleChunk($this->chunk($uploadId, 1, 2, $content, $hash, $chunks[1]));
        self::assertInstanceOf(FileMetadata::class, $meta);
        self::assertSame($hash, $meta->hash);
        self::assertSame(3000, $meta->size);

        $files = $this->manager->list();
        self::assertCount(1, $files);
        self::assertSame($hash, $files[0]['id']);
        self::assertFalse($files[0]['own']);
    }

    public function test_soft_delete_then_cleanup_removes_file(): void
    {
        $content = random_bytes(800);
        $hash = hash('sha256', $content);
        $uploadId = 'upload-1700000000000-abcd5678';

        $this->manager->handleChunk($this->chunk($uploadId, 0, 1, $content, $hash, $content));

        self::assertTrue($this->manager->delete($hash));
        self::assertCount(0, $this->manager->list());

        // ttl_days = 0 → threshold is now, so the just-trashed file is purged.
        $reflection = new \ReflectionProperty($this->manager, 'config');
        $reflection->setAccessible(true);
        $cfg = $reflection->getValue($this->manager);
        $cfg['trash_ttl_days'] = 0;
        $reflection->setValue($this->manager, $cfg);

        self::assertSame(1, $this->manager->cleanupTrash());
    }

    private function chunk(string $uploadId, int $idx, int $total, string $full, string $hash, string $bytes): ArrayChunkPayload
    {
        return new ArrayChunkPayload(
            uploadId: $uploadId,
            chunkIndex: $idx,
            totalChunks: $total,
            fileName: 'sample.bin',
            fileSize: strlen($full),
            mimeType: 'application/octet-stream',
            fileLastModified: 1700000000000,
            fileHash: $hash,
            bytes: $bytes,
        );
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
