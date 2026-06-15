<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Exception;

use Throwable;
use Xakki\FileUploader\Protocol\MessageCatalog;

/**
 * An upload error identified by a stable message code + params (see protocol/SPEC.md §5).
 *
 * `getMessage()` returns the `en`-rendered text (the verbatim default), so existing
 * callers keep working; `code()` / `params()` let bindings re-localize the response
 * in the request locale via {@see MessageCatalog}.
 */
abstract class CodedUploadException extends UploadException
{
    /**
     * @param  array<string, scalar>  $params
     */
    public function __construct(
        private readonly string $messageCode,
        private readonly array $params = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            MessageCatalog::resolve($messageCode, $params, MessageCatalog::FALLBACK_LOCALE),
            0,
            $previous,
        );
    }

    /**
     * The stable message code (e.g. `error.max_files_reached`). Distinct from the
     * inherited integer {@see Throwable::getCode()}.
     */
    public function code(): string
    {
        return $this->messageCode;
    }

    /**
     * @return array<string, scalar>
     */
    public function params(): array
    {
        return $this->params;
    }
}
