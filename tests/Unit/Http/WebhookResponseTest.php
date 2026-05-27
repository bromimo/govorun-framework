<?php

namespace Govorun\Tests\Unit\Http;

use Govorun\Http\WebhookResponse;
use Govorun\Tests\TestCase;

class WebhookResponseTest extends TestCase
{
    public function test_ok_defaults_to_empty_body(): void
    {
        $r = WebhookResponse::ok();

        $this->assertSame(200, $r->status);
        $this->assertSame('', $r->body);
    }

    public function test_ok_with_body(): void
    {
        $r = WebhookResponse::ok('ok');

        $this->assertSame(200, $r->status);
        $this->assertSame('ok', $r->body);
    }

    public function test_forbidden(): void
    {
        $r = WebhookResponse::forbidden();

        $this->assertSame(403, $r->status);
        $this->assertSame('', $r->body);
    }

    public function test_text(): void
    {
        $r = WebhookResponse::text('abc123');

        $this->assertSame(200, $r->status);
        $this->assertSame('abc123', $r->body);
    }
}