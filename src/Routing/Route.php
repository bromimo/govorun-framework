<?php

namespace Govorun\Routing;

/** Статический DSL для определения маршрутов бота.
 * Предоставляет набор статических методов для регистрации
 * маршрутов различных типов: команды, фразы, события и т.д.
 */
class Route
{
    /** @var RouteEntry[] */
    protected static array $routes = [];
    /** @var string[] */
    protected static array $middlewareStack = [];

    /** Зарегистрировать маршрут для команды (например, /start).
     * Имя команды хранится с ведущим слешем; если вызывающий передал имя без слеша,
     * он будет добавлен автоматически.
     * @param string $name Имя команды (со слешем или без — будет нормализовано к виду /name)
     * @param mixed $action Обработчик маршрута
     * @return RouteEntry
     */
    public static function command(string $name, mixed $action): RouteEntry
    {
        if (! str_starts_with($name, '/')) {
            $name = '/' . $name;
        }
        return static::addRoute('command', $name, $action);
    }

    /** Зарегистрировать маршрут для текстовой фразы.
     * Если передан Closure, создаётся группа вложенных маршрутов.
     * @param string $phrase Фраза для поиска в тексте
     * @param mixed $action Обработчик маршрута или Closure с дочерними маршрутами
     * @return RouteEntry
     */
    public static function phrase(string $phrase, mixed $action): RouteEntry
    {
        if ($action instanceof \Closure) {
            $entry = new RouteEntry('phrase', $phrase, null);
            $entry->middleware = static::$middlewareStack;
            $previousRoutes = static::$routes;
            static::$routes = [];
            $action();
            $entry->children = static::$routes;
            static::$routes = $previousRoutes;
            static::$routes[] = $entry;
            return $entry;
        }
        return static::addRoute('phrase', $phrase, $action);
    }

    /** Зарегистрировать маршрут для регулярного выражения.
     * @param string $regex Регулярное выражение для проверки текста
     * @param mixed $action Обработчик маршрута
     * @return RouteEntry
     */
    public static function pattern(string $regex, mixed $action): RouteEntry
    {
        return static::addRoute('pattern', $regex, $action);
    }

    /** Зарегистрировать маршрут для callback-действия (action).
     * @param string $name Имя действия
     * @param mixed $action Обработчик маршрута
     * @return RouteEntry
     */
    public static function action(string $name, mixed $action): RouteEntry
    {
        return static::addRoute('action', $name, $action);
    }

    /** Зарегистрировать маршрут для события.
     * @param string $name Имя события
     * @param mixed $action Обработчик маршрута
     * @return RouteEntry
     */
    public static function event(string $name, mixed $action): RouteEntry
    {
        return static::addRoute('event', $name, $action);
    }

    /** Зарегистрировать маршрут для медиафайла определённого типа.
     * @param string $type Тип медиа (photo, video, document и т.д.)
     * @param mixed $action Обработчик маршрута
     * @return RouteEntry
     */
    public static function media(string $type, mixed $action): RouteEntry
    {
        return static::addRoute('media', $type, $action);
    }

    /** Зарегистрировать маршрут для получения геолокации.
     * @param mixed $action Обработчик маршрута
     * @return RouteEntry
     */
    public static function location(mixed $action): RouteEntry
    {
        return static::addRoute('location', null, $action);
    }

    /** Зарегистрировать маршрут для получения контакта.
     * @param mixed $action Обработчик маршрута
     * @return RouteEntry
     */
    public static function contact(mixed $action): RouteEntry
    {
        return static::addRoute('contact', null, $action);
    }

    /** Зарегистрировать маршрут для реферального кода.
     * @param string $code Реферальный код
     * @param mixed $action Обработчик маршрута
     * @return RouteEntry
     */
    public static function referral(string $code, mixed $action): RouteEntry
    {
        return static::addRoute('referral', $code, $action);
    }

    /** Зарегистрировать маршрут-заглушку для необработанных сообщений.
     * @param mixed $action Обработчик маршрута
     * @return RouteEntry
     */
    public static function fallback(mixed $action): RouteEntry
    {
        return static::addRoute('fallback', null, $action);
    }

    /** Обернуть группу маршрутов в middleware.
     * @param string $middleware Полное имя класса middleware
     * @param \Closure $callback Callback с определением маршрутов
     * @return void
     */
    public static function middleware(string $middleware, \Closure $callback): void
    {
        static::$middlewareStack[] = $middleware;
        $callback();
        array_pop(static::$middlewareStack);
    }

    /** Получить все зарегистрированные маршруты.
     * @return RouteEntry[]
     */
    public static function getRoutes(): array
    {
        return static::$routes;
    }

    /** Очистить все маршруты и стек middleware.
     * @return void
     */
    public static function clear(): void
    {
        static::$routes = [];
        static::$middlewareStack = [];
    }

    /** Добавить маршрут в реестр.
     * @param string $type Тип маршрута
     * @param string|null $value Значение для сопоставления
     * @param mixed $action Обработчик маршрута
     * @return RouteEntry
     */
    protected static function addRoute(string $type, ?string $value, mixed $action): RouteEntry
    {
        $entry = new RouteEntry($type, $value, $action);
        $entry->middleware = static::$middlewareStack;
        static::$routes[] = $entry;
        return $entry;
    }
}
