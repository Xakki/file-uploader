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
     * @param  array<string, scalar>  $params  Placeholder values used to render $message (see SPEC §5).
     * @return array{success: true, data: mixed, message: string, code?: string, params?: array<string, scalar>}
     */
    public static function success(mixed $data, string $message, ?string $code = null, array $params = []): array
    {
        $response = [
            'success' => true,
            'data' => $data,
            'message' => $message,
        ];

        if ($code !== null) {
            $response['code'] = $code;
            if ($params !== []) {
                $response['params'] = $params;
            }
        }

        return $response;
    }

    /**
     * @param  array<string, string[]>  $errors
     * @param  array<string, scalar>  $params  Placeholder values used to render $message (see SPEC §5).
     * @return array{success: false, message: string, code?: string, params?: array<string, scalar>, errors?: array<string, string[]>}
     */
    public static function error(string $message, array $errors = [], ?string $code = null, array $params = []): array
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($code !== null) {
            $response['code'] = $code;
            if ($params !== []) {
                $response['params'] = $params;
            }
        }

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return $response;
    }
}
