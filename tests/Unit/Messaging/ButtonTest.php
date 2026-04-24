<?php

namespace Govorun\Tests\Unit\Messaging;

use Govorun\Messaging\Button;
use Govorun\Tests\TestCase;

class ButtonTest extends TestCase
{
    public function test_creates_action_button(): void
    {
        $btn = Button::make('Маникюр')->action('service', ['type' => 'manicure']);
        $this->assertSame('Маникюр', $btn->text);
        $this->assertSame('service', $btn->action);
        $this->assertSame(['type' => 'manicure'], $btn->param);
        $this->assertNull($btn->url);
        $this->assertFalse($btn->requestContact);
        $this->assertFalse($btn->requestLocation);
    }

    public function test_creates_url_button(): void
    {
        $btn = Button::make('На сайт')->url('https://example.com');
        $this->assertSame('На сайт', $btn->text);
        $this->assertSame('https://example.com', $btn->url);
        $this->assertNull($btn->action);
    }

    public function test_creates_contact_request_button(): void
    {
        $btn = Button::make('Телефон')->requestContact();
        $this->assertTrue($btn->requestContact);
        $this->assertFalse($btn->requestLocation);
    }

    public function test_creates_location_request_button(): void
    {
        $btn = Button::make('Локация')->requestLocation();
        $this->assertTrue($btn->requestLocation);
        $this->assertFalse($btn->requestContact);
    }

    public function test_to_array_includes_only_set_fields(): void
    {
        $btn = Button::make('Click')->action('btn', ['id' => '1']);
        $arr = $btn->toArray();
        $this->assertSame('Click', $arr['text']);
        $this->assertSame('btn', $arr['action']);
        $this->assertSame(['id' => '1'], $arr['param']);
        $this->assertArrayNotHasKey('url', $arr);
        $this->assertArrayNotHasKey('requestContact', $arr);
        $this->assertArrayNotHasKey('requestLocation', $arr);
    }

    public function test_to_array_includes_request_flags_when_true(): void
    {
        $btn = Button::make('Phone')->requestContact();
        $arr = $btn->toArray();
        $this->assertTrue($arr['requestContact']);
        $this->assertArrayNotHasKey('requestLocation', $arr);
    }

    public function test_make_returns_button_with_text(): void
    {
        $btn = Button::make('Hello');

        $this->assertSame('Hello', $btn->text);
        $this->assertNull($btn->action);
        $this->assertNull($btn->url);
        $this->assertFalse($btn->requestContact);
        $this->assertFalse($btn->requestLocation);
    }

    public function test_fluent_chain_sets_all_fields(): void
    {
        $btn = Button::make('Click')
            ->action('do_thing', ['id' => 42])
            ->url('https://example.com')
            ->requestContact()
            ->requestLocation(false);

        $this->assertSame('do_thing', $btn->action);
        $this->assertSame(['id' => 42], $btn->param);
        $this->assertSame('https://example.com', $btn->url);
        $this->assertTrue($btn->requestContact);
        $this->assertFalse($btn->requestLocation);
    }
}
