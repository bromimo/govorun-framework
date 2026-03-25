<?php

namespace Govorun\Tests\Unit\Http;

use Govorun\Http\Request;
use Govorun\Tests\TestCase;

class RequestTest extends TestCase
{
    public function test_returns_content(): void
    {
        $request = new Request(content: '{"update_id":123}');
        $this->assertSame('{"update_id":123}', $request->getContent());
    }

    public function test_parses_json(): void
    {
        $request = new Request(content: '{"update_id":123}');
        $this->assertSame(['update_id' => 123], $request->json());
    }

    public function test_json_returns_empty_array_on_null_content(): void
    {
        $request = new Request();
        $this->assertSame([], $request->json());
    }

    public function test_returns_header(): void
    {
        $request = new Request(server: [
            'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'secret123',
        ]);
        $this->assertSame('secret123', $request->header('X-Telegram-Bot-Api-Secret-Token'));
    }

    public function test_returns_null_for_missing_header(): void
    {
        $request = new Request();
        $this->assertNull($request->header('X-Missing'));
    }

    public function test_returns_method(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'POST']);
        $this->assertSame('POST', $request->method());
    }

    public function test_defaults_method_to_get(): void
    {
        $request = new Request();
        $this->assertSame('GET', $request->method());
    }

    public function test_returns_uri(): void
    {
        $request = new Request(server: ['REQUEST_URI' => '/webhook/telegram?foo=bar']);
        $this->assertSame('/webhook/telegram?foo=bar', $request->uri());
    }

    public function test_returns_path_without_query(): void
    {
        $request = new Request(server: ['REQUEST_URI' => '/webhook/telegram?foo=bar']);
        $this->assertSame('/webhook/telegram', $request->path());
    }

    public function test_returns_query_parameters(): void
    {
        $request = new Request(query: ['foo' => 'bar', 'baz' => '1']);
        $this->assertSame('bar', $request->query('foo'));
        $this->assertSame('1', $request->query('baz'));
        $this->assertNull($request->query('missing'));
        $this->assertSame('default', $request->query('missing', 'default'));
    }
}
