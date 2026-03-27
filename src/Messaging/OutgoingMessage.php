<?php

namespace Govorun\Messaging;

/** Исходящее сообщение, отправляемое пользователю через мессенджер. */
class OutgoingMessage
{
    public string $chatId = '';
    public ?string $text = null;
    public ?string $parseMode = null;
    public ?array $keyboard = null;
    public ?array $media = null;

    /** Прикрепить клавиатуру к сообщению.
     * @param Keyboard $keyboard Объект клавиатуры.
     * @return static
     */
    public function keyboard(Keyboard $keyboard): static
    {
        $this->keyboard = $keyboard->toArray();

        return $this;
    }

    /** Установить режим разметки текста (HTML, Markdown и т.д.).
     * @param string $mode Режим разметки.
     * @return static
     */
    public function parseMode(string $mode): static
    {
        $this->parseMode = $mode;

        return $this;
    }

    /** Установить подпись (caption) для медиасообщения.
     * @param string $text Текст подписи.
     * @return static
     */
    public function caption(string $text): static
    {
        $this->text = $text;

        return $this;
    }
}
