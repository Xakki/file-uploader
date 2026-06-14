<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Protocol;

/**
 * Resolves Upload Protocol message/error codes to localized, interpolated strings
 * against the shared catalogs in protocol/i18n/<locale>.json (see protocol/SPEC.md §5).
 * The same catalogs are reused by every implementation (JS, Go, Python).
 *
 * Total by contract — it NEVER throws: a missing locale file falls back to `en`,
 * a missing code returns the code itself, and malformed JSON is treated as an empty
 * catalog. This makes it safe to call from exception constructors.
 */
final class MessageCatalog
{
    public const FALLBACK_LOCALE = 'en';

    /** @var array<string, array<string, mixed>> locale => (code => template) */
    private static array $cache = [];

    /**
     * @param  array<string, scalar>  $params
     */
    public static function resolve(string $code, array $params = [], ?string $locale = null): string
    {
        $locale = ($locale !== null && $locale !== '') ? strtolower($locale) : self::FALLBACK_LOCALE;

        $template = self::lookup($code, $locale);
        if ($template === null && $locale !== self::FALLBACK_LOCALE) {
            $template = self::lookup($code, self::FALLBACK_LOCALE);
        }
        if ($template === null) {
            return $code; // unknown code — defensive, never throw
        }

        if (is_array($template)) {
            $template = self::pluralize($template, $params, $locale);
        }

        return self::interpolate((string) $template, $params);
    }

    /**
     * Clears the in-process catalog cache (test/seam helper).
     */
    public static function flush(): void
    {
        self::$cache = [];
    }

    private static function lookup(string $code, string $locale): mixed
    {
        return self::load($locale)[$code] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(string $locale): array
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $data = [];
        // The locale becomes a filesystem path segment, so allow only a safe
        // charset — a hostile request `locale` must never traverse out of i18n/.
        $safe = preg_replace('/[^a-z0-9_-]/', '', $locale);
        if ($safe !== '' && $safe !== null) {
            $path = __DIR__.'/../../protocol/i18n/'.$safe.'.json';
            if (is_file($path)) {
                $raw = @file_get_contents($path);
                if ($raw !== false) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        /** @var array<string, mixed> $decoded */
                        $data = $decoded;
                    }
                }
            }
        }

        return self::$cache[$locale] = $data;
    }

    /**
     * @param  array<string, mixed>  $forms
     * @param  array<string, scalar>  $params
     */
    private static function pluralize(array $forms, array $params, string $locale): string
    {
        $count = (isset($params['count']) && is_numeric($params['count'])) ? (int) $params['count'] : 0;
        $category = self::pluralCategory($locale, $count);

        if (isset($forms[$category])) {
            return (string) $forms[$category];
        }
        if (isset($forms['other'])) {
            return (string) $forms['other'];
        }

        return '';
    }

    /**
     * CLDR cardinal plural category for an integer count, for the shipped locales
     * (see SPEC §5.3). Unknown locales use the `en` rule.
     */
    private static function pluralCategory(string $locale, int $n): string
    {
        $n = abs($n);
        $i = $n % 10;
        $j = $n % 100;

        switch ($locale) {
            case 'ru':
                // integers fall into one/few/many; `other` is for fractions only
                if ($i === 1 && $j !== 11) {
                    return 'one';
                }
                if ($i >= 2 && $i <= 4 && ! ($j >= 12 && $j <= 14)) {
                    return 'few';
                }

                return 'many';
            case 'sr':
                if ($i === 1 && $j !== 11) {
                    return 'one';
                }
                if ($i >= 2 && $i <= 4 && ! ($j >= 12 && $j <= 14)) {
                    return 'few';
                }

                return 'other';
            case 'pt':
            case 'fr':
                return ($n === 0 || $n === 1) ? 'one' : 'other';
            case 'zh':
                return 'other';
            case 'en':
            case 'de':
            case 'es':
            default:
                return $n === 1 ? 'one' : 'other';
        }
    }

    /**
     * @param  array<string, scalar>  $params
     */
    private static function interpolate(string $template, array $params): string
    {
        if ($params === [] || ! str_contains($template, '{')) {
            return $template;
        }

        $replace = [];
        foreach ($params as $key => $value) {
            $replace['{'.$key.'}'] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return strtr($template, $replace);
    }
}
