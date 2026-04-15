<?php

namespace Govorun\Support;

/** Валидатор пользовательского ввода с fluent-интерфейсом. */
class Validator
{
    /** @var array<string, string>|false Кэш дефолтных сообщений из JSON (false — не загружено). */
    private static array|false $defaults = false;

    private ?string $error = null;

    private ?\Closure $errorHandler = null;

    private function __construct(private readonly ?string $value) {}

    /** Создать экземпляр валидатора.
     * @param  ?string  $value  Проверяемое значение
     */
    public static function make(?string $value): static
    {
        return new static($value);
    }

    /** Получить дефолтные сообщения из JSON (lazy, один раз на процесс).
     * @return array<string, string>
     * @throws \RuntimeException Если файл сообщений недоступен или содержит некорректный JSON
     */
    private static function defaults(): array
    {
        if (self::$defaults === false) {
            $path = __DIR__.'/../../resources/validation-messages.json';
            $json = file_get_contents($path);

            if ($json === false) {
                throw new \RuntimeException("Не удалось прочитать файл сообщений валидации: {$path}");
            }

            $decoded = json_decode($json, true);

            if (! is_array($decoded)) {
                throw new \RuntimeException("Некорректный JSON в файле сообщений валидации: {$path}");
            }

            self::$defaults = $decoded;
        }

        return self::$defaults;
    }

    /** Обязательное поле. */
    public function required(?string $message = null): static
    {
        if ($this->error === null && ($this->value === null || trim($this->value) === '')) {
            $this->error = $message ?? self::defaults()['required'];
        }

        return $this;
    }

    /** Корректный email. */
    public function email(?string $message = null): static
    {
        if ($this->error === null && $this->value !== null && ! filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            $this->error = $message ?? self::defaults()['email'];
        }

        return $this;
    }

    /** Текстовое значение (не чисто числовое). */
    public function string(?string $message = null): static
    {
        if ($this->error === null && $this->value !== null && is_numeric($this->value)) {
            $this->error = $message ?? self::defaults()['string'];
        }

        return $this;
    }

    /** Числовое значение. */
    public function numeric(?string $message = null): static
    {
        if ($this->error === null && $this->value !== null && ! is_numeric($this->value)) {
            $this->error = $message ?? self::defaults()['numeric'];
        }

        return $this;
    }

    /** Целое число. */
    public function integer(?string $message = null): static
    {
        if ($this->error === null && $this->value !== null && filter_var($this->value, FILTER_VALIDATE_INT) === false) {
            $this->error = $message ?? self::defaults()['integer'];
        }

        return $this;
    }

    /** Корректный URL. */
    public function url(?string $message = null): static
    {
        if ($this->error === null && $this->value !== null && ! filter_var($this->value, FILTER_VALIDATE_URL)) {
            $this->error = $message ?? self::defaults()['url'];
        }

        return $this;
    }

    /** Номер телефона. */
    public function phone(?string $message = null): static
    {
        if ($this->error === null && $this->value !== null && ! preg_match('/^\+?[\d\s\-()]{7,20}$/', $this->value)) {
            $this->error = $message ?? self::defaults()['phone'];
        }

        return $this;
    }

    /** Соответствие регулярному выражению. */
    public function regex(string $pattern, ?string $message = null): static
    {
        if ($this->error === null && $this->value !== null && ! preg_match($pattern, $this->value)) {
            $this->error = $message ?? self::defaults()['regex'];
        }

        return $this;
    }

    /** Минимальная длина строки. */
    public function min(int $value, ?string $message = null): static
    {
        if ($this->error === null && mb_strlen($this->value ?? '') < $value) {
            $this->error = $message ?? str_replace('{value}', (string) $value, self::defaults()['min']);
        }

        return $this;
    }

    /** Максимальная длина строки. */
    public function max(int $value, ?string $message = null): static
    {
        if ($this->error === null && mb_strlen($this->value ?? '') > $value) {
            $this->error = $message ?? str_replace('{value}', (string) $value, self::defaults()['max']);
        }

        return $this;
    }

    /** Длина строки в диапазоне. */
    public function between(int $min, int $max, ?string $message = null): static
    {
        $len = mb_strlen($this->value ?? '');
        if ($this->error === null && ($len < $min || $len > $max)) {
            // $min/$max — целые числа, cast в строку; плейсхолдеров не содержат, порядок замены безопасен.
            $this->error = $message ?? str_replace(
                ['{min}', '{max}'],
                [(string) $min, (string) $max],
                self::defaults()['between'],
            );
        }

        return $this;
    }

    /** Значение из списка допустимых.
     * @param  array<string>  $values
     */
    public function in(array $values, ?string $message = null): static
    {
        if ($this->error === null && ! in_array($this->value, $values)) {
            $this->error = $message ?? self::defaults()['in'];
        }

        return $this;
    }

    /** Корректная дата. */
    public function date(?string $message = null): static
    {
        if ($this->error === null && ($this->value === null || strtotime($this->value) === false)) {
            $this->error = $message ?? self::defaults()['date'];
        }

        return $this;
    }

    /** Установить обработчик ошибки для fails().
     * @param  \Closure  $handler  fn(string $error): void
     */
    public function withErrorHandler(\Closure $handler): static
    {
        $this->errorHandler = $handler;

        return $this;
    }

    /** Отключить автоматическую отправку ошибки. */
    public function silent(): static
    {
        $this->errorHandler = null;

        return $this;
    }

    /** Проверить, провалена ли валидация. Вызывает errorHandler если установлен. */
    public function fails(): bool
    {
        if ($this->error === null) {
            return false;
        }

        if ($this->errorHandler !== null) {
            ($this->errorHandler)($this->error);
        }

        return true;
    }

    /** Получить первую ошибку или null, если все проверки прошли. */
    public function validate(): ?string
    {
        return $this->error;
    }
}
