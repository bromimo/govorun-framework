<?php

namespace Govorun\Exceptions;

use RuntimeException;

/** Исключение неудачной отправки сообщения.
 * Выбрасывается, когда сообщение не удалось доставить
 * через драйвер мессенджера.
 */
class SendFailedException extends RuntimeException
{
}
