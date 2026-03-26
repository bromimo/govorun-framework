<?php

namespace Govorun\Testing;

use Govorun\Foundation\Application;
use Govorun\Http\Request;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;
use PHPUnit\Framework\Assert;

class FakeMessenger
{
    private int $cursor = 0;
    private int $messageCounter = 0;
    private ?OutgoingMessage $lastConsumed = null;

    public function __construct(
        private Application $app,
        private FakeDriver $driver,
        private string $chatId = 'fake-chat-1',
    ) {}

    public function receive(string $text): static
    {
        $this->messageCounter++;

        $message = new IncomingMessage(
            id: (string) $this->messageCounter,
            chatId: $this->chatId,
            driverName: 'fake',
            text: $text,
            user: new UserDto(id: 'fake-user-1', firstName: 'Test'),
            type: ContentType::Text,
        );

        $this->driver->queueMessage($message);

        $request = new Request(
            server: ['REQUEST_URI' => '/webhook/fake', 'REQUEST_METHOD' => 'POST'],
            content: '{}',
        );

        $this->app->handleWebhook($request);

        return $this;
    }

    public function clickButton(string $text): static
    {
        $sent = $this->driver->getSentMessages();
        $lastMessage = end($sent);

        if ($lastMessage === false || $lastMessage->keyboard === null) {
            throw new \RuntimeException("Button '{$text}' not found in last message keyboard");
        }

        $button = $this->findButton($lastMessage->keyboard, $text);

        if ($button === null) {
            throw new \RuntimeException("Button '{$text}' not found in last message keyboard");
        }

        $this->messageCounter++;

        $message = new IncomingMessage(
            id: (string) $this->messageCounter,
            chatId: $this->chatId,
            driverName: 'fake',
            text: null,
            user: new UserDto(id: 'fake-user-1', firstName: 'Test'),
            type: ContentType::Action,
            action: $button['action'] ?? '',
            actionParams: $button['param'] ?? [],
        );

        $this->driver->queueMessage($message);

        $request = new Request(
            server: ['REQUEST_URI' => '/webhook/fake', 'REQUEST_METHOD' => 'POST'],
            content: '{}',
        );

        $this->app->handleWebhook($request);

        return $this;
    }

    public function assertReply(string $expected): static
    {
        $sent = $this->driver->getSentMessages();

        Assert::assertArrayHasKey(
            $this->cursor,
            $sent,
            "Expected reply '{$expected}' but no messages were sent",
        );

        $actual = $sent[$this->cursor]->text;
        $this->lastConsumed = $sent[$this->cursor];
        $this->cursor++;

        Assert::assertSame($expected, $actual, "Expected reply '{$expected}' but got '{$actual}'");

        return $this;
    }

    public function assertReplyContains(string $needle): static
    {
        $sent = $this->driver->getSentMessages();

        Assert::assertArrayHasKey(
            $this->cursor,
            $sent,
            "Expected reply containing '{$needle}' but no messages were sent",
        );

        $actual = $sent[$this->cursor]->text;
        $this->lastConsumed = $sent[$this->cursor];
        $this->cursor++;

        Assert::assertStringContainsString(
            $needle,
            $actual ?? '',
            "Expected reply containing '{$needle}' but got '{$actual}'",
        );

        return $this;
    }

    public function assertNoReply(): static
    {
        $sent = $this->driver->getSentMessages();
        $remaining = array_slice($sent, $this->cursor);

        Assert::assertEmpty(
            $remaining,
            'Expected no more replies but found ' . count($remaining) . ' message(s)',
        );

        return $this;
    }

    public function assertKeyboard(array $expectedButtons): static
    {
        Assert::assertNotNull(
            $this->lastConsumed,
            'assertKeyboard must be called after assertReply or assertReplyContains',
        );

        $keyboard = $this->lastConsumed->keyboard;

        Assert::assertNotNull($keyboard, 'Expected keyboard but message has none');

        $actualButtons = [];
        foreach ($keyboard['rows'] as $row) {
            foreach ($row as $btn) {
                $actualButtons[] = $btn['text'];
            }
        }

        Assert::assertSame(
            $expectedButtons,
            $actualButtons,
            'Expected buttons [' . implode(', ', $expectedButtons) . '] but got [' . implode(', ', $actualButtons) . ']',
        );

        return $this;
    }

    private function findButton(array $keyboard, string $text): ?array
    {
        foreach ($keyboard['rows'] as $row) {
            foreach ($row as $btn) {
                if ($btn['text'] === $text) {
                    return $btn;
                }
            }
        }

        return null;
    }
}
