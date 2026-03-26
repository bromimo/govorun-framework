<?php

namespace Govorun\Tests\Unit\Testing;

use Govorun\Contracts\MessengerDriver;
use Govorun\Foundation\Application;
use Govorun\Messaging\Keyboard;
use Govorun\Messaging\Message;
use Govorun\Routing\Controller;
use Govorun\Routing\Route;
use Govorun\Testing\FakeDriver;
use Govorun\Testing\FakeMessenger;
use PHPUnit\Framework\TestCase;

class FakeMessengerTest extends TestCase
{
    private Application $app;
    private FakeDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        Route::clear();

        $this->app = new Application(__DIR__ . '/../../fixtures');
        $this->app->loadConfiguration();
        $this->app->registerCoreProviders();
        $this->app->boot();

        $this->driver = new FakeDriver();
        $this->app->instance(MessengerDriver::class, $this->driver);
        $this->app->instance('driver.fake', $this->driver);
    }

    protected function tearDown(): void
    {
        Route::clear();
        Application::setInstance(null);
        parent::tearDown();
    }

    private function messenger(string $chatId = 'fake-chat-1'): FakeMessenger
    {
        return new FakeMessenger($this->app, $this->driver, $chatId);
    }

    public function test_receive_and_assert_reply(): void
    {
        Route::command('start', FakeMessengerTestStartController::class);

        $this->messenger()
            ->receive('/start')
            ->assertReply('Welcome!');
    }

    public function test_assert_reply_contains(): void
    {
        Route::command('start', FakeMessengerTestStartController::class);

        $this->messenger()
            ->receive('/start')
            ->assertReplyContains('Welc');
    }

    public function test_assert_no_reply(): void
    {
        Route::command('start', FakeMessengerTestStartController::class);

        $this->messenger()
            ->receive('/start')
            ->assertReply('Welcome!')
            ->assertNoReply();
    }

    public function test_assert_reply_fails_when_no_messages(): void
    {
        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        $this->messenger()
            ->receive('hello')
            ->assertReply('something');
    }

    public function test_assert_reply_fails_on_text_mismatch(): void
    {
        Route::command('start', FakeMessengerTestStartController::class);

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        $this->messenger()
            ->receive('/start')
            ->assertReply('Wrong text');
    }

    public function test_assert_no_reply_fails_when_messages_exist(): void
    {
        Route::command('start', FakeMessengerTestStartController::class);

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        $this->messenger()
            ->receive('/start')
            ->assertNoReply();
    }

    public function test_click_button(): void
    {
        Route::command('menu', FakeMessengerTestMenuController::class);
        Route::action('pick', FakeMessengerTestPickController::class);

        $this->messenger()
            ->receive('/menu')
            ->assertReply('Choose:')
            ->clickButton('Option A')
            ->assertReply('You picked A');
    }

    public function test_click_button_throws_when_button_not_found(): void
    {
        Route::command('menu', FakeMessengerTestMenuController::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Button 'Nonexistent' not found");

        $this->messenger()
            ->receive('/menu')
            ->assertReply('Choose:')
            ->clickButton('Nonexistent');
    }

    public function test_assert_keyboard(): void
    {
        Route::command('menu', FakeMessengerTestMenuController::class);

        $this->messenger()
            ->receive('/menu')
            ->assertReply('Choose:')
            ->assertKeyboard(['Option A', 'Option B']);
    }

    public function test_assert_keyboard_fails_when_no_keyboard(): void
    {
        Route::command('start', FakeMessengerTestStartController::class);

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        $this->messenger()
            ->receive('/start')
            ->assertReply('Welcome!')
            ->assertKeyboard(['Something']);
    }

    public function test_multiple_receive_accumulates_messages(): void
    {
        Route::command('start', FakeMessengerTestStartController::class);
        Route::command('menu', FakeMessengerTestMenuController::class);

        $this->messenger()
            ->receive('/start')
            ->receive('/menu')
            ->assertReply('Welcome!')
            ->assertReply('Choose:');
    }

    public function test_chat_id_is_passed_to_incoming_message(): void
    {
        Route::command('echo_chat', FakeMessengerTestEchoChatController::class);

        $this->messenger('chat-42')
            ->receive('/echo_chat')
            ->assertReply('chat-42');
    }
}

class FakeMessengerTestStartController extends Controller
{
    public function handle(): void
    {
        $this->reply('Welcome!');
    }
}

class FakeMessengerTestMenuController extends Controller
{
    public function handle(): void
    {
        $msg = Message::make('Choose:')
            ->keyboard(
                Keyboard::make()
                    ->button('Option A', 'pick', ['v' => 'A'])
                    ->button('Option B', 'pick', ['v' => 'B'])
            );
        $this->send($msg);
    }
}

class FakeMessengerTestPickController extends Controller
{
    public function handle(): void
    {
        $this->reply('You picked ' . $this->param('v'));
    }
}

class FakeMessengerTestEchoChatController extends Controller
{
    public function handle(): void
    {
        $this->reply($this->message()->chatId);
    }
}
