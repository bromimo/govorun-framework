<?php

namespace Govorun\Messaging;

/** Фабрика для создания исходящих сообщений с медиаконтентом. */
class Media
{
    /** Создать исходящее сообщение с фотографией.
     * @param string $url URL-адрес фотографии.
     * @return OutgoingMessage
     */
    public static function photo(string $url): OutgoingMessage
    {
        $msg = new OutgoingMessage();
        $msg->media = ['type' => 'photo', 'url' => $url];

        return $msg;
    }

    /** Создать исходящее сообщение с документом.
     * @param string $url URL-адрес документа.
     * @return OutgoingMessage
     */
    public static function document(string $url): OutgoingMessage
    {
        $msg = new OutgoingMessage();
        $msg->media = ['type' => 'document', 'url' => $url];

        return $msg;
    }

    /** Создать исходящее сообщение с голосовым сообщением.
     * @param string $url URL-адрес голосового файла.
     * @return OutgoingMessage
     */
    public static function voice(string $url): OutgoingMessage
    {
        $msg = new OutgoingMessage();
        $msg->media = ['type' => 'voice', 'url' => $url];

        return $msg;
    }
}
