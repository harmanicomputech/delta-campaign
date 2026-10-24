<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Services\Sms\Drivers\AfricasTalkingSmsGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AfricasTalkingSmsGatewayTest extends TestCase
{
    private function gateway(string $username = 'campaign'): AfricasTalkingSmsGateway
    {
        return new AfricasTalkingSmsGateway($username, 'test-key', 'SENDER');
    }

    public function test_successful_send_returns_the_message_id_and_cost(): void
    {
        Http::fake(['api.africastalking.com/*' => Http::response(['SMSMessageData' => ['Message' => 'Sent to 1/1', 'Recipients' => [
            ['statusCode' => 101, 'number' => '+2348031234567', 'status' => 'Success', 'cost' => 'NGN 2.2000', 'messageId' => 'ATXid_123'],
        ]]])]);

        $result = $this->gateway()->send('+2348031234567', 'Hello');

        $this->assertTrue($result->accepted);
        $this->assertSame('ATXid_123', $result->providerMessageId);
        $this->assertSame('2.2000', $result->cost);
        Http::assertSent(fn (Request $request) => $request->hasHeader('apiKey', 'test-key')
            && $request['username'] === 'campaign' && $request['from'] === 'SENDER' && $request['to'] === '+2348031234567');
    }

    public function test_invalid_numbers_are_permanent_failures(): void
    {
        Http::fake(['*' => Http::response(['SMSMessageData' => ['Recipients' => [['statusCode' => 403, 'status' => 'InvalidPhoneNumber']]]])]);

        $result = $this->gateway()->send('+2348031234567', 'Hello');

        $this->assertFalse($result->accepted);
        $this->assertTrue($result->permanentFailure);
    }

    public function test_provider_outages_are_retryable(): void
    {
        Http::fake(['*' => fn () => throw new ConnectionException('timeout')]);
        $this->assertFalse($this->gateway()->send('+2348031234567', 'x')->permanentFailure);

        Http::fake(['*' => Http::response('', 503)]);
        $this->assertFalse($this->gateway()->send('+2348031234567', 'x')->permanentFailure);
    }

    public function test_the_sandbox_uses_the_sandbox_endpoint(): void
    {
        Http::fake(['api.sandbox.africastalking.com/*' => Http::response(['SMSMessageData' => ['Recipients' => [['statusCode' => 101, 'status' => 'Success', 'messageId' => 'x']]]])]);

        $this->assertTrue($this->gateway('sandbox')->send('+2348031234567', 'x')->accepted);
    }
}
