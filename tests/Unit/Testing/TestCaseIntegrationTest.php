<?php

namespace Govorun\Tests\Unit\Testing;

use Govorun\Http\ApiClient;
use Govorun\Messaging\Keyboard;
use Govorun\Messaging\Message;
use Govorun\Routing\Controller;
use Govorun\Routing\Route;
use Govorun\Testing\TestCase;

class TestCaseIntegrationTest extends TestCase
{
    protected function basePath(): string
    {
        return __DIR__ . '/../../fixtures';
    }

    public function test_full_bot_conversation(): void
    {
        Route::command('start', IntegrationTestWelcomeController::class);
        Route::command('menu', IntegrationTestMenuController::class);
        Route::action('select', IntegrationTestSelectController::class);

        $this->fakeMessenger()
            ->receive('/start')
            ->assertReply('Welcome to the bot!')
            ->assertNoReply()
            ->receive('/menu')
            ->assertReply('Pick an option:')
            ->assertKeyboard(['Alpha', 'Beta'])
            ->clickButton('Alpha')
            ->assertReply('You selected: Alpha');
    }

    public function test_fake_api_with_fake_messenger(): void
    {
        $fake = $this->fakeApi(IntegrationTestApiClient::class);
        $fake->mockGet('/users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        Route::command('users', IntegrationTestUsersController::class);

        $this->fakeMessenger()
            ->receive('/users')
            ->assertReplyContains('Alice')
            ->assertReplyContains('Bob');

        $fake->assertRequestMade('GET', '/users');
        $fake->assertRequestCount(1);
    }
}

class IntegrationTestWelcomeController extends Controller
{
    public function handle(): void
    {
        $this->reply('Welcome to the bot!');
    }
}

class IntegrationTestMenuController extends Controller
{
    public function handle(): void
    {
        $msg = Message::make('Pick an option:')
            ->keyboard(
                Keyboard::make()
                    ->button('Alpha', 'select', ['item' => 'Alpha'])
                    ->button('Beta', 'select', ['item' => 'Beta'])
            );
        $this->send($msg);
    }
}

class IntegrationTestSelectController extends Controller
{
    public function handle(): void
    {
        $this->reply('You selected: ' . $this->param('item'));
    }
}

class IntegrationTestApiClient extends ApiClient
{
    public function __construct()
    {
        // Guzzle client injected by FakeApiClient
    }

    protected function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    public function getUsers(): array
    {
        return $this->get('/users');
    }
}

class IntegrationTestUsersController extends Controller
{
    public function handle(): void
    {
        $client = app(IntegrationTestApiClient::class);
        $users = $client->getUsers();

        foreach ($users as $user) {
            $this->reply($user['name']);
        }
    }
}
