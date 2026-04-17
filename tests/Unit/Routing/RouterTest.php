<?php

namespace Govorun\Tests\Unit\Routing;

use Govorun\Contracts\MessengerDriver;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\Dto\MediaDto;
use Govorun\Messaging\Dto\LocationDto;
use Govorun\Messaging\Dto\ContactDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Routing\Controller;
use Govorun\Routing\Route;
use Govorun\Routing\Router;
use Govorun\Tests\TestCase;

class RouterTest extends TestCase
{
    private MessengerDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        Route::clear();
        $this->driver = $this->createMock(MessengerDriver::class);
    }

    private function makeMessage(
        string $text = '',
        ContentType $type = ContentType::Text,
        ?string $action = null,
        ?array $actionParams = null,
        ?string $event = null,
        ?MediaDto $media = null,
        ?LocationDto $location = null,
        ?ContactDto $contact = null,
        ?string $referral = null,
    ): IncomingMessage {
        return new IncomingMessage(
            id: '1', chatId: 'chat1', driverName: 'telegram',
            text: $text, user: new UserDto(id: '100'), type: $type,
            action: $action, actionParams: $actionParams, event: $event,
            media: $media, location: $location, contact: $contact, referral: $referral,
        );
    }

    public function test_dispatches_command(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::command('start', [get_class($ctrl), 'handle']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage('/start'));
        $this->assertTrue($ctrl::$called);
        $ctrl::$called = false;
    }

    public function test_dispatches_command_registered_without_slash(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::command('help', [get_class($ctrl), 'handle']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage('/help extra params'));
        $this->assertTrue($ctrl::$called);
        $ctrl::$called = false;
    }

    public function test_dispatches_command_registered_with_leading_slash(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::command('/start', [get_class($ctrl), 'handle']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage('/start'));
        $this->assertTrue($ctrl::$called);
        $ctrl::$called = false;
    }

    public function test_does_not_match_command_without_leading_slash_in_message(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::command('/start', [get_class($ctrl), 'handle']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage('start'));
        $this->assertFalse($ctrl::$called);
    }

    public function test_dispatches_action(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::action('confirm', [get_class($ctrl), 'handle']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage(type: ContentType::Action, action: 'confirm'));
        $this->assertTrue($ctrl::$called);
        $ctrl::$called = false;
    }

    public function test_dispatches_event(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::event('subscribe', [get_class($ctrl), 'handle']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage(type: ContentType::Event, event: 'subscribe'));
        $this->assertTrue($ctrl::$called);
        $ctrl::$called = false;
    }

    public function test_dispatches_phrase(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::phrase('запись', [get_class($ctrl), 'handle']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage('хочу запись'));
        $this->assertTrue($ctrl::$called);
        $ctrl::$called = false;
    }

    public function test_dispatches_phrase_by_alias(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::phrase('запись', [get_class($ctrl), 'handle'])->alias(['записаться']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage('хочу записаться'));
        $this->assertTrue($ctrl::$called);
        $ctrl::$called = false;
    }

    public function test_dispatches_pattern(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::pattern('/^привет/ui', [get_class($ctrl), 'handle']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage('Привет мир'));
        $this->assertTrue($ctrl::$called);
        $ctrl::$called = false;
    }

    public function test_dispatches_media(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::media('photo', [get_class($ctrl), 'handle']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage(type: ContentType::Media, media: new MediaDto(type: 'photo')));
        $this->assertTrue($ctrl::$called);
        $ctrl::$called = false;
    }

    public function test_dispatches_location(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::location([get_class($ctrl), 'handle']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage(type: ContentType::Location, location: new LocationDto(55.75, 37.62)));
        $this->assertTrue($ctrl::$called);
        $ctrl::$called = false;
    }

    public function test_dispatches_contact(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::contact([get_class($ctrl), 'handle']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage(type: ContentType::Contact, contact: new ContactDto(phone: '+79001234567')));
        $this->assertTrue($ctrl::$called);
        $ctrl::$called = false;
    }

    public function test_dispatches_referral(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::referral('promo', [get_class($ctrl), 'handle']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage(referral: 'promo'));
        $this->assertTrue($ctrl::$called);
        $ctrl::$called = false;
    }

    public function test_dispatches_fallback(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::fallback([get_class($ctrl), 'handle']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage('unknown'));
        $this->assertTrue($ctrl::$called);
        $ctrl::$called = false;
    }

    public function test_priority_event_over_command(): void
    {
        $eventCtrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        $cmdCtrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::command('start', [get_class($cmdCtrl), 'handle']);
        Route::event('subscribe', [get_class($eventCtrl), 'handle']);
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage(text: '/start', type: ContentType::Event, event: 'subscribe'));
        $this->assertTrue($eventCtrl::$called);
        $this->assertFalse($cmdCtrl::$called);
        $eventCtrl::$called = false;
    }

    public function test_nested_phrase_dispatch(): void
    {
        $ctrl = new class extends Controller {
            public static bool $called = false;
            public function handle(): void { static::$called = true; }
        };
        Route::phrase('запись', function () use ($ctrl) {
            Route::phrase('моя', [get_class($ctrl), 'handle']);
        });
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage('моя запись'));
        $this->assertTrue($ctrl::$called);
        $ctrl::$called = false;
    }

    public function test_no_match_does_not_throw(): void
    {
        $router = new Router($this->driver);
        $router->dispatch($this->makeMessage('nothing matches'));
        $this->assertTrue(true);
    }
}
