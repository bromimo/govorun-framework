<?php

namespace Govorun\Tests\Unit\Messaging;

use Govorun\Messaging\Button;
use Govorun\Tests\TestCase;

class ButtonTest extends TestCase
{
    public function test_creates_action_button(): void
    {
        $btn = new Button('Маникюр', action: 'service', param: ['type' => 'manicure']);
        $this->assertSame('Маникюр', $btn->text);
        $this->assertSame('service', $btn->action);
        $this->assertSame(['type' => 'manicure'], $btn->param);
        $this->assertNull($btn->url);
        $this->assertFalse($btn->requestContact);
        $this->assertFalse($btn->requestLocation);
    }

    public function test_creates_url_button(): void
    {
        $btn = new Button('На сайт', url: 'https://example.com');
        $this->assertSame('На сайт', $btn->text);
        $this->assertSame('https://example.com', $btn->url);
        $this->assertNull($btn->action);
    }

    public function test_creates_contact_request_button(): void
    {
        $btn = new Button('Телефон', requestContact: true);
        $this->assertTrue($btn->requestContact);
        $this->assertFalse($btn->requestLocation);
    }

    public function test_creates_location_request_button(): void
    {
        $btn = new Button('Локация', requestLocation: true);
        $this->assertTrue($btn->requestLocation);
        $this->assertFalse($btn->requestContact);
    }

    public function test_to_array_includes_only_set_fields(): void
    {
        $btn = new Button('Click', action: 'btn', param: ['id' => '1']);
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
        $btn = new Button('Phone', requestContact: true);
        $arr = $btn->toArray();
        $this->assertTrue($arr['requestContact']);
        $this->assertArrayNotHasKey('requestLocation', $arr);
    }
}
