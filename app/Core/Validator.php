<?php
declare(strict_types=1);

namespace Core;

class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            foreach (explode('|', $ruleString) as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }
                $method = 'rule' . ucfirst($rule);
                if (method_exists($this, $method)) {
                    $this->$method($field, $value, $params);
                }
            }
        }

        return empty($this->errors);
    }

    public function getErrors(): array { return $this->errors; }

    public function firstError(): ?string
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    private function ruleRequired(string $field, mixed $value, array $params): void
    {
        if ($value === null || $value === '' || $value === []) {
            $this->addError($field, "Ce champ est obligatoire");
        }
    }

    private function ruleEmail(string $field, mixed $value, array $params): void
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "Adresse email invalide");
        }
    }

    private function ruleMin(string $field, mixed $value, array $params): void
    {
        $min = (int) ($params[0] ?? 0);
        if ($value !== null && $value !== '' && strlen((string) $value) < $min) {
            $this->addError($field, "Minimum {$min} caractères requis");
        }
    }

    private function ruleMax(string $field, mixed $value, array $params): void
    {
        $max = (int) ($params[0] ?? 255);
        if ($value && strlen((string) $value) > $max) {
            $this->addError($field, "Maximum {$max} caractères autorisés");
        }
    }

    private function ruleIn(string $field, mixed $value, array $params): void
    {
        if ($value !== null && $value !== '' && !in_array($value, $params, true)) {
            $this->addError($field, "Valeur non autorisée");
        }
    }

    private function ruleConfirmed(string $field, mixed $value, array $params): void
    {
        $confirmValue = $_POST[$field . '_confirmation'] ?? null;
        if ($value !== $confirmValue) {
            $this->addError($field, "Les mots de passe ne correspondent pas");
        }
    }

    private function ruleAlpha(string $field, mixed $value, array $params): void
    {
        if ($value && !preg_match('/^[\p{L}\s\-]+$/u', (string) $value)) {
            $this->addError($field, "Ce champ ne doit contenir que des lettres");
        }
    }

    private function ruleNumeric(string $field, mixed $value, array $params): void
    {
        if ($value && !is_numeric($value)) {
            $this->addError($field, "Ce champ doit être numérique");
        }
    }
}
