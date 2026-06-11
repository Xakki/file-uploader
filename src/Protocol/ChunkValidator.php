<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Protocol;

use Xakki\FileUploader\FileUploader;

/**
 * Validates the scalar multipart fields of a chunk request against Upload Protocol v1
 * (see protocol/SPEC.md §2). Bindings that lack a native request validator (Symfony,
 * custom/PSR) use this; it mirrors the Laravel FormRequest rules verbatim so a single
 * conformance fixture passes for every binding.
 *
 * Size limits and the extension/MIME allow-list are intentionally NOT checked here —
 * {@see FileUploader::guardFile()} owns those.
 */
final class ChunkValidator
{
    public const UPLOAD_ID_PATTERN = '/^upload-[0-9]{13}-[a-z0-9]{8}$/';

    /**
     * @param  array<string, mixed>  $fields  Raw scalar fields from the request body (ints or numeric strings).
     * @param  string[]  $allowedLocales  Permitted locales; empty accepts any.
     * @return array<string, string[]> Field name => error messages; empty array means valid.
     */
    public static function validate(array $fields, array $allowedLocales = []): array
    {
        $errors = [];

        self::requireString($fields, 'fileName', 255, $errors);
        self::requireString($fields, 'mimeType', 150, $errors);

        if (self::requireString($fields, 'uploadId', 60, $errors)
            && ! preg_match(self::UPLOAD_ID_PATTERN, (string) $fields['uploadId'])) {
            $errors['uploadId'][] = 'The uploadId format is invalid.';
        }

        self::requireInteger($fields, 'chunkIndex', 0, $errors);
        self::requireInteger($fields, 'totalChunks', 1, $errors);
        self::requireInteger($fields, 'fileSize', 1, $errors);
        self::requireInteger($fields, 'fileLastModified', 0, $errors);

        if (self::present($fields, 'fileHash')) {
            $hash = $fields['fileHash'];
            if (! is_string($hash) || mb_strlen($hash) > 128) {
                $errors['fileHash'][] = 'The fileHash field is invalid.';
            }
        }

        if ($allowedLocales !== [] && self::present($fields, 'locale')
            && ! in_array((string) $fields['locale'], $allowedLocales, true)) {
            $errors['locale'][] = 'The selected locale is invalid.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private static function present(array $fields, string $key): bool
    {
        if (! array_key_exists($key, $fields)) {
            return false;
        }
        $value = $fields[$key];

        return ! ($value === null || $value === '');
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, string[]>  $errors
     * @return bool true when present and well-formed
     */
    private static function requireString(array $fields, string $key, int $max, array &$errors): bool
    {
        if (! self::present($fields, $key)) {
            $errors[$key][] = "The {$key} field is required.";

            return false;
        }
        $value = $fields[$key];
        if (! is_string($value)) {
            $errors[$key][] = "The {$key} field must be a string.";

            return false;
        }
        if (mb_strlen($value) > $max) {
            $errors[$key][] = "The {$key} field must not exceed {$max} characters.";

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, string[]>  $errors
     */
    private static function requireInteger(array $fields, string $key, int $min, array &$errors): void
    {
        if (! self::present($fields, $key)) {
            $errors[$key][] = "The {$key} field is required.";

            return;
        }
        $value = $fields[$key];
        if (is_int($value)) {
            $int = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            $int = (int) $value;
        } else {
            $errors[$key][] = "The {$key} field must be an integer.";

            return;
        }
        if ($int < $min) {
            $errors[$key][] = "The {$key} field must be at least {$min}.";
        }
    }
}
