<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Tests;

use PHPUnit\Framework\TestCase;
use Xakki\FileUploader\Protocol\ChunkValidator;

class ChunkValidatorTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function validFields(): array
    {
        return [
            'chunkIndex' => '0',
            'totalChunks' => '2',
            'fileSize' => '1048576',
            'uploadId' => 'upload-1700000000000-abcd1234',
            'fileName' => 'doc.pdf',
            'mimeType' => 'application/pdf',
            'fileLastModified' => '1700000000000',
        ];
    }

    public function test_valid_fields_produce_no_errors(): void
    {
        self::assertSame([], ChunkValidator::validate($this->validFields()));
    }

    public function test_integer_zero_chunk_index_is_valid(): void
    {
        $fields = $this->validFields();
        $fields['chunkIndex'] = 0;
        self::assertSame([], ChunkValidator::validate($fields));
    }

    public function test_invalid_upload_id_is_reported(): void
    {
        $fields = $this->validFields();
        $fields['uploadId'] = 'not-a-valid-upload-id';

        $errors = ChunkValidator::validate($fields);

        self::assertArrayHasKey('uploadId', $errors);
        self::assertCount(1, $errors);
    }

    public function test_missing_required_field_is_reported(): void
    {
        $fields = $this->validFields();
        unset($fields['totalChunks']);

        $errors = ChunkValidator::validate($fields);

        self::assertArrayHasKey('totalChunks', $errors);
    }

    public function test_total_chunks_below_minimum_is_reported(): void
    {
        $fields = $this->validFields();
        $fields['totalChunks'] = '0';

        self::assertArrayHasKey('totalChunks', ChunkValidator::validate($fields));
    }

    public function test_non_integer_field_is_reported(): void
    {
        $fields = $this->validFields();
        $fields['fileSize'] = 'abc';

        self::assertArrayHasKey('fileSize', ChunkValidator::validate($fields));
    }

    public function test_optional_hash_and_locale_are_validated_when_present(): void
    {
        $fields = $this->validFields();
        $fields['fileHash'] = str_repeat('a', 200);
        $fields['locale'] = 'de';

        $errors = ChunkValidator::validate($fields, ['en', 'ru']);

        self::assertArrayHasKey('fileHash', $errors);
        self::assertArrayHasKey('locale', $errors);
    }

    public function test_locale_ignored_when_no_allow_list_given(): void
    {
        $fields = $this->validFields();
        $fields['locale'] = 'de';

        self::assertSame([], ChunkValidator::validate($fields));
    }
}
