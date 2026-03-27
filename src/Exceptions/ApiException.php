<?php

namespace Govorun\Exceptions;

use RuntimeException;

/** Исключение ошибки API-запроса.
 * Выбрасывается при неудачном выполнении запроса к внешнему API:
 * ошибки HTTP-статусов, таймауты и сетевые сбои.
 */
class ApiException extends RuntimeException
{
}
