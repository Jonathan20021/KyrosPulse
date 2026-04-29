<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validador de datos sencillo con reglas comunes.
 *
 *   $v = new Validator($data, [
 *     'email'    => 'required|email',
 *     'password' => 'required|min:8',
 *   ]);
 *   if ($v->fails()) { $errors = $v->errors(); }
 */
final class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $messages;

    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data     = $data;
        $this->rules    = $rules;
        $this->messages = $messages;
        $this->run();
    }

    public function fails(): bool { return !empty($this->errors); }
    public function passes(): bool { return empty($this->errors); }
    public function errors(): array { return $this->errors; }
    public function firstError(): ?string
    {
        if (empty($this->errors)) return null;
        $first = reset($this->errors);
        return is_array($first) ? ($first[0] ?? null) : $first;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $rules) {
            $value = $this->data[$field] ?? null;
            $rules = is_array($rules) ? $rules : explode('|', $rules);
            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        $param = null;
        if (str_contains($rule, ':')) {
            [$rule, $param] = explode(':', $rule, 2);
        }

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    $this->error($field, 'El campo :field es obligatorio.');
                }
                break;

            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->error($field, 'El campo :field debe ser un email valido.');
                }
                break;

            case 'min':
                if ($value !== null && $value !== '' && mb_strlen((string) $value) < (int) $param) {
                    $this->error($field, "El campo :field debe tener al menos $param caracteres.");
                }
                break;

            case 'max':
                if ($value !== null && mb_strlen((string) $value) > (int) $param) {
                    $this->error($field, "El campo :field no debe superar $param caracteres.");
                }
                break;

            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->error($field, 'El campo :field debe ser numerico.');
                }
                break;

            case 'integer':
                if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->error($field, 'El campo :field debe ser un entero.');
                }
                break;

            case 'in':
                $allowed = array_map('trim', explode(',', (string) $param));
                if ($value !== null && $value !== '' && !in_array((string) $value, $allowed, true)) {
                    $this->error($field, 'El campo :field tiene un valor no permitido.');
                }
                break;

            case 'same':
                if ($value !== ($this->data[$param] ?? null)) {
                    $this->error($field, "El campo :field debe coincidir con $param.");
                }
                break;

            case 'confirmed':
                if ($value !== ($this->data[$field . '_confirmation'] ?? null)) {
                    $this->error($field, 'La confirmacion del campo :field no coincide.');
                }
                break;

            case 'phone':
                if ($value !== null && $value !== '' && !preg_match('/^\+?[0-9\s\-\(\)]{6,20}$/', (string) $value)) {
                    $this->error($field, 'El campo :field debe ser un telefono valido.');
                }
                break;

            case 'alpha':
                if ($value !== null && $value !== '' && !preg_match('/^[\pL\s]+$/u', (string) $value)) {
                    $this->error($field, 'El campo :field solo puede contener letras.');
                }
                break;

            case 'alphanum':
                if ($value !== null && $value !== '' && !preg_match('/^[\pL\pN\s\-_]+$/u', (string) $value)) {
                    $this->error($field, 'El campo :field solo puede contener letras y numeros.');
                }
                break;

            case 'url':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->error($field, 'El campo :field debe ser una URL valida.');
                }
                break;

            case 'unique':
                // unique:tabla,columna
                if ($value !== null && $value !== '' && $param) {
                    [$table, $column] = array_pad(explode(',', $param), 2, null);
                    $column = $column ?: $field;
                    $exists = Database::fetchColumn(
                        "SELECT COUNT(*) FROM `$table` WHERE `$column` = :v",
                        ['v' => $value]
                    );
                    if ((int) $exists > 0) {
                        $this->error($field, "El valor de :field ya esta en uso.");
                    }
                }
                break;
        }
    }

    private function error(string $field, string $defaultMsg): void
    {
        $key = "$field." . $this->ruleFromMsg($defaultMsg);
        $msg = $this->messages[$key]
            ?? $this->messages[$field]
            ?? str_replace(':field', $field, $defaultMsg);
        $this->errors[$field][] = $msg;
    }

    private function ruleFromMsg(string $msg): string
    {
        return md5($msg);
    }
}
