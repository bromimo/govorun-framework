<?php

namespace Govorun\State;

use Govorun\Contracts\MessengerDriver;
use Govorun\Contracts\StateStorage;
use Govorun\Messaging\IncomingMessage;

class FlowHandler
{
    public function __construct(
        private StateStorage $storage,
        private MessengerDriver $driver,
    ) {}

    /**
     * Returns true if Flow consumed the message (router should NOT dispatch).
     * Returns false if no active Flow or Flow was interrupted (router should dispatch).
     */
    public function handle(IncomingMessage $message): bool
    {
        $stateRecord = $this->storage->get($message->chatId, $message->driverName);

        if ($stateRecord === null) {
            return false;
        }

        $flowClass = $stateRecord['flow_class'] ?? null;

        if ($flowClass === null || ! class_exists($flowClass)) {
            $this->storage->delete($message->chatId, $message->driverName);
            return false;
        }

        /** @var Flow $flow */
        $flow = new $flowClass($this->storage, $this->driver, $message);

        if ($flow->shouldInterrupt($message)) {
            $flow->onCancel();
            return false;
        }

        $flow->resume();

        return true;
    }
}
