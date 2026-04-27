<?php

namespace Govorun\Tests\Unit\Routing;

use Govorun\Routing\Route;
use Govorun\Routing\RouteEntry;
use Govorun\Tests\TestCase;

class RouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::clear();
    }

    public function test_command_registers_route(): void
    {
        Route::command('start', 'StartController');
        $routes = Route::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertSame('command', $routes[0]->type);
        $this->assertSame('/start', $routes[0]->value);
        $this->assertSame('StartController', $routes[0]->action);
    }

    public function test_phrase_registers_route_with_alias(): void
    {
        $entry = Route::phrase('запись', 'RecordController');
        $entry->alias(['записаться', 'записать']);
        $routes = Route::getRoutes();
        $this->assertSame(['записаться', 'записать'], $routes[0]->aliases);
    }

    public function test_pattern_registers_route(): void
    {
        Route::pattern('/^привет/ui', 'GreetingController');
        $routes = Route::getRoutes();
        $this->assertSame('pattern', $routes[0]->type);
        $this->assertSame('/^привет/ui', $routes[0]->value);
    }

    public function test_action_registers_route(): void
    {
        Route::action('confirm', ['OrderController', 'confirm']);
        $routes = Route::getRoutes();
        $this->assertSame('action', $routes[0]->type);
        $this->assertSame('confirm', $routes[0]->value);
        $this->assertSame(['OrderController', 'confirm'], $routes[0]->action);
    }

    public function test_event_registers_route(): void
    {
        Route::event('subscribe', 'WelcomeController');
        $routes = Route::getRoutes();
        $this->assertSame('event', $routes[0]->type);
    }

    public function test_media_registers_route(): void
    {
        Route::media('photo', 'PhotoController');
        $routes = Route::getRoutes();
        $this->assertSame('media', $routes[0]->type);
        $this->assertSame('photo', $routes[0]->value);
    }

    public function test_location_registers_route(): void
    {
        Route::location('LocationController');
        $routes = Route::getRoutes();
        $this->assertSame('location', $routes[0]->type);
        $this->assertNull($routes[0]->value);
    }

    public function test_contact_registers_route(): void
    {
        Route::contact('ContactController');
        $routes = Route::getRoutes();
        $this->assertSame('contact', $routes[0]->type);
    }

    public function test_referral_registers_route(): void
    {
        Route::referral('promo', 'PromoController');
        $routes = Route::getRoutes();
        $this->assertSame('referral', $routes[0]->type);
        $this->assertSame('promo', $routes[0]->value);
    }

    public function test_fallback_registers_route(): void
    {
        Route::fallback('DefaultController');
        $routes = Route::getRoutes();
        $this->assertSame('fallback', $routes[0]->type);
    }

    public function test_phrase_with_nested_closure(): void
    {
        Route::phrase('запись', function () {
            Route::phrase('моя', 'MyRecordController');
            Route::phrase('отменить', 'CancelController');
        });
        $routes = Route::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertCount(2, $routes[0]->children);
        $this->assertSame('моя', $routes[0]->children[0]->value);
        $this->assertSame('отменить', $routes[0]->children[1]->value);
    }

    public function test_middleware_wraps_routes_in_group(): void
    {
        Route::middleware('AuthMiddleware', function () {
            Route::command('profile', 'ProfileController');
            Route::phrase('запись', 'RecordController');
        });
        $routes = Route::getRoutes();
        $this->assertCount(2, $routes);
        $this->assertContains('AuthMiddleware', $routes[0]->middleware);
        $this->assertContains('AuthMiddleware', $routes[1]->middleware);
    }

    public function test_clear_removes_all_routes(): void
    {
        Route::command('start', 'StartController');
        Route::clear();
        $this->assertEmpty(Route::getRoutes());
    }
}
