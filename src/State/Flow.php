<?php

namespace Govorun\State;

use Govorun\Contracts\MessengerDriver;
use Govorun\Contracts\StateStorage;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\Message;

abstract class Flow
{
    protected array $steps = [];
    protected array $interruptCommands = ['/start', '/cancel'];
    protected bool $interruptOnEvent = true;

    protected StateData $state;
    private string $chatId;
    private string $driverName;

    public function __construct(
        protected StateStorage $storage,
        protected MessengerDriver $driver,
        protected IncomingMessage $message,
    ) {
        $this->chatId = $message->chatId;
        $this->driverName = $message->driverName;
        $this->state = new StateData($this->loadData());
    }

    public function start(): void
    {
        $firstStep = $this->steps[0] ?? null;

        if ($firstStep === null) {
            return;
        }

        $this->saveState($firstStep);
        $this->executeAsk($firstStep);
    }

    public function resume(): void
    {
        $stateRecord = $this->storage->get($this->chatId, $this->driverName);

        if ($stateRecord === null) {
            return;
        }

        $currentStep = $stateRecord['current_step'];
        $this->state = new StateData($stateRecord['data'] ?? []);

        $step = new Step();
        $method = $currentStep . 'Step';

        if (! method_exists($this, $method)) {
            return;
        }

        $this->$method($step);

        $receiveCallback = $step->getReceiveCallback();

        if ($receiveCallback !== null) {
            $receiveCallback->call($this, $this->message);
        }
    }

    public function shouldInterrupt(IncomingMessage $message): bool
    {
        if ($this->interruptOnEvent && $message->type === ContentType::Event) {
            return true;
        }

        if ($message->text !== null) {
            foreach ($this->interruptCommands as $command) {
                $text = $message->text;
                $cmd = ltrim($command, '/');
                $textTrimmed = ltrim($text, '/');

                if ($textTrimmed === $cmd || str_starts_with($textTrimmed, $cmd . ' ')) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function nextStep(): void
    {
        $stateRecord = $this->storage->get($this->chatId, $this->driverName);
        $currentStep = $stateRecord['current_step'] ?? $this->steps[0];
        $currentIndex = array_search($currentStep, $this->steps);

        if ($currentIndex === false || $currentIndex >= count($this->steps) - 1) {
            // Last step — complete
            $this->storage->delete($this->chatId, $this->driverName);
            $this->onComplete();
            return;
        }

        $nextStep = $this->steps[$currentIndex + 1];
        $this->saveState($nextStep);
        $this->executeAsk($nextStep);
    }

    protected function reply(string $text): void
    {
        $msg = Message::make($text);
        $msg->chatId = $this->chatId;
        $this->driver->send($msg);
    }

    public function onComplete(): void {}

    public function onCancel(): void
    {
        $this->storage->delete($this->chatId, $this->driverName);
    }

    private function executeAsk(string $stepName): void
    {
        $step = new Step();
        $method = $stepName . 'Step';

        if (! method_exists($this, $method)) {
            return;
        }

        $this->$method($step);

        $askText = $step->getAskText();

        if ($askText !== null) {
            $askCallback = $step->getAskCallback();

            if ($askCallback !== null) {
                $keyboard = $askCallback->call($this);
                $msg = Message::make($askText)->keyboard($keyboard);
                $msg->chatId = $this->chatId;
                $this->driver->send($msg);
            } else {
                $this->reply($askText);
            }
        }
    }

    private function saveState(string $currentStep): void
    {
        $this->storage->set($this->chatId, $this->driverName, [
            'flow_class' => static::class,
            'current_step' => $currentStep,
            'data' => $this->state->all(),
        ]);
    }

    private function loadData(): array
    {
        $stateRecord = $this->storage->get($this->chatId, $this->driverName);

        return $stateRecord['data'] ?? [];
    }
}

class StateData
{
    public function __construct(private array $data = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function all(): array
    {
        return $this->data;
    }
}
