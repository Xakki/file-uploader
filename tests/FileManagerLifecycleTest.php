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
use Xakki\FileUploader\Exception\AuthorizationException;
use Xakki\FileUploader\FileManager;
use Xakki\FileUploader\Storage\FlysystemStorage;
use Xakki\FileUploader\Tests\Support\ArrayChunkPayload;

class FileManagerLifecycleTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/fu-life-'.bin2hex(random_bytes(6));
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

    public function test_hard_delete_removes_file_and_metadata(): void
    {
        $manager = $this->manager(['soft_delete' => false]);
        $content = random_bytes(500);
        $hash = hash('sha256', $content);

        $manager->handleChunk($this->chunk($manager, $content, $hash));
        self::assertCount(1, $manager->list());

        self::assertTrue($manager->delete($hash));
        self::assertCount(0, $manager->list());
        self::assertFalse($manager->delete($hash));
    }

    public function test_duplicate_content_is_deduplicated(): void
    {
        $manager = $this->manager();
        $content = random_bytes(400);
        $hash = hash('sha256', $content);

        $first = $manager->handleChunk($this->chunk($manager, $content, $hash));
        self::assertInstanceOf(FileMetadata::class, $first);

        $second = $manager->handleChunk($this->chunk($manager, $content, $hash));
        self::assertInstanceOf(FileMetadata::class, $second);
        self::assertSame($hash, $second->hash);
        self::assertCount(1, $manager->list());
    }

    public function test_soft_delete_then_restore_brings_file_back(): void
    {
        $manager = $this->manager();
        $content = random_bytes(450);
        $hash = hash('sha256', $content);

        $manager->handleChunk($this->chunk($manager, $content, $hash));
        self::assertTrue($manager->delete($hash));
        self::assertCount(0, $manager->list());

        self::assertTrue($manager->restore($hash));

        $files = $manager->list();
        self::assertCount(1, $files);
        self::assertSame($hash, $files[0]['id']);
        self::assertNull($manager->readMetadata($hash)->deletedAt);
    }

    public function test_delete_denied_for_anonymous_user_without_blanket_access(): void
    {
        $manager = $this->manager(['allow_delete_all_files' => false]);
        $content = random_bytes(300);
        $hash = hash('sha256', $content);

        $manager->handleChunk($this->chunk($manager, $content, $hash));

        $this->expectException(AuthorizationException::class);
        $manager->delete($hash);
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
            'soft_delete' => true,
            'trash_ttl_days' => 30,
            'allow_delete_all_files' => true,
            'full_access' => ['users' => [], 'roles' => []],
        ], $overrides);

        return new FileManager($config, $storage, new NullUserResolver, new NullLogger, new SystemClock);
    }

    private function chunk(FileManager $manager, string $content, string $hash): ArrayChunkPayload
    {
        return new ArrayChunkPayload(
            uploadId: 'upload-1700000000000-'.bin2hex(random_bytes(4)),
            chunkIndex: 0,
            totalChunks: 1,
            fileName: 'sample.bin',
            fileSize: strlen($content),
            mimeType: 'application/octet-stream',
            fileLastModified: 1700000000000,
            fileHash: $hash,
            bytes: $content,
        );
    }
}
