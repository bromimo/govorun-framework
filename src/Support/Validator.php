<?php

namespace Govorun\Support;

/** Валидатор пользовательского ввода с fluent-интерфейсом. */
class Validator
{
    private ?string $error = null;

    private function __construct(private readonly ?string $value) {}

    /** Создать экземпляр валидатора.
     * @param  ?string  $value  Проверяемое значение
     */
    public static function make(?string $value): static
    {
        return new static($value);
    }

    /** Обязательное поле. */
    public function required(string $message = 'Это обязательное поле'): static
    {
        if ($this->error === null && ($this->value === null || trim($this->value) === '')) {
            $this->error = $message;
        }

        return $this;
    }

    /** Корректный email. */
    public function email(string $message = 'Введите корректный email'): static
    {
        if ($this->error === null && $this->value !== null && ! filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            $this->error = $message;
        }

        return $this;
    }

    /** Числовое значение. */
    public function numeric(string $message = 'Значение должно быть числом'): static
    {
        if ($this->error === null && $this->value !== null && ! is_numeric($this->value)) {
            $this->error = $message;
        }

        return $this;
    }

    /** Целое число. */
    public function integer(string $message = 'Значение должно быть целым числом'): static
    {
        if ($this->error === null && $this->value !== null && filter_var($this->value, FILTER_VALIDATE_INT) === false) {
            $this->error = $message;
        }

        return $this;
    }

    /** Корректный URL. */
    public function url(string $message = 'Введите корректный URL'): static
    {
        if ($this->error === null && $this->value !== null && ! filter_var($this->value, FILTER_VALIDATE_URL)) {
            $this->error = $message;
        }

        return $this;
    }

    /** Номер телефона. */
    public function phone(string $message = 'Введите корректный номер телефона'): static
    {
        if ($this->error === null && $this->value !== null && ! preg_match('/^\+?[\d\s\-()]{7,20}$/', $this->value)) {
            $this->error = $message;
        }

        return $this;
    }

    /** Соответствие регулярному выражению. */
    public function regex(string $pattern, string $message = 'Значение не соответствует формату'): static
    {
        if ($this->error === null && $this->value !== null && ! preg_match($pattern, $this->value)) {
            $this->error = $message;
        }

        return $this;
    }

    /** Минимальная длина строки. */
    public function min(int $length, string $message = ''): static
    {
        if ($this->error === null && mb_strlen($this->value ?? '') < $length) {
            $this->error = $message ?: "Минимальная длина: {$length}";
        }

        return $this;
    }

    /** Максимальная длина строки. */
    public function max(int $length, string $message = ''): static
    {
        if ($this->error === null && mb_strlen($this->value ?? '') > $length) {
            $this->error = $message ?: "Максимальная длина: {$length}";
        }

        return $this;
    }

    /** Длина строки в диапазоне. */
    public function between(int $min, int $max, string $message = ''): static
    {
        $len = mb_strlen($this->value ?? '');
        if ($this->error === null && ($len < $min || $len > $max)) {
            $this->error = $message ?: "Длина должна быть от {$min} до {$max}";
        }

        return $this;
    }

    /** Значение из списка допустимых.
     * @param  array<string>  $values
     */
    public function in(array $values, string $message = 'Выберите одно из допустимых значений'): static
    {
        if ($this->error === null && ! in_array($this->value, $values)) {
            $this->error = $message;
        }

        return $this;
    }

    /** Корректная дата. */
    public function date(string $message = 'Введите корректную дату'): static
    {
        if ($this->error === null && ($this->value === null || strtotime($this->value) === false)) {
            $this->error = $message;
        }

        return $this;
    }

    /** Получить первую ошибку или null, если все проверки прошли. */
    public function validate(): ?string
    {
        return $this->error;
    }
}
