<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Tests;

use PHPUnit\Framework\TestCase;
use Xakki\FileUploader\Protocol\ResponseFactory;

class ResponseFactoryTest extends TestCase
{
    public function test_success_envelope_shape(): void
    {
        self::assertSame(
            ['success' => true, 'data' => ['id' => 1], 'message' => 'ok'],
            ResponseFactory::success(['id' => 1], 'ok'),
        );
    }

    public function test_error_without_field_errors_omits_errors_key(): void
    {
        $response = ResponseFactory::error('bad');

        self::assertSame(['success' => false, 'message' => 'bad'], $response);
        self::assertArrayNotHasKey('errors', $response);
    }

    public function test_error_with_field_errors_includes_errors_key(): void
    {
        $errors = ['file' => ['required']];

        self::assertSame(
            ['success' => false, 'message' => 'bad', 'errors' => $errors],
            ResponseFactory::error('bad', $errors),
        );
    }
}
