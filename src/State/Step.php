<?php

namespace Govorun\State;

use Closure;
use Govorun\Messaging\Keyboard;
use Govorun\Messaging\OutgoingMessage;

/** Шаг диалогового потока.
 * Описывает один шаг (вопрос-ответ) внутри Flow:
 * сообщение вопроса, коллбэк для клавиатуры и коллбэк для обработки ответа.
 */
class Step
{
    private string|OutgoingMessage|null $ask = null;
    private Closure|Keyboard|null $askCallback = null;
    private ?Closure $receiveCallback = null;

    /** Задать вопрос шага и опциональную клавиатуру.
     * @param string|OutgoingMessage $message Текст вопроса или готовое исходящее сообщение
     * @param Closure|Keyboard|null $keyboard Клавиатура напрямую или замыкание-билдер (для доступа к $this в Flow)
     * @return void
     */
    public function ask(
        string|OutgoingMessage $message,
        Closure|Keyboard|null $keyboard = null,
    ): void {
        $this->ask = $message;
        $this->askCallback = $keyboard;
    }

    /** Задать коллбэк для обработки полученного ответа.
     * @param Closure $callback Коллбэк обработки входящего сообщения.
     * @return void
     */
    public function receive(Closure $callback): void
    {
        $this->receiveCallback = $callback;
    }

    /** Получить сообщение вопроса (строка или OutgoingMessage).
     * @return string|OutgoingMessage|null
     */
    public function getAsk(): string|OutgoingMessage|null
    {
        return $this->ask;
    }

    /** Получить текст вопроса (для обратной совместимости).
     * Возвращает строку для string-ask, caption для OutgoingMessage.
     * @return string|null
     */
    public function getAskText(): ?string
    {
        if ($this->ask === null) {
            return null;
        }

        if (is_string($this->ask)) {
            return $this->ask;
        }

        return $this->ask->text;
    }

    /** Получить клавиатуру или её билдер.
     * @return Closure|Keyboard|null
     */
    public function getAskCallback(): Closure|Keyboard|null
    {
        return $this->askCallback;
    }

    /** Получить коллбэк обработки ответа.
     * @return Closure|null
     */
    public function getReceiveCallback(): ?Closure
    {
        return $this->receiveCallback;
    }
}
