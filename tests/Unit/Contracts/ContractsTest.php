<?php

namespace Govorun\Tests\Unit\Contracts;

use Govorun\Contracts\MessengerDriver;
use Govorun\Contracts\StateStorage;
use Govorun\Exceptions\ApiException;
use Govorun\Exceptions\SendFailedException;
use Govorun\Tests\TestCase;

class ContractsTest extends TestCase
{
    public function test_messenger_driver_is_implementable(): void
    {
        $mock = $this->createMock(MessengerDriver::class);
        $this->assertInstanceOf(MessengerDriver::class, $mock);
    }

    public function test_state_storage_is_implementable(): void
    {
        $mock = $this->createMock(StateStorage::class);
        $this->assertInstanceOf(StateStorage::class, $mock);
    }

    public function test_send_failed_exception_is_throwable(): void
    {
        $this->expectException(SendFailedException::class);
        $this->expectExceptionMessage('Send failed');
        throw new SendFailedException('Send failed');
    }

    public function test_api_exception_is_throwable(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('API error');
        throw new ApiException('API error');
    }
}
