<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Crypto\FieldEncrypter;
use App\Support\Crypto\PhoneHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class CryptoTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_numbers_are_encrypted_at_rest_with_a_keyed_hash(): void
    {
        $user = User::factory()->phone('0803 123 4567')->create();
        $row = DB::table('users')->where('id', $user->id)->first();

        $this->assertStringNotContainsString('8031234567', (string) $row->phone_encrypted);
        $this->assertSame(PhoneHasher::hash('+2348031234567'), $row->phone_hash);
        $this->assertNotSame(hash('sha256', '+2348031234567'), $row->phone_hash, 'Hashes are keyed, not plain SHA-256.');
        $this->assertSame('4567', $row->phone_last4);
        $this->assertSame('+2348031234567', $user->refresh()->phone);
        $this->assertSame($user->id, User::findByPhone('+234 803 123 4567')?->id);
    }

    public function test_field_encryption_does_not_depend_on_app_key(): void
    {
        $payload = FieldEncrypter::encrypt('sample');
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->assertSame('sample', FieldEncrypter::decrypt($payload));
    }

    public function test_missing_keys_fail_loudly(): void
    {
        config(['campaign.keys.phone_hash' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PHONE_HASH_KEY');
        PhoneHasher::hash('+2348031234567');
    }

    public function test_hashing_requires_e164(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PhoneHasher::hash('08031234567');
    }
}
