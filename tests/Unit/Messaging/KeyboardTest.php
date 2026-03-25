<?php

namespace Govorun\Tests\Unit\Messaging;

use Govorun\Messaging\Keyboard;
use Govorun\Tests\TestCase;

class KeyboardTest extends TestCase
{
    public function test_make_creates_inline_keyboard(): void
    {
        $kb = Keyboard::make();
        $arr = $kb->toArray();
        $this->assertSame('inline', $arr['type']);
        $this->assertFalse($arr['remove']);
        $this->assertSame([], $arr['rows']);
    }

    public function test_reply_creates_reply_keyboard(): void
    {
        $kb = Keyboard::reply();
        $arr = $kb->toArray();
        $this->assertSame('reply', $arr['type']);
    }

    public function test_remove_creates_remove_keyboard(): void
    {
        $kb = Keyboard::remove();
        $arr = $kb->toArray();
        $this->assertTrue($arr['remove']);
    }

    public function test_buttons_go_into_single_row_by_default(): void
    {
        $kb = Keyboard::make()
            ->button('A', action: 'a')
            ->button('B', action: 'b');
        $arr = $kb->toArray();
        $this->assertCount(1, $arr['rows']);
        $this->assertCount(2, $arr['rows'][0]);
        $this->assertSame('A', $arr['rows'][0][0]['text']);
        $this->assertSame('B', $arr['rows'][0][1]['text']);
    }

    public function test_row_breaks_buttons_into_new_row(): void
    {
        $kb = Keyboard::make()
            ->button('A', action: 'a')
            ->row()
            ->button('B', action: 'b');
        $arr = $kb->toArray();
        $this->assertCount(2, $arr['rows']);
        $this->assertCount(1, $arr['rows'][0]);
        $this->assertCount(1, $arr['rows'][1]);
        $this->assertSame('A', $arr['rows'][0][0]['text']);
        $this->assertSame('B', $arr['rows'][1][0]['text']);
    }

    public function test_button_with_url(): void
    {
        $kb = Keyboard::make()->button('Link', url: 'https://example.com');
        $arr = $kb->toArray();
        $this->assertSame('https://example.com', $arr['rows'][0][0]['url']);
    }

    public function test_button_with_params(): void
    {
        $kb = Keyboard::make()->button('Service', action: 'service', param: ['type' => 'manicure']);
        $arr = $kb->toArray();
        $this->assertSame('service', $arr['rows'][0][0]['action']);
        $this->assertSame(['type' => 'manicure'], $arr['rows'][0][0]['param']);
    }

    public function test_reply_keyboard_with_contact_and_location(): void
    {
        $kb = Keyboard::reply()
            ->button('Отправить номер', requestContact: true)
            ->button('Отправить локацию', requestLocation: true);
        $arr = $kb->toArray();
        $this->assertSame('reply', $arr['type']);
        $this->assertTrue($arr['rows'][0][0]['requestContact']);
        $this->assertTrue($arr['rows'][0][1]['requestLocation']);
    }

    public function test_empty_row_call_is_ignored(): void
    {
        $kb = Keyboard::make()->row()->button('A', action: 'a');
        $arr = $kb->toArray();
        $this->assertCount(1, $arr['rows']);
    }
}
