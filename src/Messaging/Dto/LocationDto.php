<?php

namespace Govorun\Messaging\Dto;

class LocationDto
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public array $raw = [],
    ) {}
}
