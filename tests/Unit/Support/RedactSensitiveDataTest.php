<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Logging\RedactSensitiveData;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class RedactSensitiveDataTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function process(string $message, array $context = []): LogRecord
    {
        return (new RedactSensitiveData)(new LogRecord(new DateTimeImmutable, 'test', Level::Info, $message, $context));
    }

    public function test_masks_phone_numbers_in_messages(): void
    {
        $record = $this->process('OTP sent to 0803 123 4567 and +2348091112222');

        $this->assertSame('OTP sent to +234******4567 and +234******2222', $record->message);
    }

    public function test_redacts_sensitive_keys_recursively(): void
    {
        $record = $this->process('login', [
            'otp' => '123456',
            'user' => ['phone_number' => '08031234567', 'pin' => '1234', 'name' => 'Ada'],
            'headers' => ['Authorization' => 'Bearer abc'],
            'api_key' => 'k',
            'note' => 'call 08031234567',
        ]);

        $this->assertSame(RedactSensitiveData::REDACTED, $record->context['otp']);
        $this->assertSame(RedactSensitiveData::REDACTED, $record->context['user']['phone_number']);
        $this->assertSame(RedactSensitiveData::REDACTED, $record->context['user']['pin']);
        $this->assertSame('Ada', $record->context['user']['name']);
        $this->assertSame(RedactSensitiveData::REDACTED, $record->context['headers']['Authorization']);
        $this->assertSame(RedactSensitiveData::REDACTED, $record->context['api_key']);
        $this->assertSame('call +234******4567', $record->context['note']);
    }

    public function test_does_not_redact_innocent_keys_containing_short_fragments(): void
    {
        $record = $this->process('x', ['opinion' => 'a', 'shipping' => 'b', 'spinner' => 'c', 'pin_attempts' => 3]);

        $this->assertSame('a', $record->context['opinion']);
        $this->assertSame('b', $record->context['shipping']);
        $this->assertSame('c', $record->context['spinner']);
        $this->assertSame(RedactSensitiveData::REDACTED, $record->context['pin_attempts']);
    }

    public function test_leaves_non_phone_numbers_alone(): void
    {
        $this->assertSame('Imported 12345678901234 rows', $this->process('Imported 12345678901234 rows')->message);
    }
}
