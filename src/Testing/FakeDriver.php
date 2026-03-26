<?php

namespace Govorun\Testing;

use Govorun\Contracts\MessengerDriver;
use Govorun\Http\Request;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;

class FakeDriver implements MessengerDriver
{
    /** @var IncomingMessage[] */
    private array $pendingMessages = [];

    /** @var OutgoingMessage[] */
    private array $sentMessages = [];

    public function queueMessage(IncomingMessage $message): void
    {
        $this->pendingMessages[] = $message;
    }

    public function verifyWebhook(Request $request): bool
    {
        return true;
    }

    public function parseUpdate(Request $request): IncomingMessage
    {
        if (empty($this->pendingMessages)) {
            throw new \RuntimeException('No messages in FakeDriver queue');
        }

        return array_shift($this->pendingMessages);
    }

    public function send(OutgoingMessage $message): void
    {
        $this->sentMessages[] = $message;
    }

    public function edit(string $messageId, OutgoingMessage $message): void {}

    public function delete(string $messageId, string $chatId): void {}

    public function installWebhook(string $url): bool
    {
        return true;
    }

    public function removeWebhook(): bool
    {
        return true;
    }

    public function getUser(string $id): UserDto
    {
        return new UserDto(id: $id);
    }

    /** @return OutgoingMessage[] */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function resetSentMessages(): void
    {
        $this->sentMessages = [];
    }
}
