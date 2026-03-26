<?php

namespace Govorun\Tests\Unit\Testing;

require_once __DIR__ . '/FakeMessengerTest.php';

use Govorun\Contracts\MessengerDriver;
use Govorun\Foundation\Application;
use Govorun\Routing\Route;
use Govorun\Testing\FakeDriver;
use Govorun\Testing\FakeMessenger;
use Govorun\Testing\InteractsWithMessenger;
use PHPUnit\Framework\TestCase;

class InteractsWithMessengerTest extends TestCase
{
    use InteractsWithMessenger;

    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        Route::clear();

        $this->app = new Application(__DIR__ . '/../../fixtures');
        $this->app->loadConfiguration();
        $this->app->registerCoreProviders();
        $this->app->boot();
    }

    protected function tearDown(): void
    {
        Route::clear();
        Application::setInstance(null);
        parent::tearDown();
    }

    public function test_fake_messenger_returns_fake_messenger_instance(): void
    {
        $messenger = $this->fakeMessenger();
        $this->assertInstanceOf(FakeMessenger::class, $messenger);
    }

    public function test_fake_messenger_binds_driver_to_container(): void
    {
        $this->fakeMessenger();

        $this->assertInstanceOf(FakeDriver::class, $this->app->make(MessengerDriver::class));
        $this->assertInstanceOf(FakeDriver::class, $this->app->make('driver.fake'));
    }

    public function test_fake_messenger_accepts_custom_chat_id(): void
    {
        Route::command('echo_chat', FakeMessengerTestEchoChatController::class);

        $this->fakeMessenger('custom-chat-99')
            ->receive('/echo_chat')
            ->assertReply('custom-chat-99');
    }
}
