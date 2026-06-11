<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Xakki\FileUploader\Auth\NullUserResolver;
use Xakki\FileUploader\Clock\SystemClock;

class CoreSeamsTest extends TestCase
{
    public function test_system_clock_returns_immutable_current_time(): void
    {
        $before = new DateTimeImmutable;
        $now = (new SystemClock)->now();
        $after = new DateTimeImmutable;

        self::assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
    }

    public function test_null_user_resolver_is_anonymous_with_no_roles(): void
    {
        $resolver = new NullUserResolver;

        self::assertNull($resolver->id());
        self::assertFalse($resolver->hasAnyRole([]));
        self::assertFalse($resolver->hasAnyRole(['admin', 'editor']));
    }
}
