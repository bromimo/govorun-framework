<?php

namespace Govorun\Contracts;

use Govorun\Http\Request;
use Govorun\Http\WebhookResponse;

/** Драйвер, формирующий собственное тело HTTP-ответа на вебхук.
 * Используется платформами (например, VK Callback API), которым требуется
 * специфический ответ: строка-подтверждение или подтверждающее тело.
 */
interface WebhookResponder
{
    /** Сформировать ответ до парсинга и диспатча сообщения.
     * @param Request $request Входящий HTTP-запрос
     * @return WebhookResponse|null Ответ для short-circuit или null для обычной обработки
     */
    public function preflight(Request $request): ?WebhookResponse;

    /** Тело ответа после успешной обработки события.
     * @return string Тело (например, "ok")
     */
    public function ackBody(): string;
}