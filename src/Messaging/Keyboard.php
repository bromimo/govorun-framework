<?php

namespace Govorun\Messaging;

class Keyboard
{
    private array $rows = [];
    private array $currentRow = [];
    private string $type = 'inline';
    private bool $remove = false;

    public static function make(): static
    {
        return new static();
    }

    public static function reply(): static
    {
        $kb = new static();
        $kb->type = 'reply';

        return $kb;
    }

    public static function remove(): static
    {
        $kb = new static();
        $kb->remove = true;

        return $kb;
    }

    public function button(
        string $text,
        ?string $action = null,
        ?array $param = null,
        ?string $url = null,
        bool $requestContact = false,
        bool $requestLocation = false,
    ): static {
        $this->currentRow[] = new Button($text, $action, $param, $url, $requestContact, $requestLocation);

        return $this;
    }

    public function row(): static
    {
        if (! empty($this->currentRow)) {
            $this->rows[] = $this->currentRow;
            $this->currentRow = [];
        }

        return $this;
    }

    public function toArray(): array
    {
        $rows = $this->rows;

        if (! empty($this->currentRow)) {
            $rows[] = $this->currentRow;
        }

        return [
            'type' => $this->type,
            'remove' => $this->remove,
            'rows' => array_map(
                fn (array $row) => array_map(
                    fn (Button $btn) => $btn->toArray(),
                    $row
                ),
                $rows
            ),
        ];
    }
}
