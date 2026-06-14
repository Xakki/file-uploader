<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Tests;

use PHPUnit\Framework\TestCase;
use Xakki\FileUploader\Protocol\ChunkValidator;
use Xakki\FileUploader\Protocol\MessageCatalog;

class ChunkValidatorI18nTest extends TestCase
{
    protected function setUp(): void
    {
        MessageCatalog::flush();
    }

    public function test_missing_field_renders_english_by_default(): void
    {
        $errors = ChunkValidator::validate([]);

        self::assertArrayHasKey('fileName', $errors);
        self::assertContains('The fileName field is required.', $errors['fileName']);
    }

    public function test_missing_field_renders_russian_with_locale(): void
    {
        $errors = ChunkValidator::validate([], [], 'ru');

        self::assertArrayHasKey('fileName', $errors);
        self::assertContains('Поле fileName обязательно для заполнения.', $errors['fileName']);
    }

    public function test_invalid_upload_id_renders_russian_with_locale(): void
    {
        $errors = ChunkValidator::validate(['uploadId' => 'not-a-valid-id'], [], 'ru');

        self::assertArrayHasKey('uploadId', $errors);
        self::assertContains('Неверный формат uploadId.', $errors['uploadId']);
    }
}
