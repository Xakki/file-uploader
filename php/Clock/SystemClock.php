<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Default PSR-20 clock. Bindings that need test-controllable time (e.g. Laravel's
 * Date facade) supply their own ClockInterface instead.
 */
final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
