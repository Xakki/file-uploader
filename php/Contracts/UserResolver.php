<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Contracts;

/**
 * Identity seam: who the current user is, and whether they hold any of a set of roles.
 * A binding adapts its auth system (Laravel Auth, Symfony Security, custom) to this.
 */
interface UserResolver
{
    /**
     * Current user identifier as a string, or null when unauthenticated.
     */
    public function id(): ?string;

    /**
     * @param  string[]  $roles
     */
    public function hasAnyRole(array $roles): bool;
}
