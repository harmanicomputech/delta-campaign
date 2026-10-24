<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Phone\NigerianPhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NigerianPhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null}>
     */
    public static function numbers(): array
    {
        return [
            'local with spaces' => ['0803 123 4567', '+2348031234567'],
            'local compact' => ['08031234567', '+2348031234567'],
            'national without trunk' => ['803-123-4567', '+2348031234567'],
            'international' => ['+234 803 123 4567', '+2348031234567'],
            'international no plus' => ['2348031234567', '+2348031234567'],
            'international 00' => ['002348031234567', '+2348031234567'],
            'trunk after country code' => ['+234 0803 123 4567', '+2348031234567'],
            '7xx range' => ['07012345678', '+2347012345678'],
            '9xx range' => ['09123456789', '+2349123456789'],
            'too short' => ['0803123456', null],
            'too long' => ['080312345678', null],
            'landline' => ['01 234 5678', null],
            'invalid mobile prefix' => ['08531234567', null],
            'foreign number' => ['+44 7700 900123', null],
            'empty' => ['', null],
            'letters' => ['phone', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_normalises_to_e164(string $input, ?string $expected): void
    {
        $this->assertSame($expected, NigerianPhoneNumber::normalise($input));
    }

    public function test_mask_reveals_only_the_last_four_digits(): void
    {
        $this->assertSame('+234 ••• ••• 4567', NigerianPhoneNumber::mask('+2348031234567'));
    }
}
