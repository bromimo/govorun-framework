<?php

namespace Govorun\State;

use Closure;
use Govorun\Messaging\OutgoingMessage;

/** Шаг диалогового потока.
 * Описывает один шаг (вопрос-ответ) внутри Flow:
 * сообщение вопроса, коллбэк для клавиатуры и коллбэк для обработки ответа.
 */
class Step
{
    private string|OutgoingMessage|null $ask = null;
    private ?Closure $askCallback = null;
    private ?Closure $receiveCallback = null;

    /** Задать вопрос шага и опциональный построитель клавиатуры.
     * @param string|OutgoingMessage $message Текст вопроса или готовое исходящее сообщение (media, parseMode и т.п.).
     * @param Closure|null $keyboardBuilder Коллбэк для создания клавиатуры.
     * @return void
     */
    public function ask(string|OutgoingMessage $message, ?Closure $keyboardBuilder = null): void
    {
        $this->ask = $message;
        $this->askCallback = $keyboardBuilder;
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

    /** Получить коллбэк построителя клавиатуры.
     * @return Closure|null
     */
    public function getAskCallback(): ?Closure
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
