<?php

namespace Govorun\Routing;

use Govorun\Contracts\MessengerDriver;
use Govorun\Contracts\StateStorage;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\Message;
use Govorun\Messaging\OutgoingMessage;
use Govorun\State\Flow;

abstract class Controller
{
    protected IncomingMessage $incomingMessage;
    protected MessengerDriver $driver;
    protected ?StateStorage $stateStorage = null;

    public function setContext(IncomingMessage $message, MessengerDriver $driver): void
    {
        $this->incomingMessage = $message;
        $this->driver = $driver;
    }

    protected function message(): IncomingMessage
    {
        return $this->incomingMessage;
    }

    protected function user(): UserDto
    {
        return $this->incomingMessage->user;
    }

    protected function reply(string $text): void
    {
        $msg = Message::make($text);
        $msg->chatId = $this->incomingMessage->chatId;
        $this->driver->send($msg);
    }

    protected function send(OutgoingMessage $message): void
    {
        $message->chatId = $this->incomingMessage->chatId;
        $this->driver->send($message);
    }

    protected function edit(OutgoingMessage $message): void
    {
        $message->chatId = $this->incomingMessage->chatId;
        // Requires lastMessageId tracking — stub for Phase 5
    }

    protected function deleteLastMessage(): void
    {
        // Requires lastMessageId tracking — stub for Phase 5
    }

    protected function param(string $key): ?string
    {
        return $this->incomingMessage->actionParams[$key] ?? null;
    }

    public function setStateStorage(StateStorage $storage): void
    {
        $this->stateStorage = $storage;
    }

    protected function startFlow(string $flowClass): void
    {
        $storage = $this->resolveStateStorage();

        /** @var Flow $flow */
        $flow = new $flowClass($storage, $this->driver, $this->incomingMessage);
        $flow->start();
    }

    protected function resolveStateStorage(): StateStorage
    {
        if ($this->stateStorage !== null) {
            return $this->stateStorage;
        }

        return app(\Govorun\Contracts\StateStorage::class);
    }
}
