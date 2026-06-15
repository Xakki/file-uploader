<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Auth;

use Xakki\FileUploader\Contracts\UserResolver;

/**
 * Anonymous resolver: no current user, no roles. Useful for custom/standalone
 * projects that do not gate uploads behind authentication.
 */
final class NullUserResolver implements UserResolver
{
    public function id(): ?string
    {
        return null;
    }

    public function hasAnyRole(array $roles): bool
    {
        return false;
    }
}
