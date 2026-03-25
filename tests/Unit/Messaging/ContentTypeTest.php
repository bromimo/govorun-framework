<?php

namespace Govorun\Tests\Unit\Messaging;

use Govorun\Messaging\ContentType;
use Govorun\Tests\TestCase;

class ContentTypeTest extends TestCase
{
    public function test_has_all_expected_cases(): void
    {
        $this->assertSame('text', ContentType::Text->value);
        $this->assertSame('action', ContentType::Action->value);
        $this->assertSame('media', ContentType::Media->value);
        $this->assertSame('location', ContentType::Location->value);
        $this->assertSame('contact', ContentType::Contact->value);
        $this->assertSame('event', ContentType::Event->value);
    }

    public function test_can_be_created_from_value(): void
    {
        $this->assertSame(ContentType::Text, ContentType::from('text'));
        $this->assertSame(ContentType::Action, ContentType::from('action'));
    }

    public function test_has_exactly_six_cases(): void
    {
        $this->assertCount(6, ContentType::cases());
    }
}
