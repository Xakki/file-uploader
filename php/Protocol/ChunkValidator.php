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
     * @param  ?string  $locale  Locale for rendering messages via the catalog; null → `en` (see SPEC §5).
     * @return array<string, string[]> Field name => error messages; empty array means valid.
     */
    public static function validate(array $fields, array $allowedLocales = [], ?string $locale = null): array
    {
        $errors = [];

        self::requireString($fields, 'fileName', 255, $errors, $locale);
        self::requireString($fields, 'mimeType', 150, $errors, $locale);

        if (self::requireString($fields, 'uploadId', 60, $errors, $locale)
            && ! preg_match(self::UPLOAD_ID_PATTERN, (string) $fields['uploadId'])) {
            $errors['uploadId'][] = MessageCatalog::resolve('validation.uploadid_invalid', [], $locale);
        }

        self::requireInteger($fields, 'chunkIndex', 0, $errors, $locale);
        self::requireInteger($fields, 'totalChunks', 1, $errors, $locale);
        self::requireInteger($fields, 'fileSize', 1, $errors, $locale);
        self::requireInteger($fields, 'fileLastModified', 0, $errors, $locale);

        if (self::present($fields, 'fileHash')) {
            $hash = $fields['fileHash'];
            if (! is_string($hash) || mb_strlen($hash) > 128) {
                $errors['fileHash'][] = MessageCatalog::resolve('validation.filehash_invalid', [], $locale);
            }
        }

        if ($allowedLocales !== [] && self::present($fields, 'locale')
            && ! in_array((string) $fields['locale'], $allowedLocales, true)) {
            $errors['locale'][] = MessageCatalog::resolve('validation.locale_invalid', [], $locale);
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
    private static function requireString(array $fields, string $key, int $max, array &$errors, ?string $locale = null): bool
    {
        if (! self::present($fields, $key)) {
            $errors[$key][] = MessageCatalog::resolve('validation.field_required', ['field' => $key], $locale);

            return false;
        }
        $value = $fields[$key];
        if (! is_string($value)) {
            $errors[$key][] = MessageCatalog::resolve('validation.field_string', ['field' => $key], $locale);

            return false;
        }
        if (mb_strlen($value) > $max) {
            $errors[$key][] = MessageCatalog::resolve('validation.field_max_chars', ['field' => $key, 'max' => $max], $locale);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, string[]>  $errors
     */
    private static function requireInteger(array $fields, string $key, int $min, array &$errors, ?string $locale = null): void
    {
        if (! self::present($fields, $key)) {
            $errors[$key][] = MessageCatalog::resolve('validation.field_required', ['field' => $key], $locale);

            return;
        }
        $value = $fields[$key];
        if (is_int($value)) {
            $int = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            $int = (int) $value;
        } else {
            $errors[$key][] = MessageCatalog::resolve('validation.field_integer', ['field' => $key], $locale);

            return;
        }
        if ($int < $min) {
            $errors[$key][] = MessageCatalog::resolve('validation.field_min', ['field' => $key, 'min' => $min], $locale);
        }
    }
}
