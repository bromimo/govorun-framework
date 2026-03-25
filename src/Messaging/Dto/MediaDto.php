<?php

namespace Govorun\Messaging\Dto;

class MediaDto
{
    public function __construct(
        public string $type,
        public ?string $url = null,
        public ?string $fileId = null,
        public ?string $mimeType = null,
        public ?int $fileSize = null,
        public array $raw = [],
    ) {}
}
