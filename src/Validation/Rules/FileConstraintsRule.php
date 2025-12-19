<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;

final class FileConstraintsRule extends Rule
{
    /**
     * @param array<int, string> $accept
     */
    public function __construct(
        private readonly array $accept = [],
        private readonly bool $allowMultiple = false,
        private readonly ?int $minFiles = null,
        private readonly ?int $maxFiles = null,
        private readonly ?int $maxFileSize = null,
        private readonly ?int $maxTotalSize = null,
    ) {
        $this->message = 'The :attribute is not a valid file upload.';
    }

    public function check($value): bool
    {
        if ($this->isEmpty($value)) {
            return true;
        }

        $files = $this->normalizeFiles($value);
        if (null === $files) {
            return false;
        }

        $count = count($files);
        if (null !== $this->minFiles && $count < $this->minFiles) {
            $this->message = "The :attribute must include at least {$this->minFiles} file(s).";

            return false;
        }

        if (null !== $this->maxFiles && $count > $this->maxFiles) {
            $this->message = "The :attribute must include at most {$this->maxFiles} file(s).";

            return false;
        }

        $accept = array_values(array_unique(array_filter(array_map('strtolower', $this->accept))));
        $totalSize = 0;

        foreach ($files as $file) {
            $error = $this->fileError($file);
            if (UPLOAD_ERR_NO_FILE === $error) {
                continue;
            }

            if (0 !== $error) {
                $this->message = 'The :attribute contains a failed upload.';

                return false;
            }

            $size = $this->fileSize($file);
            if (null === $size) {
                $this->message = 'The :attribute contains a file with unknown size.';

                return false;
            }

            if (null !== $this->maxFileSize && $size > $this->maxFileSize) {
                $this->message = "The :attribute contains a file that exceeds {$this->maxFileSize} bytes.";

                return false;
            }

            $totalSize += $size;

            if ([] !== $accept) {
                $mime = $this->fileMimeType($file);
                if (null === $mime) {
                    $this->message = 'The :attribute contains a file with unknown type.';

                    return false;
                }

                if ( ! in_array(strtolower($mime), $accept, true)) {
                    $this->message = 'The :attribute contains a file with an invalid type.';

                    return false;
                }
            }
        }

        if (null !== $this->maxTotalSize && $totalSize > $this->maxTotalSize) {
            $this->message = "The :attribute total size must not exceed {$this->maxTotalSize} bytes.";

            return false;
        }

        return true;
    }

    /**
     * @return array<int, mixed>|null
     */
    private function normalizeFiles(mixed $value): ?array
    {
        if ($this->isFileLike($value)) {
            return [$value];
        }

        if ( ! is_array($value)) {
            return null;
        }

        if ($this->isFileLikeArray($value)) {
            return [$value];
        }

        if ( ! $this->isList($value)) {
            return null;
        }

        if ( ! $this->allowMultiple && count($value) > 1) {
            $this->message = 'The :attribute does not allow multiple files.';

            return null;
        }

        $files = [];
        foreach ($value as $item) {
            if ($this->isEmpty($item)) {
                continue;
            }

            if ( ! $this->isFileLike($item) && ! $this->isFileLikeArray($item)) {
                return null;
            }

            $files[] = $item;
        }

        return $files;
    }

    private function isFileLike(mixed $value): bool
    {
        if (is_array($value)) {
            return $this->isFileLikeArray($value);
        }

        if ( ! is_object($value)) {
            return false;
        }

        return method_exists($value, 'getSize')
            && (method_exists($value, 'getMimeType') || method_exists($value, 'getClientMimeType'));
    }

    private function isFileLikeArray(array $value): bool
    {
        return array_key_exists('size', $value) && array_key_exists('type', $value);
    }

    private function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function fileSize(mixed $file): ?int
    {
        if (is_array($file)) {
            $size = $file['size'] ?? null;

            return is_numeric($size) ? (int) $size : null;
        }

        if (is_object($file) && method_exists($file, 'getSize')) {
            $size = $file->getSize();

            return is_numeric($size) ? (int) $size : null;
        }

        return null;
    }

    private function fileMimeType(mixed $file): ?string
    {
        if (is_array($file)) {
            $type = $file['type'] ?? null;

            return is_string($type) && '' !== trim($type) ? $type : null;
        }

        if (is_object($file)) {
            if (method_exists($file, 'getMimeType')) {
                $type = $file->getMimeType();
                if (is_string($type) && '' !== trim($type)) {
                    return $type;
                }
            }

            if (method_exists($file, 'getClientMimeType')) {
                $type = $file->getClientMimeType();
                if (is_string($type) && '' !== trim($type)) {
                    return $type;
                }
            }
        }

        return null;
    }

    private function fileError(mixed $file): int
    {
        if (is_array($file) && array_key_exists('error', $file) && is_numeric($file['error'])) {
            return (int) $file['error'];
        }

        return 0;
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_string($value)) {
            return '' === trim($value);
        }

        return null === $value || [] === $value;
    }
}

