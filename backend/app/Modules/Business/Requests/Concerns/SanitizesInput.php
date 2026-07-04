<?php

namespace App\Modules\Business\Requests\Concerns;

trait SanitizesInput
{
    protected function sanitizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim(strip_tags($value));
    }

    protected function sanitizeStrings(array $fields): void
    {
        $merged = [];

        foreach ($fields as $field) {
            if ($this->has($field)) {
                $merged[$field] = $this->sanitizeString($this->input($field));
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }
}