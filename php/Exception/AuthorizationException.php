<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Exception;

/**
 * The current user is not allowed to manage (delete/restore) a file.
 * Bindings map this to HTTP 403.
 */
class AuthorizationException extends CodedUploadException {}
