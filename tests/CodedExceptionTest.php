<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xakki\FileUploader\Exception\AttentionException;
use Xakki\FileUploader\Exception\AuthorizationException;
use Xakki\FileUploader\Exception\UploadException;
use Xakki\FileUploader\Protocol\MessageCatalog;

class CodedExceptionTest extends TestCase
{
    protected function setUp(): void
    {
        MessageCatalog::flush();
    }

    /**
     * @return array<string, array{string, array<string, scalar>, string}>
     */
    public static function attentionProvider(): array
    {
        return [
            'chunk_persist_failed' => [
                'error.chunk_persist_failed', [], 'Failed to persist chunk.',
            ],
            'max_size_exceeded' => [
                'error.max_size_exceeded', [], 'File exceeds maximum allowed size.',
            ],
            'extension_mime_mismatch' => [
                'error.extension_mime_mismatch',
                ['ext' => 'exe', 'mime' => 'image/png'],
                'Extension `exe` is not allowed for MIME type `image/png`.',
            ],
            'extension_not_allowed' => [
                'error.extension_not_allowed', ['ext' => 'exe'], 'Extension `exe` is not allowed.',
            ],
            'mime_not_allowed' => [
                'error.mime_not_allowed', ['mime' => 'image/png'], 'MIME type `image/png` is not allowed.',
            ],
            'mime_not_allowed_unknown' => [
                'error.mime_not_allowed_unknown', [], 'MIME type is not allowed.',
            ],
            'max_files_reached' => [
                'error.max_files_reached', [], 'Maximum number of files reached.',
            ],
        ];
    }

    /**
     * @param  array<string, scalar>  $params
     */
    #[DataProvider('attentionProvider')]
    public function test_attention_exception_renders_english_and_exposes_getters(
        string $code,
        array $params,
        string $expected,
    ): void {
        $ex = new AttentionException($code, $params);

        self::assertSame($expected, $ex->getMessage());
        self::assertSame($code, $ex->code());
        self::assertSame($params, $ex->params());
    }

    public function test_authorization_exception_delete(): void
    {
        $ex = new AuthorizationException('error.not_authorized_delete');

        self::assertSame('You are not allowed to delete this file.', $ex->getMessage());
        self::assertSame('error.not_authorized_delete', $ex->code());
        self::assertSame([], $ex->params());
    }

    public function test_authorization_exception_restore(): void
    {
        $ex = new AuthorizationException('error.not_authorized_restore');

        self::assertSame('You are not allowed to restore this file.', $ex->getMessage());
        self::assertSame('error.not_authorized_restore', $ex->code());
    }

    public function test_hierarchy_is_preserved(): void
    {
        self::assertInstanceOf(UploadException::class, new AttentionException('error.max_files_reached'));
        self::assertInstanceOf(UploadException::class, new AuthorizationException('error.not_authorized_delete'));
    }
}
