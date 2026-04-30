<?php
declare(strict_types=1);

namespace Core;

class Validator
{
    /** @var array */
    private $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $ruleList = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                $params = [];
                if (strpos($rule, ':') !== false) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $methodName = 'rule' . ucfirst($rule);
                if (method_exists($this, $methodName)) {
                    $this->$methodName($field, $value, $params);
                }
            }
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return string|null
     */
    public function firstError()
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param array $params
     */
    private function ruleRequired(string $field, $value, array $params): void
    {
        if ($value === null || $value === '' || $value === []) {
            $this->addError($field, "Le champ {$field} est obligatoire");
        }
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param array $params
     */
    private function ruleEmail(string $field, $value, array $params): void
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "L'adresse email n'est pas valide");
        }
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param array $params
     */
    private function ruleMin(string $field, $value, array $params): void
    {
        $min = (int) ($params[0] ?? 0);
        if ($value && strlen((string) $value) < $min) {
            $this->addError($field, "Le champ {$field} doit contenir au moins {$min} caractères");
        }
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param array $params
     */
    private function ruleMax(string $field, $value, array $params): void
    {
        $max = (int) ($params[0] ?? 0);
        if ($value && strlen((string) $value) > $max) {
            $this->addError($field, "Le champ {$field} ne doit pas dépasser {$max} caractères");
        }
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param array $params
     */
    private function ruleIn(string $field, $value, array $params): void
    {
        // Ne vérifie que si la valeur n'est pas vide (required doit être vérifié avant)
        // Mais si une valeur est fournie, elle doit être dans la liste
        if ($value !== null && $value !== '' && !in_array($value, $params, true)) {
            $this->addError($field, "La valeur du champ {$field} n'est pas autorisée");
        }
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param array $params
     */
    private function ruleConfirmed(string $field, $value, array $params): void
    {
        $confirmField = $field . '_confirmation';
        $confirmValue = $_POST[$confirmField] ?? null;
        if ($value !== $confirmValue) {
            $this->addError($field, "Les champs ne correspondent pas");
        }
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param array $params
     */
    private function ruleAlpha(string $field, $value, array $params): void
    {
        if ($value && !preg_match('/^[\p{L}\s\-]+$/u', (string) $value)) {
            $this->addError($field, "Le champ {$field} ne doit contenir que des lettres");
        }
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param array $params
     */
    private function ruleNumeric(string $field, $value, array $params): void
    {
        if ($value && !is_numeric($value)) {
            $this->addError($field, "Le champ {$field} doit être numérique");
        }
    }
}
