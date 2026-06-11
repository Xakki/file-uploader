<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Protocol;

/**
 * Builds the Upload Protocol v1 response envelope (see protocol/SPEC.md §3).
 * Framework bindings json-encode the returned arrays; this keeps the wire shape
 * in one place across Laravel, Symfony and standalone usage.
 */
final class ResponseFactory
{
    /**
     * @return array{success: true, data: mixed, message: string}
     */
    public static function success(mixed $data, string $message): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, string[]>  $errors
     * @return array{success: false, message: string, errors?: array<string, string[]>}
     */
    public static function error(string $message, array $errors = []): array
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return $response;
    }
}
