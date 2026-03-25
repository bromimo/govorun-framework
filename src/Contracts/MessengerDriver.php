<?php

namespace Govorun\Contracts;

use Govorun\Http\Request;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;

interface MessengerDriver
{
    public function verifyWebhook(Request $request): bool;
    public function parseUpdate(Request $request): IncomingMessage;
    public function send(OutgoingMessage $message): void;
    public function edit(string $messageId, OutgoingMessage $message): void;
    public function delete(string $messageId, string $chatId): void;
    public function installWebhook(string $url): bool;
    public function removeWebhook(): bool;
    public function getUser(string $id): UserDto;
}
