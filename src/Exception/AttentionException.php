<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Exception;

/**
 * A user-correctable problem with the upload (size, extension/MIME not allowed,
 * chunk could not be persisted). Bindings map this to HTTP 422.
 */
class AttentionException extends CodedUploadException {}
