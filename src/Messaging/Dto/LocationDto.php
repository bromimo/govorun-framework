<?php

namespace Govorun\Messaging\Dto;

/** DTO геолокации из входящего сообщения. */
class LocationDto
{
    /** Создать экземпляр DTO геолокации.
     * @param float $latitude  Широта.
     * @param float $longitude Долгота.
     * @param array $raw       Сырые данные из мессенджера.
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
        public array $raw = [],
    ) {}
}
