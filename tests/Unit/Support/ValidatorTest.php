<?php

namespace Govorun\Tests\Unit\Support;

use Govorun\Support\Validator;
use PHPUnit\Framework\TestCase;

/** Тесты валидатора: дефолты из JSON и именованные плейсхолдеры. */
class ValidatorTest extends TestCase
{
    public function test_default_required_message_from_json(): void
    {
        $error = Validator::make(null)->required()->validate();
        $this->assertSame('Пожалуйста, введите значение', $error);
    }

    public function test_default_email_message_from_json(): void
    {
        $error = Validator::make('not-an-email')->email()->validate();
        $this->assertSame('Введите корректный email', $error);
    }

    public function test_default_string_message_from_json(): void
    {
        $error = Validator::make('123')->string()->validate();
        $this->assertSame('Значение должно быть текстом', $error);
    }

    public function test_min_substitutes_value_placeholder(): void
    {
        $error = Validator::make('ab')->min(5)->validate();
        $this->assertSame('Минимальная длина: 5 символов', $error);
    }

    public function test_max_substitutes_value_placeholder(): void
    {
        $error = Validator::make('abcdef')->max(3)->validate();
        $this->assertSame('Максимальная длина: 3 символов', $error);
    }

    public function test_between_substitutes_min_and_max_placeholders(): void
    {
        $error = Validator::make('a')->between(3, 10)->validate();
        $this->assertSame('Длина должна быть от 3 до 10', $error);
    }

    public function test_custom_message_overrides_default(): void
    {
        $error = Validator::make(null)->required('Моё сообщение')->validate();
        $this->assertSame('Моё сообщение', $error);
    }

    public function test_custom_message_for_min_is_not_templated(): void
    {
        // Кастомное сообщение передаётся как есть — без подстановки.
        $error = Validator::make('x')->min(5, 'Слишком коротко')->validate();
        $this->assertSame('Слишком коротко', $error);
    }

    public function test_in_uses_json_default(): void
    {
        $error = Validator::make('xyz')->in(['a', 'b'])->validate();
        $this->assertSame('Выберите одно из допустимых значений', $error);
    }

    public function test_url_uses_json_default(): void
    {
        $error = Validator::make('not-a-url')->url()->validate();
        $this->assertSame('Введите корректный URL', $error);
    }
}
