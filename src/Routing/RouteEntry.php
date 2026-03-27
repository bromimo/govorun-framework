<?php

namespace Govorun\Routing;

/** Запись маршрута, содержащая тип, значение и обработчик.
 * Хранит метаданные одного маршрута: тип сопоставления,
 * значение для проверки, действие-обработчик, алиасы,
 * middleware и дочерние маршруты.
 */
class RouteEntry
{
    public array $aliases = [];
    public array $middleware = [];
    public array $children = [];

    /** Создать запись маршрута.
     * @param string $type Тип маршрута (command, phrase, pattern и т.д.)
     * @param string|null $value Значение для сопоставления
     * @param mixed $action Обработчик маршрута
     */
    public function __construct(
        public string $type,
        public ?string $value,
        public mixed $action,
    ) {}

    /** Задать алиасы (синонимы) для маршрута.
     * @param array $aliases Массив строк-алиасов
     * @return static
     */
    public function alias(array $aliases): static
    {
        $this->aliases = $aliases;
        return $this;
    }
}
