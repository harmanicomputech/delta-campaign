<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Services\Authentication\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    /** RFC 6238 Appendix B SHA-1 secret ("12345678901234567890") in base32. */
    private const string RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /**
     * RFC 6238 test vectors, truncated to 6 digits (value mod 10^6).
     *
     * @return array<string, array{int, string}>
     */
    public static function vectors(): array
    {
        return [
            'T=59' => [59, '287082'],
            'T=1111111109' => [1111111109, '081804'],
            'T=1111111111' => [1111111111, '050471'],
            'T=1234567890' => [1234567890, '005924'],
            'T=2000000000' => [2000000000, '279037'],
        ];
    }

    #[DataProvider('vectors')]
    public function test_matches_rfc_6238_vectors(int $time, string $code): void
    {
        $this->assertSame($code, Totp::code(self::RFC_SECRET, intdiv($time, 30)));
        $this->assertSame(intdiv($time, 30), Totp::verify(self::RFC_SECRET, $code, timestamp: $time));
    }

    public function test_accepts_one_step_of_drift_only(): void
    {
        $code = Totp::code(self::RFC_SECRET, intdiv(1111111109, 30));

        $this->assertNotNull(Totp::verify(self::RFC_SECRET, $code, timestamp: 1111111109 + 30));
        $this->assertNull(Totp::verify(self::RFC_SECRET, $code, timestamp: 1111111109 + 90));
    }

    public function test_rejects_replay_of_used_steps(): void
    {
        $step = intdiv(1234567890, 30);
        $code = Totp::code(self::RFC_SECRET, $step);

        $this->assertNull(Totp::verify(self::RFC_SECRET, $code, lastUsedStep: $step, timestamp: 1234567890));
    }

    public function test_rejects_malformed_codes(): void
    {
        $this->assertNull(Totp::verify(self::RFC_SECRET, '12345', timestamp: 59));
        $this->assertNull(Totp::verify(self::RFC_SECRET, 'abcdef', timestamp: 59));
    }

    public function test_base32_round_trips_and_secrets_are_random(): void
    {
        $this->assertSame('12345678901234567890', Totp::base32Decode(self::RFC_SECRET));
        $this->assertSame(self::RFC_SECRET, Totp::base32Encode('12345678901234567890'));
        $this->assertNotSame(Totp::generateSecret(), Totp::generateSecret());
        $this->assertSame(32, strlen(Totp::generateSecret()));
    }

    public function test_builds_an_otpauth_uri(): void
    {
        $this->assertSame(
            'otpauth://totp/Campaign%20Ops:%2B234%20x?secret=ABC&issuer=Campaign%20Ops&algorithm=SHA1&digits=6&period=30',
            Totp::uri('ABC', '+234 x', 'Campaign Ops'),
        );
    }
}
