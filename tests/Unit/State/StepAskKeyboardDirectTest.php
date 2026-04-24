<?php

namespace Govorun\Tests\Unit\State;

use Govorun\State\Step;
use Govorun\Tests\TestCase;
use Govorun\Messaging\Button;
use Govorun\Messaging\Keyboard;

class StepAskKeyboardDirectTest extends TestCase
{
    public function test_ask_accepts_keyboard_directly(): void
    {
        $step = new Step;
        $kb = Keyboard::make()->buttons([[Button::make('OK')->action('ok')]]);

        $step->ask('Подтверждаете?', $kb);

        $this->assertSame($kb, $step->getAskCallback());
    }

    public function test_ask_still_accepts_closure(): void
    {
        $step = new Step;
        $closure = fn () => Keyboard::make()->buttons([[Button::make('Yes')->action('y')]]);

        $step->ask('Выберите', $closure);

        $this->assertSame($closure, $step->getAskCallback());
    }

    public function test_ask_without_keyboard_is_null(): void
    {
        $step = new Step;

        $step->ask('Имя?');

        $this->assertNull($step->getAskCallback());
    }
}
