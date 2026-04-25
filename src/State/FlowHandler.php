<?php

namespace Govorun\State;

use Govorun\Contracts\StateStorage;
use Govorun\Contracts\MessengerDriver;
use Govorun\Messaging\IncomingMessage;

/** Обработчик активных диалоговых потоков.
 * Проверяет наличие активного Flow для чата и при необходимости
 * возобновляет или прерывает его.
 */
class FlowHandler
{
    /** Создать экземпляр обработчика потоков.
     * @param StateStorage $storage Хранилище состояния
     * @param MessengerDriver $driver Драйвер мессенджера
     */
    public function __construct(
        private StateStorage $storage,
        private MessengerDriver $driver,
    ) {}

    /** Обработать входящее сообщение в контексте активного потока.
     * Возвращает true, если Flow обработал сообщение (роутер НЕ должен диспатчить).
     * Возвращает false, если нет активного Flow или он был прерван (роутер должен диспатчить).
     * @param IncomingMessage $message Входящее сообщение
     * @return bool
     * @throws \Throwable При ошибках хранилища или драйвера
     */
    public function handle(IncomingMessage $message): bool
    {
        $stateRecord = $this->storage->get($message->chatId, $message->driverName);

        if ($stateRecord === null) {
            return false;
        }

        $flowClass = $stateRecord['flow_class'] ?? null;

        if ($flowClass === null || ! class_exists($flowClass)) {
            // Запись принадлежит контроллеру (например, controller_kb_ctx) — не удаляем,
            // даём роутеру задиспатчить сообщение в обычном порядке.
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
