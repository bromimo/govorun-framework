<?php

namespace Govorun\State;

use Govorun\Messaging\Message;
use Govorun\Support\Validator;
use Govorun\Messaging\Keyboard;
use Govorun\Messaging\ContentType;
use Govorun\Contracts\StateStorage;
use Govorun\Contracts\MessengerDriver;
use Govorun\Messaging\IncomingMessage;

/** Абстрактный диалоговый поток (Flow).
 * Базовый класс для пошаговых диалогов с пользователем.
 * Управляет переходами между шагами, хранением состояния,
 * прерыванием по командам и событиям.
 */
abstract class Flow
{
    protected array $steps = [];
    protected array $interruptCommands = ['/start', '/cancel'];
    protected bool $interruptOnEvent = true;

    protected StateData $state;
    private string $chatId;
    private string $driverName;

    /** Создать экземпляр потока.
     * @param StateStorage $storage Хранилище состояния
     * @param MessengerDriver $driver Драйвер мессенджера
     * @param IncomingMessage $message Входящее сообщение
     */
    public function __construct(
        protected StateStorage $storage,
        protected MessengerDriver $driver,
        protected IncomingMessage $message,
    ) {
        $this->chatId = $message->chatId;
        $this->driverName = $message->driverName;
        $this->state = new StateData($this->loadData());
    }

    /** Запустить поток с первого шага.
     * @return void
     * @throws \Throwable При ошибках хранилища или драйвера
     */
    public function start(): void
    {
        $firstStep = $this->steps[0] ?? null;

        if ($firstStep === null) {
            return;
        }

        $this->saveState($firstStep);
        $this->executeAsk($firstStep);
    }

    /** Возобновить поток с текущего шага.
     * Загружает сохранённое состояние и вызывает receive-коллбэк текущего шага.
     * @return void
     * @throws \Throwable При ошибках хранилища или драйвера
     */
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

    /** Проверить, должен ли поток быть прерван входящим сообщением.
     * @param IncomingMessage $message Входящее сообщение
     * @return bool
     */
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

    /** Перейти к следующему шагу потока.
     * Если передано имя — прыгает на указанный шаг (должен быть в $steps).
     * Без аргумента — переходит к следующему по массиву $steps; если текущий последний — завершает поток.
     * @param ?string $name Имя шага для явного перехода
     * @return void
     * @throws \InvalidArgumentException Если указанный шаг отсутствует в $steps
     * @throws \Throwable При ошибках хранилища или драйвера
     */
    protected function nextStep(?string $name = null): void
    {
        $this->finalizeAskKeyboard($this->message->action ?? null, false);

        if ($name !== null) {
            if (! in_array($name, $this->steps, true)) {
                throw new \InvalidArgumentException(
                    "Step '{$name}' not found in flow " . static::class
                );
            }
            $this->saveState($name);
            $this->executeAsk($name);
            return;
        }

        $stateRecord = $this->storage->get($this->chatId, $this->driverName);
        $currentStep = $stateRecord['current_step'] ?? $this->steps[0];
        $currentIndex = array_search($currentStep, $this->steps);

        if ($currentIndex === false || $currentIndex >= count($this->steps) - 1) {
            $this->completeFlow();
            return;
        }

        $nextStep = $this->steps[$currentIndex + 1];
        $this->saveState($nextStep);
        $this->executeAsk($nextStep);
    }

    /** Завершить поток: очистить состояние и вызвать onComplete().
     * @return void
     * @throws \Throwable При ошибке хранилища
     */
    protected function completeFlow(): void
    {
        $this->finalizeAskKeyboard($this->message->action ?? null, false);
        $this->storage->delete($this->chatId, $this->driverName);
        $this->onComplete();
    }

    /** Отправить текстовый ответ пользователю.
     * @param string $text Текст сообщения
     * @return void
     * @throws \Throwable При ошибке отправки через драйвер
     */
    protected function reply(string $text): void
    {
        $msg = Message::make($text);
        $msg->chatId = $this->chatId;
        $this->driver->send($msg);
    }

    /** Создать валидатор с автоматической отправкой ошибки пользователю.
     * @param ?string $value Проверяемое значение
     */
    protected function validator(?string $value): Validator
    {
        return Validator::make($value)->withErrorHandler(fn (string $error) => $this->reply($error));
    }

    /** Обработчик завершения потока.
     * @return void
     */
    public function onComplete(): void {}

    /** Обработчик отмены потока.
     * Редактирует последнюю ask_keyboard (если активна) и удаляет состояние из хранилища.
     * @return void
     * @throws \Throwable При ошибке хранилища
     */
    public function onCancel(): void
    {
        $this->finalizeAskKeyboard(null, true);
        $this->storage->delete($this->chatId, $this->driverName);
    }

    /** Выполнить ask-фазу указанного шага.
     * @param string $stepName Имя шага
     * @return void
     * @throws \Throwable При ошибке отправки через драйвер
     */
    private function executeAsk(string $stepName): void
    {
        $step = new Step();
        $method = $stepName . 'Step';

        if (! method_exists($this, $method)) {
            return;
        }

        $this->$method($step);

        $askText = $step->getAskText();

        if ($askText === null) {
            return;
        }

        $askCallback = $step->getAskCallback();

        if ($askCallback === null) {
            $this->reply($askText);
            return;
        }

        $keyboard = $askCallback->call($this);
        $msg = Message::make($askText)->keyboard($keyboard);
        $msg->chatId = $this->chatId;

        $sentId = $this->driver->send($msg);

        $this->captureAskKeyboardContext($sentId, $askText, $msg->parseMode, $keyboard);
    }

    /** Сохранить контекст отправленного ask_keyboard для последующего edit.
     * Пишет только для inline-клавиатур с callback-кнопками и при не-null message_id.
     * @param ?string $messageId Id отправленного сообщения от драйвера
     * @param string $originalText Исходный текст ask
     * @param ?string $parseMode Режим разметки
     * @param Keyboard $keyboard Клавиатура
     * @return void
     * @throws \Throwable При ошибке хранилища
     */
    private function captureAskKeyboardContext(
        ?string $messageId,
        string $originalText,
        ?string $parseMode,
        Keyboard $keyboard,
    ): void {
        if ($messageId === null) {
            return;
        }

        $data = $keyboard->toArray();

        if ($data['type'] !== 'inline' || $data['remove']) {
            return;
        }

        $labelMap = [];

        foreach ($data['rows'] as $row) {
            foreach ($row as $btn) {
                if (isset($btn['action'])) {
                    $labelMap[$btn['action']] = $btn['text'];
                }
            }
        }

        if (empty($labelMap)) {
            return;
        }

        $this->state->set('__ask_keyboard_ctx', [
            'message_id' => $messageId,
            'original_text' => $originalText,
            'parse_mode' => $parseMode,
            'label_map' => $labelMap,
        ]);

        $this->storage->set($this->chatId, $this->driverName, [
            'flow_class' => static::class,
            'current_step' => $this->currentStepName(),
            'data' => $this->state->all(),
        ]);
    }

    /** Завершить активную ask_keyboard: редактировать исходное сообщение (убрать клавиатуру,
     * дописать «(выбрано: X)» или «(отменено)») и очистить контекст.
     * @param ?string $selectedAction Action нажатой кнопки или null, если нет выбора
     * @param bool $cancelled Признак отмены (onCancel path)
     * @return void
     */
    private function finalizeAskKeyboard(?string $selectedAction, bool $cancelled = false): void
    {
        if (! $this->state->has('__ask_keyboard_ctx')) {
            return;
        }

        $ctx = $this->state->get('__ask_keyboard_ctx');

        if (! $cancelled && ($selectedAction === null || $selectedAction === '')) {
            $this->clearAskKeyboardContext();
            return;
        }

        $suffix = $cancelled
            ? '(отменено)'
            : '(выбрано: ' . ($ctx['label_map'][$selectedAction] ?? $selectedAction) . ')';

        $msg = Message::make($ctx['original_text'] . "\n\n" . $suffix);
        $msg->chatId = $this->chatId;

        if ($ctx['parse_mode'] !== null) {
            $msg->parseMode($ctx['parse_mode']);
        }

        try {
            $this->driver->edit($ctx['message_id'], $msg);
        } catch (\Throwable) {
            // Мёртвый edit не должен ронять Flow.
        }

        $this->clearAskKeyboardContext();
    }

    /** Удалить контекст ask_keyboard из state и сохранить.
     * @return void
     * @throws \Throwable При ошибке хранилища
     */
    private function clearAskKeyboardContext(): void
    {
        $data = $this->state->all();
        unset($data['__ask_keyboard_ctx']);
        $this->state = new StateData($data);

        $record = $this->storage->get($this->chatId, $this->driverName);

        if ($record !== null) {
            $this->storage->set($this->chatId, $this->driverName, [
                'flow_class' => $record['flow_class'],
                'current_step' => $record['current_step'],
                'data' => $data,
            ]);
        }
    }

    /** Получить имя текущего шага из хранилища.
     * @return string
     * @throws \Throwable При ошибке хранилища
     */
    private function currentStepName(): string
    {
        $record = $this->storage->get($this->chatId, $this->driverName);

        return $record['current_step'] ?? $this->steps[0];
    }

    /** Сохранить текущее состояние потока в хранилище.
     * @param string $currentStep Имя текущего шага
     * @return void
     * @throws \Throwable При ошибке хранилища
     */
    private function saveState(string $currentStep): void
    {
        $this->storage->set($this->chatId, $this->driverName, [
            'flow_class' => static::class,
            'current_step' => $currentStep,
            'data' => $this->state->all(),
        ]);
    }

    /** Загрузить данные состояния из хранилища.
     * @return array
     * @throws \Throwable При ошибке хранилища
     */
    private function loadData(): array
    {
        $stateRecord = $this->storage->get($this->chatId, $this->driverName);

        return $stateRecord['data'] ?? [];
    }
}

/** Контейнер данных состояния потока.
 * Хранит произвольные данные, собранные в процессе прохождения шагов Flow.
 */
class StateData
{
    /** Создать экземпляр контейнера данных.
     * @param array $data Начальные данные
     */
    public function __construct(private array $data = []) {}

    /** Получить значение по ключу.
     * @param string $key Ключ
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /** Установить значение по ключу.
     * @param string $key Ключ
     * @param mixed $value Значение
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /** Проверить наличие ключа в данных.
     * @param string $key Ключ
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /** Получить все данные.
     * @return array
     */
    public function all(): array
    {
        return $this->data;
    }
}
