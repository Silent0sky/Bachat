<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private array $errors = [];

    /**
     * Rules: 'required', 'email', 'numeric', 'int', 'date', 'bool',
     *        'min:X' / 'max:X' (numeric compare if value is numeric, else length),
     *        'in:a,b,c', 'same:otherfield'
     */
    public function __construct(private array $data, private array $rules)
    {
        foreach ($rules as $field => $ruleStr) {
            $this->checkField((string) $field, $ruleStr);
        }
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    private function checkField(string $field, string $ruleStr): void
    {
        $raw   = $this->data[$field] ?? null;
        $value = is_string($raw) ? trim($raw) : $raw;
        $label = ucwords(str_replace(['_', '-'], ' ', $field));

        foreach (explode('|', $ruleStr) as $rule) {
            $rule = trim($rule);
            if ($rule === '') {
                continue;
            }
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);

            switch ($name) {
                case 'required':
                    if ($value === null || $value === '' || $value === []) {
                        $this->errors[$field][] = t('validation.required', ['field' => $label]);
                    }
                    break;

                case 'email':
                    if ($value !== null && $value !== '' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                        $this->errors[$field][] = t('validation.email', ['field' => $label]);
                    }
                    break;

                case 'numeric':
                    if ($value !== null && $value !== '' && !is_numeric($value)) {
                        $this->errors[$field][] = t('validation.numeric', ['field' => $label]);
                    }
                    break;

                case 'int':
                    if ($value !== null && $value !== '' && filter_var((string) $value, FILTER_VALIDATE_INT) === false) {
                        $this->errors[$field][] = t('validation.int', ['field' => $label]);
                    }
                    break;

                case 'date':
                    if ($value !== null && $value !== '' && strtotime((string) $value) === false) {
                        $this->errors[$field][] = t('validation.date', ['field' => $label]);
                    }
                    break;

                case 'min':
                    if ($value === null || $value === '') {
                        break;
                    }
                    if (is_numeric($value)) {
                        if ((float) $value < (float) $arg) {
                            $this->errors[$field][] = t('validation.min_num', ['field' => $label, 'value' => $arg]);
                        }
                    } elseif (mb_strlen((string) $value) < (int) $arg) {
                        $this->errors[$field][] = t('validation.min_len', ['field' => $label, 'value' => $arg]);
                    }
                    break;

                case 'max':
                    if ($value === null || $value === '') {
                        break;
                    }
                    if (is_numeric($value)) {
                        if ((float) $value > (float) $arg) {
                            $this->errors[$field][] = t('validation.max_num', ['field' => $label, 'value' => $arg]);
                        }
                    } elseif (mb_strlen((string) $value) > (int) $arg) {
                        $this->errors[$field][] = t('validation.max_len', ['field' => $label, 'value' => $arg]);
                    }
                    break;

                case 'in':
                    $opts = explode(',', (string) $arg);
                    if ($value !== null && $value !== '' && !in_array((string) $value, $opts, true)) {
                        $this->errors[$field][] = t('validation.in', ['field' => $label]);
                    }
                    break;

                case 'same':
                    if (($this->data[$arg] ?? null) !== $value) {
                        $this->errors[$field][] = t('validation.same', ['field' => $label]);
                    }
                    break;
            }
        }
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        $flat = [];
        foreach ($this->errors as $field => $msgs) {
            $flat[$field] = implode(' ', $msgs);
        }
        return $flat;
    }

    public function first(): ?string
    {
        foreach ($this->errors as $msgs) {
            foreach ((array) $msgs as $m) {
                return $m;
            }
        }
        return null;
    }
}
