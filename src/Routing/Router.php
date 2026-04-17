<?php

namespace Govorun\Routing;

use Govorun\Messaging\ContentType;
use Govorun\Contracts\MessengerDriver;
use Govorun\Messaging\IncomingMessage;

/** Маршрутизатор входящих сообщений.
 * Сопоставляет входящее сообщение с зарегистрированными маршрутами
 * по приоритету типов и вызывает соответствующий обработчик,
 * пропуская его через цепочку middleware.
 */
class Router
{
    private MiddlewarePipeline $pipeline;

    /** Создать экземпляр маршрутизатора.
     * @param MessengerDriver $driver Драйвер мессенджера
     */
    public function __construct(
        private MessengerDriver $driver,
    ) {
        $this->pipeline = new MiddlewarePipeline();
    }

    /** Диспетчеризовать входящее сообщение: найти подходящий маршрут и выполнить его.
     * @param IncomingMessage $message Входящее сообщение
     * @return void
     */
    public function dispatch(IncomingMessage $message): void
    {
        $routes = Route::getRoutes();
        $matched = $this->matchRoute($message, $routes);

        if ($matched === null) {
            return;
        }

        [$entry, $middleware] = $matched;

        $this->pipeline->run($message, $middleware, function (IncomingMessage $msg) use ($entry) {
            $this->callAction($entry->action, $msg);
        });
    }

    /** Найти подходящий маршрут среди списка по приоритету типов.
     * @param IncomingMessage $message Входящее сообщение
     * @param array $routes Массив маршрутов для поиска
     * @return array{RouteEntry, string[]}|null
     */
    private function matchRoute(IncomingMessage $message, array $routes): ?array
    {
        $priorityOrder = ['event', 'command', 'action', 'referral', 'media', 'location', 'contact', 'pattern', 'phrase', 'fallback'];

        foreach ($priorityOrder as $type) {
            foreach ($routes as $entry) {
                if ($entry->type !== $type) {
                    continue;
                }
                $result = $this->matchEntry($message, $entry);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /** Проверить, совпадает ли конкретный маршрут с сообщением.
     * @param IncomingMessage $message Входящее сообщение
     * @param RouteEntry $entry Запись маршрута для проверки
     * @return array{RouteEntry, string[]}|null
     */
    private function matchEntry(IncomingMessage $message, RouteEntry $entry): ?array
    {
        // Handle phrase with children first (nested routes)
        if ($entry->type === 'phrase' && !empty($entry->children) && $this->phraseTextMatches($message, $entry)) {
            $childResult = $this->matchRoute($message, $entry->children);
            if ($childResult !== null) {
                [$childEntry, $childMiddleware] = $childResult;
                return [$childEntry, array_merge($entry->middleware, $childMiddleware)];
            }
            if ($entry->action !== null) {
                return [$entry, $entry->middleware];
            }
            return null;
        }

        $matches = match ($entry->type) {
            'event'    => $message->type === ContentType::Event && $message->event === $entry->value,
            'command'  => $this->matchCommand($message, $entry),
            'action'   => $message->type === ContentType::Action && $message->action === $entry->value,
            'referral' => $message->referral !== null && $message->referral === $entry->value,
            'media'    => $message->type === ContentType::Media && $message->media?->type === $entry->value,
            'location' => $message->type === ContentType::Location && $message->location !== null,
            'contact'  => $message->type === ContentType::Contact && $message->contact !== null,
            'pattern'  => $message->text !== null && preg_match($entry->value, $message->text) === 1,
            'phrase'   => $this->phraseTextMatches($message, $entry),
            'fallback' => true,
            default    => false,
        };

        if ($matches && $entry->action !== null) {
            return [$entry, $entry->middleware];
        }

        return null;
    }

    /** Проверить совпадение текста сообщения с командой.
     * @param IncomingMessage $message Входящее сообщение
     * @param RouteEntry $entry Запись маршрута команды
     * @return bool
     */
    private function matchCommand(IncomingMessage $message, RouteEntry $entry): bool
    {
        if ($message->text === null) {
            return false;
        }
        $text = $message->text;
        $command = $entry->value;
        return $text === $command || str_starts_with($text, $command . ' ');
    }

    /** Проверить совпадение текста сообщения с фразой или её алиасами.
     * @param IncomingMessage $message Входящее сообщение
     * @param RouteEntry $entry Запись маршрута фразы
     * @return bool
     */
    private function phraseTextMatches(IncomingMessage $message, RouteEntry $entry): bool
    {
        if ($message->text === null) {
            return false;
        }
        $text = mb_strtolower($message->text);
        if (str_contains($text, mb_strtolower($entry->value))) {
            return true;
        }
        foreach ($entry->aliases as $alias) {
            if (str_contains($text, mb_strtolower($alias))) {
                return true;
            }
        }
        return false;
    }

    /** Вызвать обработчик маршрута (контроллер или массив [класс, метод]).
     * @param mixed $action Обработчик маршрута
     * @param IncomingMessage $message Входящее сообщение
     * @return void
     */
    private function callAction(mixed $action, IncomingMessage $message): void
    {
        if (is_string($action)) {
            $controller = new $action();
            if ($controller instanceof Controller) {
                $controller->setContext($message, $this->driver);
            }
            $method = method_exists($controller, 'handle') ? 'handle' : '__invoke';
            $controller->$method();
            return;
        }

        if (is_array($action) && count($action) === 2) {
            [$class, $method] = $action;
            $controller = new $class();
            if ($controller instanceof Controller) {
                $controller->setContext($message, $this->driver);
            }
            $controller->$method();
        }
    }
}
