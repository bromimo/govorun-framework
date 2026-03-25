<?php

namespace Govorun\Messaging;

class Message
{
    public static function make(string $text): OutgoingMessage
    {
        $msg = new OutgoingMessage();
        $msg->text = $text;

        return $msg;
    }
}
