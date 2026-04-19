<?php

namespace Govorun\Tests\Unit\State;

use Govorun\Messaging\Media;
use Govorun\Messaging\OutgoingMessage;
use Govorun\State\Step;
use Govorun\Tests\TestCase;

class StepTest extends TestCase
{
    public function test_ask_with_string_stores_string(): void
    {
        $step = new Step();
        $step->ask('Hello');

        $this->assertSame('Hello', $step->getAsk());
        $this->assertSame('Hello', $step->getAskText());
    }

    public function test_ask_with_outgoing_message_stores_object(): void
    {
        $step = new Step();
        $msg = Media::photo('https://example.com/a.jpg')->caption('Привет');
        $step->ask($msg);

        $this->assertSame($msg, $step->getAsk());
        $this->assertSame('Привет', $step->getAskText());
    }

    public function test_get_ask_returns_null_when_not_called(): void
    {
        $step = new Step();

        $this->assertNull($step->getAsk());
        $this->assertNull($step->getAskText());
    }

    public function test_ask_with_keyboard_builder_stored(): void
    {
        $step = new Step();
        $builder = fn () => null;
        $step->ask('Q', $builder);

        $this->assertSame($builder, $step->getAskCallback());
    }
}
