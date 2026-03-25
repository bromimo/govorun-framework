<?php

namespace Govorun\Messaging;

class Media
{
    public static function photo(string $url): OutgoingMessage
    {
        $msg = new OutgoingMessage();
        $msg->media = ['type' => 'photo', 'url' => $url];

        return $msg;
    }

    public static function document(string $url): OutgoingMessage
    {
        $msg = new OutgoingMessage();
        $msg->media = ['type' => 'document', 'url' => $url];

        return $msg;
    }

    public static function voice(string $url): OutgoingMessage
    {
        $msg = new OutgoingMessage();
        $msg->media = ['type' => 'voice', 'url' => $url];

        return $msg;
    }
}
