<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     * @return array<string, string>
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleSet) {
            $rulesList = explode('|', $ruleSet);
            $value = $data[$field] ?? null;

            foreach ($rulesList as $rule) {
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $errors[$field] = 'required';
                    break;
                }

                if ($value === null || $value === '') {
                    continue;
                }

                if ($rule === 'email' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = 'invalid_email';
                    break;
                }

                if ($rule === 'int' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $errors[$field] = 'invalid_integer';
                    break;
                }

                if ($rule === 'numeric' && !is_numeric($value)) {
                    $errors[$field] = 'invalid_number';
                    break;
                }

                if ($rule === 'url' && !filter_var((string) $value, FILTER_VALIDATE_URL)) {
                    $errors[$field] = 'invalid_url';
                    break;
                }

                if (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (mb_strlen((string) $value, 'UTF-8') < $min) {
                        $errors[$field] = 'min_' . $min;
                        break;
                    }
                }

                if (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (mb_strlen((string) $value, 'UTF-8') > $max) {
                        $errors[$field] = 'max_' . $max;
                        break;
                    }
                }
            }
        }

        return $errors;
    }
}
