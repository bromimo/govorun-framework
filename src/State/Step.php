<?php

namespace Govorun\State;

use Closure;

/** Шаг диалогового потока.
 * Описывает один шаг (вопрос-ответ) внутри Flow:
 * текст вопроса, коллбэк для клавиатуры и коллбэк для обработки ответа.
 */
class Step
{
    private ?string $askText = null;
    private ?Closure $askCallback = null;
    private ?Closure $receiveCallback = null;

    /** Задать текст вопроса и опциональный построитель клавиатуры.
     * @param string $text Текст вопроса пользователю
     * @param Closure|null $keyboardBuilder Коллбэк для создания клавиатуры
     * @return void
     */
    public function ask(string $text, ?Closure $keyboardBuilder = null): void
    {
        $this->askText = $text;
        $this->askCallback = $keyboardBuilder;
    }

    /** Задать коллбэк для обработки полученного ответа.
     * @param Closure $callback Коллбэк обработки входящего сообщения
     * @return void
     */
    public function receive(Closure $callback): void
    {
        $this->receiveCallback = $callback;
    }

    /** Получить текст вопроса.
     * @return string|null
     */
    public function getAskText(): ?string
    {
        return $this->askText;
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
