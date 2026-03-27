<?php

namespace Govorun\Testing;

use Govorun\Routing\Route;
use Govorun\Foundation\Application;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/** Базовый TestCase для тестирования Govorun-ботов.
 * Создаёт Application в setUp(), подключает трейты для fakeMessenger() и fakeApi().
 */
abstract class TestCase extends PHPUnitTestCase
{
    use InteractsWithMessenger, InteractsWithApi;

    /** @var Application */
    protected Application $app;

    /** Инициализация тестового окружения.
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Route::clear();
        $this->app = $this->createApplication();
    }

    /** Очистка после теста.
     * @return void
     */
    protected function tearDown(): void
    {
        Route::clear();
        Application::setInstance(null);
        parent::tearDown();
    }

    /** Создаёт и настраивает экземпляр Application.
     * @return Application
     */
    protected function createApplication(): Application
    {
        $app = new Application($this->basePath());
        $app->loadConfiguration();
        $app->registerCoreProviders();
        $app->boot();

        return $app;
    }

    /** Определяет базовый путь проекта.
     * @return string
     */
    protected function basePath(): string
    {
        return defined('GOVORUN_BASE_PATH')
            ? GOVORUN_BASE_PATH
            : (function_exists('base_path') ? base_path() : getcwd());
    }
}
