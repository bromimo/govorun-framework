<?php

namespace Govorun\Testing;

use Govorun\Foundation\Application;
use Govorun\Routing\Route;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    use InteractsWithMessenger, InteractsWithApi;

    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        Route::clear();
        $this->app = $this->createApplication();
    }

    protected function tearDown(): void
    {
        Route::clear();
        Application::setInstance(null);
        parent::tearDown();
    }

    protected function createApplication(): Application
    {
        $app = new Application($this->basePath());
        $app->loadConfiguration();
        $app->registerCoreProviders();
        $app->boot();

        return $app;
    }

    protected function basePath(): string
    {
        return defined('GOVORUN_BASE_PATH')
            ? GOVORUN_BASE_PATH
            : (function_exists('base_path') ? base_path() : getcwd());
    }
}
