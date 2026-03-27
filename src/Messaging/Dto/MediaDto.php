<?php

namespace Govorun\Messaging\Dto;

/** DTO медиаконтента входящего сообщения. */
class MediaDto
{
    /** Создать экземпляр DTO медиаконтента.
     * @param string      $type     Тип медиа (photo, document, voice и т.д.).
     * @param string|null $url      URL-адрес файла.
     * @param string|null $fileId   Идентификатор файла в мессенджере.
     * @param string|null $mimeType MIME-тип файла.
     * @param int|null    $fileSize Размер файла в байтах.
     * @param array       $raw      Сырые данные из мессенджера.
     */
    public function __construct(
        public string $type,
        public ?string $url = null,
        public ?string $fileId = null,
        public ?string $mimeType = null,
        public ?int $fileSize = null,
        public array $raw = [],
    ) {}
}
