<?php

use Govorun\Foundation\Application;

if (! function_exists('app')) {
    /** Получить экземпляр приложения или разрешить абстракцию из контейнера.
     * @param string|null $abstract  Имя абстракции для разрешения
     * @param array       $parameters Параметры для разрешения
     * @return mixed
     */
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $instance = Application::getInstance();

        if ($abstract === null) {
            return $instance;
        }

        return $instance->make($abstract, $parameters);
    }
}

if (! function_exists('config')) {
    /** Получить/установить значение конфигурации.
     * @param string|array|null $key     Ключ конфигурации, массив для установки, или null для получения репозитория
     * @param mixed             $default Значение по умолчанию
     * @return mixed
     */
    function config(string|array|null $key = null, mixed $default = null): mixed
    {
        $config = app('config');

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $config->set($k, $v);
            }
            return null;
        }

        if ($key === null) {
            return $config;
        }

        return $config->get($key, $default);
    }
}

if (! function_exists('env')) {
    /** Получить значение переменной окружения с приведением типов.
     * @param string $key     Имя переменной окружения
     * @param mixed  $default Значение по умолчанию
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;

        if ($value === null) {
            $value = getenv($key);
            if ($value === false) {
                return $default;
            }
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (! function_exists('base_path')) {
    /** Получить базовый путь проекта или путь относительно него.
     * @param string $path Относительный путь
     * @return string
     */
    function base_path(string $path = ''): string
    {
        return app()->basePath($path);
    }
}

if (! function_exists('config_path')) {
    /** Получить путь к директории конфигурации или файлу в ней.
     * @param string $path Относительный путь
     * @return string
     */
    function config_path(string $path = ''): string
    {
        return app()->configPath($path);
    }
}

if (! function_exists('storage_path')) {
    /** Получить путь к директории хранилища или файлу в ней.
     * @param string $path Относительный путь
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        return app()->storagePath($path);
    }
}

if (! function_exists('database_path')) {
    /** Получить путь к директории базы данных или файлу в ней.
     * @param string $path Относительный путь
     * @return string
     */
    function database_path(string $path = ''): string
    {
        return app()->databasePath($path);
    }
}

if (! function_exists('event')) {
    /** Диспетчеризовать событие и вызвать его слушателей.
     * @param string|object $event   Имя или экземпляр события
     * @param mixed         $payload Данные события
     * @param bool          $halt    Остановить распространение при первом не-null ответе
     * @return mixed
     */
    function event(string|object $event, mixed $payload = [], bool $halt = false): mixed
    {
        return app('events')->dispatch($event, $payload, $halt);
    }
}
