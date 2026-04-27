<?php

namespace Govorun\Tests\Unit\Drivers\Telegram;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use Govorun\Tests\TestCase;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use Govorun\Drivers\Telegram\TelegramDriver;

/** Тесты методов синхронизации профиля Telegram-бота (setMyName и др.). */
class TelegramProfileTest extends TestCase
{
    private array $history = [];

    private function makeDriver(array $responses = []): TelegramDriver
    {
        if (empty($responses)) {
            $responses = [new Response(200, [], '{"ok":true,"result":true}')];
        }

        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new TelegramDriver(
            config: ['token' => 'test-token', 'secret' => null],
            client: new Client(['handler' => $stack]),
        );
    }

    public function test_set_my_name_calls_api(): void
    {
        $driver = $this->makeDriver();

        $driver->setMyName('Govorun Bot');

        $this->assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/setMyName', $request->getUri()->getPath());

        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertSame('Govorun Bot', $body['name']);
    }

    public function test_set_my_description_calls_api(): void
    {
        $driver = $this->makeDriver();

        $driver->setMyDescription('Длинное описание для пустого экрана.');

        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/setMyDescription', $request->getUri()->getPath());

        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertSame('Длинное описание для пустого экрана.', $body['description']);
    }

    public function test_set_my_short_description_calls_api(): void
    {
        $driver = $this->makeDriver();

        $driver->setMyShortDescription('AI-ассистент');

        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/setMyShortDescription', $request->getUri()->getPath());

        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertSame('AI-ассистент', $body['short_description']);
    }

    public function test_set_my_commands_calls_api(): void
    {
        $driver = $this->makeDriver();

        $driver->setMyCommands([
            ['command' => 'start', 'description' => 'Запустить'],
            ['command' => 'help',  'description' => 'Справка'],
        ]);

        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/setMyCommands', $request->getUri()->getPath());

        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertSame([
            ['command' => 'start', 'description' => 'Запустить'],
            ['command' => 'help',  'description' => 'Справка'],
        ], $body['commands']);
    }

    public function test_set_my_commands_with_empty_array_clears_menu(): void
    {
        $driver = $this->makeDriver();

        $driver->setMyCommands([]);

        $body = json_decode($this->history[0]['request']->getBody()->getContents(), true);
        $this->assertSame([], $body['commands']);
    }

    public function test_set_my_profile_photo_static_uses_multipart(): void
    {
        $driver = $this->makeDriver();
        $photo = __DIR__ . '/../../../fixtures/profile-photo.jpg';

        $driver->setMyProfilePhoto($photo, 'static');

        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/setMyProfilePhoto', $request->getUri()->getPath());
        $this->assertStringContainsString('multipart/form-data', $request->getHeaderLine('Content-Type'));

        $body = (string) $request->getBody();
        $this->assertStringContainsString('name="photo"', $body);
        $this->assertStringContainsString('"type":"static"', $body);
        $this->assertStringContainsString('"photo":"attach:\/\/photo_file"', $body);
        $this->assertStringContainsString('name="photo_file"', $body);
    }

    public function test_set_my_profile_photo_animated_uses_animation_field(): void
    {
        $driver = $this->makeDriver();
        $photo = __DIR__ . '/../../../fixtures/profile-photo.jpg';

        $driver->setMyProfilePhoto($photo, 'animated');

        $body = (string) $this->history[0]['request']->getBody();
        $this->assertStringContainsString('"type":"animated"', $body);
        $this->assertStringContainsString('"animation":"attach:\/\/photo_file"', $body);
        $this->assertStringNotContainsString('"photo":"attach', $body);
    }

    public function test_remove_my_profile_photo_calls_api(): void
    {
        $driver = $this->makeDriver();

        $driver->removeMyProfilePhoto();

        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/removeMyProfilePhoto', $request->getUri()->getPath());
    }

    public function test_set_my_profile_photo_throws_when_ok_false(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], '{"ok":false,"error_code":400,"description":"PHOTO_INVALID_DIMENSIONS"}'),
        ]);
        $photo = __DIR__ . '/../../../fixtures/profile-photo.jpg';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Telegram setMyProfilePhoto failed [400]: PHOTO_INVALID_DIMENSIONS');

        $driver->setMyProfilePhoto($photo, 'static');
    }

    public function test_api_call_throws_when_ok_false(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], '{"ok":false,"error_code":429,"description":"Too Many Requests"}'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Telegram setMyName failed [429]: Too Many Requests');

        $driver->setMyName('Boom');
    }
}
