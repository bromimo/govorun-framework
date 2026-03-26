<?php

namespace Govorun\Tests\Unit\Testing;

use Govorun\Foundation\Application;
use Govorun\Http\Request;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\Message;
use Govorun\Testing\FakeDriver;
use PHPUnit\Framework\TestCase;

class FakeDriverTest extends TestCase
{
    private FakeDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new FakeDriver();
    }

    protected function tearDown(): void
    {
        Application::setInstance(null);
        parent::tearDown();
    }

    public function test_verify_webhook_always_returns_true(): void
    {
        $request = new Request(server: ['REQUEST_URI' => '/webhook/fake']);
        $this->assertTrue($this->driver->verifyWebhook($request));
    }

    public function test_parse_update_returns_queued_message(): void
    {
        $message = new IncomingMessage(
            id: '1', chatId: '100', driverName: 'fake',
            text: 'hello', user: new UserDto(id: '1'), type: ContentType::Text,
        );

        $this->driver->queueMessage($message);

        $request = new Request(server: ['REQUEST_URI' => '/webhook/fake']);
        $result = $this->driver->parseUpdate($request);

        $this->assertSame($message, $result);
    }

    public function test_parse_update_throws_when_queue_empty(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No messages in FakeDriver queue');

        $request = new Request(server: ['REQUEST_URI' => '/webhook/fake']);
        $this->driver->parseUpdate($request);
    }

    public function test_send_collects_outgoing_messages(): void
    {
        $msg1 = Message::make('Hello');
        $msg2 = Message::make('World');

        $this->driver->send($msg1);
        $this->driver->send($msg2);

        $sent = $this->driver->getSentMessages();
        $this->assertCount(2, $sent);
        $this->assertSame('Hello', $sent[0]->text);
        $this->assertSame('World', $sent[1]->text);
    }

    public function test_reset_sent_messages_clears_collection(): void
    {
        $this->driver->send(Message::make('Hello'));
        $this->driver->resetSentMessages();

        $this->assertEmpty($this->driver->getSentMessages());
    }

    public function test_install_webhook_returns_true(): void
    {
        $this->assertTrue($this->driver->installWebhook('https://example.com'));
    }

    public function test_remove_webhook_returns_true(): void
    {
        $this->assertTrue($this->driver->removeWebhook());
    }

    public function test_get_user_returns_stub_dto(): void
    {
        $user = $this->driver->getUser('42');
        $this->assertSame('42', $user->id);
    }
}
