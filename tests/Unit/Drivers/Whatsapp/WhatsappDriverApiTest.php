<?php

namespace Govorun\Tests\Unit\Drivers\Whatsapp;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use Govorun\Tests\TestCase;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Drivers\Whatsapp\WhatsappDriver;
use Govorun\Exceptions\WebhookManualSetupException;

/** Тесты бизнес-профиля, скачивания медиа и неподдерживаемых операций WhatsApp. */
class WhatsappDriverApiTest extends TestCase
{
    private array $history = [];

    private function driverWith(array $responses, array $config = []): WhatsappDriver
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new WhatsappDriver(
            config: array_merge(['access_token' => 'TOK', 'phone_number_id' => '555', 'app_id' => 'APP'], $config),
            client: new Client(['handler' => $stack]),
        );
    }

    public function test_get_business_profile(): void
    {
        $driver = $this->driverWith([new Response(200, [], json_encode([
            'data' => [['about' => 'Привет', 'description' => 'Опис', 'vertical' => 'RETAIL']],
        ]))]);

        $profile = $driver->getBusinessProfile();

        $this->assertSame('Привет', $profile['about']);
        $request = $this->history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringContainsString('whatsapp_business_profile', (string) $request->getUri());
    }

    public function test_set_business_profile_posts_messaging_product(): void
    {
        $driver = $this->driverWith([new Response(200, [], json_encode(['success' => true]))]);

        $driver->setBusinessProfile(['about' => 'Новый', 'vertical' => 'EDU']);

        $body = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame('whatsapp', $body['messaging_product']);
        $this->assertSame('Новый', $body['about']);
        $this->assertSame('EDU', $body['vertical']);
    }

    public function test_upload_profile_photo_returns_handle(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'wa') . '.jpg';
        file_put_contents($tmp, 'binarydata');

        $driver = $this->driverWith([
            new Response(200, [], json_encode(['id' => 'upload:SESSION_1'])),
            new Response(200, [], json_encode(['h' => 'HANDLE_XYZ'])),
        ]);

        $handle = $driver->uploadProfilePhoto($tmp);
        @unlink($tmp);

        $this->assertSame('HANDLE_XYZ', $handle);
        $sessionRequest = $this->history[0]['request'];
        $this->assertStringContainsString('APP/uploads', (string) $sessionRequest->getUri());
        $uploadRequest = $this->history[1]['request'];
        $this->assertSame('OAuth TOK', $uploadRequest->getHeaderLine('Authorization'));
        $this->assertSame('0', $uploadRequest->getHeaderLine('file_offset'));
    }

    public function test_download_media_two_step(): void
    {
        $driver = $this->driverWith([
            new Response(200, [], json_encode(['url' => 'https://lookaside.fb/x', 'mime_type' => 'image/jpeg'])),
            new Response(200, [], 'RAWBYTES'),
        ]);

        $bytes = $driver->downloadMedia('MEDIA_1');

        $this->assertSame('RAWBYTES', $bytes);
        $binaryRequest = $this->history[1]['request'];
        $this->assertSame('Bearer TOK', $binaryRequest->getHeaderLine('Authorization'));
        $this->assertNotSame('', $binaryRequest->getHeaderLine('User-Agent'));
    }

    public function test_edit_and_delete_throw(): void
    {
        $driver = $this->driverWith([]);

        $this->expectException(\RuntimeException::class);
        $driver->edit('1', new OutgoingMessage());
    }

    public function test_install_webhook_throws_manual_setup(): void
    {
        $driver = $this->driverWith([]);

        $this->expectException(WebhookManualSetupException::class);
        $driver->installWebhook('https://example.com/webhook/whatsapp');
    }
}