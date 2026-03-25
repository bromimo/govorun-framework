<?php

namespace Govorun\Messaging;

enum ContentType: string
{
    case Text = 'text';
    case Action = 'action';
    case Media = 'media';
    case Location = 'location';
    case Contact = 'contact';
    case Event = 'event';
}
