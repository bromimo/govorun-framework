<?php

namespace Govorun\Messaging;

/** Фабрика для создания исходящих текстовых сообщений. */
class Message
{
    /** Создать исходящее сообщение с указанным текстом.
     * @param string $text Текст сообщения.
     * @return OutgoingMessage
     */
    public static function make(string $text): OutgoingMessage
    {
        $msg = new OutgoingMessage();
        $msg->text = $text;

        return $msg;
    }
}
