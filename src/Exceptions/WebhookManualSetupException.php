<?php

namespace Govorun\Exceptions;

/** Драйвер не устанавливает вебхук автоматически — требуется ручная настройка.
 * Команды webhook:install/webhook:remove трактуют это как предупреждение, а не ошибку.
 */
class WebhookManualSetupException extends \RuntimeException
{
}