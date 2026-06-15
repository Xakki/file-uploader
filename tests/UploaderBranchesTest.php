<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Tests;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Xakki\FileUploader\Auth\NullUserResolver;
use Xakki\FileUploader\Clock\SystemClock;
use Xakki\FileUploader\Contracts\UserResolver;
use Xakki\FileUploader\Dto\FileMetadata;
use Xakki\FileUploader\Exception\AttentionException;
use Xakki\FileUploader\FileManager;
use Xakki\FileUploader\Storage\FlysystemStorage;
use Xakki\FileUploader\Tests\Support\ArrayChunkPayload;

class UploaderBranchesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/fu-branch-'.bin2hex(random_bytes(6));
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

    public function test_declared_hash_mismatch_throws(): void
    {
        $manager = $this->manager();

        $this->expectException(AttentionException::class);
        $manager->handleChunk($this->chunk($this->uploadId(), 0, 1, 'abc', hash('sha256', 'WRONG'), 'abc'));
    }

    public function test_inconsistent_chunk_count_throws(): void
    {
        $manager = $this->manager(['chunk_size' => 1024 * 1024]);

        // 5 chunks declared for a 3-byte file is impossible (each chunk carries >= 1 byte).
        $this->expectException(AttentionException::class);
        $manager->handleChunk($this->chunk($this->uploadId(), 0, 5, 'abc', '', 'a'));
    }

    public function test_missing_chunk_throws_incomplete_upload(): void
    {
        $manager = $this->manager();

        // Send only the final chunk of a 2-chunk upload -> assembly finds chunk 0 missing.
        $this->expectException(AttentionException::class);
        $manager->handleChunk($this->chunk($this->uploadId(), 1, 2, 'abcd', '', 'cd'));
    }

    public function test_multi_chunk_upload_assembles_full_file(): void
    {
        $manager = $this->manager();
        $body = 'HELLO-CORE-MULTICHUNK';
        $hash = hash('sha256', $body);
        $half = (int) ceil(strlen($body) / 2);
        $id = $this->uploadId();

        $first = $manager->handleChunk($this->chunk($id, 0, 2, $body, $hash, substr($body, 0, $half)));
        self::assertTrue($first);

        $second = $manager->handleChunk($this->chunk($id, 1, 2, $body, $hash, substr($body, $half)));
        self::assertInstanceOf(FileMetadata::class, $second);
        self::assertSame($hash, $second->hash);
        self::assertSame(strlen($body), $second->size);
    }

    public function test_full_access_by_user_id_allows_delete_without_blanket_flag(): void
    {
        $users = new class implements UserResolver
        {
            public function id(): string
            {
                return 'u1';
            }

            public function hasAnyRole(array $roles): bool
            {
                return false;
            }
        };

        $manager = $this->manager([
            'allow_delete_all_files' => false,
            'full_access' => ['users' => ['u1'], 'roles' => []],
        ], $users);

        $body = 'owned-by-u1';
        $hash = hash('sha256', $body);
        $manager->handleChunk($this->chunk($this->uploadId(), 0, 1, $body, $hash, $body));

        self::assertTrue($manager->delete($hash));
    }

    public function test_full_access_by_role_allows_delete_without_blanket_flag(): void
    {
        $users = new class implements UserResolver
        {
            public function id(): string
            {
                return 'u2';
            }

            public function hasAnyRole(array $roles): bool
            {
                return in_array('admin', $roles, true);
            }
        };

        $manager = $this->manager([
            'allow_delete_all_files' => false,
            'full_access' => ['users' => [], 'roles' => ['admin']],
        ], $users);

        $body = 'role-protected';
        $hash = hash('sha256', $body);
        $manager->handleChunk($this->chunk($this->uploadId(), 0, 1, $body, $hash, $body));

        self::assertTrue($manager->delete($hash));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function manager(array $overrides = [], ?UserResolver $users = null): FileManager
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

        return new FileManager($config, $storage, $users ?? new NullUserResolver, new NullLogger, new SystemClock);
    }

    private function chunk(string $id, int $index, int $total, string $fileBody, string $hash, string $bytes): ArrayChunkPayload
    {
        return new ArrayChunkPayload(
            uploadId: $id,
            chunkIndex: $index,
            totalChunks: $total,
            fileName: 'a.txt',
            fileSize: strlen($fileBody),
            mimeType: 'text/plain',
            fileLastModified: 1700000000000,
            fileHash: $hash,
            bytes: $bytes,
        );
    }

    private function uploadId(): string
    {
        return 'upload-1700000000000-'.bin2hex(random_bytes(4));
    }
}
