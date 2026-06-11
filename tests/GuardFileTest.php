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

class GuardFileTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/fu-guard-'.bin2hex(random_bytes(6));
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

    public function test_rejects_file_exceeding_max_size(): void
    {
        $this->expectException(AttentionException::class);
        $this->upload($this->manager(['max_size' => 10]), 'big.bin', 'application/octet-stream', random_bytes(20));
    }

    public function test_rejects_extension_not_in_allow_list(): void
    {
        $this->expectException(AttentionException::class);
        $this->upload($this->manager(['allowed_extensions' => ['txt']]), 'evil.bin', 'application/octet-stream', 'data');
    }

    public function test_accepts_extension_in_allow_list(): void
    {
        $meta = $this->upload($this->manager(['allowed_extensions' => ['txt']]), 'note.txt', 'text/plain', 'hello');
        self::assertInstanceOf(FileMetadata::class, $meta);
    }

    public function test_wildcard_allows_any_extension(): void
    {
        $meta = $this->upload($this->manager(['allowed_extensions' => ['*']]), 'whatever.xyz', 'application/octet-stream', 'data');
        self::assertInstanceOf(FileMetadata::class, $meta);
    }

    public function test_mime_map_allows_matching_extension(): void
    {
        $meta = $this->upload($this->manager(['allowed_extensions' => ['text/plain:txt']]), 'note.txt', 'text/plain', 'hello');
        self::assertInstanceOf(FileMetadata::class, $meta);
    }

    public function test_mime_map_rejects_wrong_extension_for_mime(): void
    {
        $this->expectException(AttentionException::class);
        $this->upload($this->manager(['allowed_extensions' => ['text/plain:txt']]), 'note.csv', 'text/plain', 'hello');
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

    private function upload(FileManager $manager, string $name, string $mime, string $bytes): true|FileMetadata
    {
        return $manager->handleChunk(new ArrayChunkPayload(
            uploadId: 'upload-1700000000000-'.bin2hex(random_bytes(4)),
            chunkIndex: 0,
            totalChunks: 1,
            fileName: $name,
            fileSize: strlen($bytes),
            mimeType: $mime,
            fileLastModified: 1700000000000,
            fileHash: null,
            bytes: $bytes,
        ));
    }
}
