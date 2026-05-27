<?php

namespace Govorun\Http;

/** HTTP-ответ на входящий вебхук мессенджера.
 * Хранит код статуса и тело, выводимые точкой входа.
 */
final class WebhookResponse
{
    /** Создать ответ.
     * @param int $status HTTP-код статуса
     * @param string $body Тело ответа
     */
    public function __construct(
        public int $status,
        public string $body = '',
    ) {}

    /** Успешный ответ 200 с опциональным телом.
     * @param string $body Тело ответа
     * @return self
     */
    public static function ok(string $body = ''): self
    {
        return new self(200, $body);
    }

    /** Ответ 403 с пустым телом.
     * @return self
     */
    public static function forbidden(): self
    {
        return new self(403, '');
    }

    /** Ответ 200 с заданным текстовым телом.
     * @param string $body Тело ответа
     * @return self
     */
    public static function text(string $body): self
    {
        return new self(200, $body);
    }
}