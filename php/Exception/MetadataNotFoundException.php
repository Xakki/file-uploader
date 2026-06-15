<?php

declare(strict_types=1);

namespace Xakki\FileUploader\Exception;

/**
 * Metadata file is missing or malformed. Often used for control flow internally.
 */
class MetadataNotFoundException extends UploadException {}
