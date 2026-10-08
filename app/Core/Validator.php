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

    /**
     * Longueur minimale, en CARACTÈRES.
     *
     * `strlen` compte des octets. En UTF-8 un caractère accentué en pèse deux,
     * un idéogramme trois : « éàüñ » faisait huit octets et satisfaisait donc
     * `min:8`, alors que le mot de passe ne compte que quatre caractères. La
     * longueur minimale des mots de passe était contournable — il suffisait
     * d'accentuer. `mb_strlen` mesure ce que l'utilisateur a réellement tapé.
     */
    private function ruleMin(string $field, mixed $value, array $params): void
    {
        $min = (int) ($params[0] ?? 0);
        if ($value !== null && $value !== '' && mb_strlen((string) $value) < $min) {
            $this->addError($field, "Minimum {$min} caractères requis");
        }
    }

    /**
     * Longueur maximale, en CARACTÈRES — pour la raison symétrique.
     *
     * Compté en octets, `max:100` refusait un nom de soixante lettres dès
     * qu'elles portaient des accents. Sur un public gabonais et francophone,
     * cela bloquait des inscriptions parfaitement légitimes. C'est aussi
     * l'unité de MySQL : une colonne utf8mb4 VARCHAR(100) accepte cent
     * caractères, accentués ou non.
     */
    private function ruleMax(string $field, mixed $value, array $params): void
    {
        $max = (int) ($params[0] ?? 255);
        if ($value && mb_strlen((string) $value) > $max) {
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

    private function rulePhoneGabon(string $field, mixed $value, array $params): void
    {
        if ($value && Phone::normalizeGabon((string) $value) === null) {
            $this->addError($field, "Numéro gabonais invalide — ex : 074 12 34 56 (01, 06 ou 07)");
        }
    }
}
