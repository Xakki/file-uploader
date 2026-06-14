<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xakki\FileUploader\Protocol\MessageCatalog;

class MessageCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        MessageCatalog::flush();
    }

    /**
     * Reads a catalog entry straight from the shipped JSON so plural-form
     * expectations stay correct if the wording is tweaked.
     *
     * @return string|array<string, string>
     */
    private function catalogEntry(string $locale, string $code): string|array
    {
        $path = __DIR__.'/../protocol/i18n/'.$locale.'.json';
        self::assertFileExists($path);
        $raw = file_get_contents($path);
        self::assertNotFalse($raw);
        $decoded = json_decode($raw, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey($code, $decoded);

        /** @var string|array<string, string> */
        return $decoded[$code];
    }

    private function pluralForm(string $locale, string $code, string $category, int $count): string
    {
        $entry = $this->catalogEntry($locale, $code);
        self::assertIsArray($entry);
        self::assertArrayHasKey($category, $entry, "Missing `$category` form for $code in $locale");

        return str_replace('{count}', (string) $count, $entry[$category]);
    }

    public function test_default_locale_resolves_to_english(): void
    {
        self::assertSame(
            'Maximum number of files reached.',
            MessageCatalog::resolve('error.max_files_reached'),
        );
    }

    public function test_explicit_russian_locale(): void
    {
        self::assertSame(
            'Достигнуто максимальное число файлов.',
            MessageCatalog::resolve('error.max_files_reached', [], 'ru'),
        );
    }

    public function test_single_param_interpolation(): void
    {
        self::assertSame(
            'Extension `exe` is not allowed.',
            MessageCatalog::resolve('error.extension_not_allowed', ['ext' => 'exe']),
        );
    }

    public function test_two_param_interpolation(): void
    {
        self::assertSame(
            'Extension `exe` is not allowed for MIME type `image/png`.',
            MessageCatalog::resolve('error.extension_mime_mismatch', ['ext' => 'exe', 'mime' => 'image/png']),
        );
    }

    public function test_unknown_code_returns_the_code(): void
    {
        self::assertSame('no.such.code', MessageCatalog::resolve('no.such.code'));
    }

    public function test_unknown_locale_falls_back_to_english(): void
    {
        self::assertSame(
            'Maximum number of files reached.',
            MessageCatalog::resolve('error.max_files_reached', [], 'xx'),
        );
    }

    public function test_english_plurals(): void
    {
        self::assertSame(
            'Removed 1 file from trash.',
            MessageCatalog::resolve('message.cleanup_done', ['count' => 1]),
        );
        self::assertSame(
            'Removed 2 files from trash.',
            MessageCatalog::resolve('message.cleanup_done', ['count' => 2]),
        );
        self::assertSame(
            'Removed 0 files from trash.',
            MessageCatalog::resolve('message.cleanup_done', ['count' => 0]),
        );
    }

    public function test_russian_plurals(): void
    {
        self::assertSame(
            'Удалён 1 файл из корзины.',
            MessageCatalog::resolve('message.cleanup_done', ['count' => 1], 'ru'),
        );
        self::assertSame(
            'Удалено 2 файла из корзины.',
            MessageCatalog::resolve('message.cleanup_done', ['count' => 2], 'ru'),
        );
        self::assertSame(
            'Удалено 5 файлов из корзины.',
            MessageCatalog::resolve('message.cleanup_done', ['count' => 5], 'ru'),
        );
        // 11 -> many (special-cased: %100 in 12..14 -> many; 11 also -> many)
        self::assertSame(
            'Удалено 11 файлов из корзины.',
            MessageCatalog::resolve('message.cleanup_done', ['count' => 11], 'ru'),
        );
        // 21 -> one (%10==1 && %100!=11)
        self::assertSame(
            'Удалён 21 файл из корзины.',
            MessageCatalog::resolve('message.cleanup_done', ['count' => 21], 'ru'),
        );
        // 22 -> few (%10 in 2..4 && %100 not in 12..14)
        self::assertSame(
            $this->pluralForm('ru', 'message.cleanup_done', 'few', 22),
            MessageCatalog::resolve('message.cleanup_done', ['count' => 22], 'ru'),
        );
    }

    public function test_serbian_plurals(): void
    {
        // 1 -> one, 2 -> few, 5 -> other; derive exact wording from sr.json
        self::assertSame(
            $this->pluralForm('sr', 'message.cleanup_done', 'one', 1),
            MessageCatalog::resolve('message.cleanup_done', ['count' => 1], 'sr'),
        );
        self::assertSame(
            $this->pluralForm('sr', 'message.cleanup_done', 'few', 2),
            MessageCatalog::resolve('message.cleanup_done', ['count' => 2], 'sr'),
        );
        self::assertSame(
            $this->pluralForm('sr', 'message.cleanup_done', 'other', 5),
            MessageCatalog::resolve('message.cleanup_done', ['count' => 5], 'sr'),
        );

        // Distinct categories — guard against all forms collapsing to one string.
        self::assertNotSame(
            MessageCatalog::resolve('message.cleanup_done', ['count' => 1], 'sr'),
            MessageCatalog::resolve('message.cleanup_done', ['count' => 2], 'sr'),
        );
        self::assertNotSame(
            MessageCatalog::resolve('message.cleanup_done', ['count' => 2], 'sr'),
            MessageCatalog::resolve('message.cleanup_done', ['count' => 5], 'sr'),
        );
    }

    public function test_chinese_plain_string_interpolates_any_count(): void
    {
        $entry = $this->catalogEntry('zh', 'message.cleanup_done');
        self::assertIsString($entry);

        self::assertSame(
            str_replace('{count}', '3', $entry),
            MessageCatalog::resolve('message.cleanup_done', ['count' => 3], 'zh'),
        );
        // Sanity: it really did interpolate.
        self::assertSame(
            '已从回收站删除 3 个文件。',
            MessageCatalog::resolve('message.cleanup_done', ['count' => 3], 'zh'),
        );
    }

    public function test_french_plurals(): void
    {
        // 0 -> one, 1 -> one, 2 -> other
        self::assertSame(
            $this->pluralForm('fr', 'message.cleanup_done', 'one', 0),
            MessageCatalog::resolve('message.cleanup_done', ['count' => 0], 'fr'),
        );
        self::assertSame(
            $this->pluralForm('fr', 'message.cleanup_done', 'one', 1),
            MessageCatalog::resolve('message.cleanup_done', ['count' => 1], 'fr'),
        );
        self::assertSame(
            $this->pluralForm('fr', 'message.cleanup_done', 'other', 2),
            MessageCatalog::resolve('message.cleanup_done', ['count' => 2], 'fr'),
        );
    }

    public function test_portuguese_plurals(): void
    {
        // 0 -> one, 1 -> one, 2 -> other
        self::assertSame(
            $this->pluralForm('pt', 'message.cleanup_done', 'one', 0),
            MessageCatalog::resolve('message.cleanup_done', ['count' => 0], 'pt'),
        );
        self::assertSame(
            $this->pluralForm('pt', 'message.cleanup_done', 'one', 1),
            MessageCatalog::resolve('message.cleanup_done', ['count' => 1], 'pt'),
        );
        self::assertSame(
            $this->pluralForm('pt', 'message.cleanup_done', 'other', 2),
            MessageCatalog::resolve('message.cleanup_done', ['count' => 2], 'pt'),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function localeProvider(): array
    {
        return [
            'en' => ['en'],
            'ru' => ['ru'],
            'es' => ['es'],
            'pt' => ['pt'],
            'zh' => ['zh'],
            'fr' => ['fr'],
            'de' => ['de'],
            'sr' => ['sr'],
        ];
    }

    #[DataProvider('localeProvider')]
    public function test_every_locale_loads_and_resolves(string $locale): void
    {
        $resolved = MessageCatalog::resolve('error.max_files_reached', [], $locale);
        self::assertNotSame('', $resolved);
        self::assertNotSame('error.max_files_reached', $resolved, "Locale `$locale` did not resolve the code");
    }

    /**
     * A hostile request `locale` must not traverse out of protocol/i18n/ — it is
     * sanitized to a safe charset, so a traversal attempt resolves like an unknown
     * locale (falls back to the `en` catalog), never reading an arbitrary file.
     */
    #[DataProvider('traversalLocaleProvider')]
    public function test_locale_path_traversal_is_neutralized(string $hostileLocale): void
    {
        self::assertSame(
            'Maximum number of files reached.',
            MessageCatalog::resolve('error.max_files_reached', [], $hostileLocale),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function traversalLocaleProvider(): array
    {
        return [
            'dot-dot-slash' => ['../../../../etc/passwd'],
            'absolute' => ['/etc/hosts'],
            'null-byte' => ["en\0"],
            'encoded' => ['..%2f..%2fen'],
        ];
    }
}
