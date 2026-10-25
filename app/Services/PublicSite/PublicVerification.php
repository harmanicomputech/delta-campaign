<?php

declare(strict_types=1);

namespace App\Services\PublicSite;

use App\Enums\OtpPurpose;
use App\Models\OtpChallenge;
use App\Services\Authentication\OtpService;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Validation\ValidationException;

/**
 * Phone verification for public forms (registration, preferences, data requests):
 * a one-time code by SMS proves the visitor controls the number. The verified
 * number is remembered in the session for 30 minutes.
 */
final class PublicVerification
{
    private const string SESSION_KEY = 'public_verification';

    private const int VALID_MINUTES = 30;

    public function __construct(private readonly OtpService $otp) {}

    public function send(string $phone, ?string $ip): void
    {
        $e164 = NigerianPhoneNumber::normalise($phone) ?? throw ValidationException::withMessages(['phone' => __('validation.nigerian_mobile')]);
        $challenge = $this->otp->request($e164, OtpPurpose::PublicVerification, $ip);
        session()->put(self::SESSION_KEY.'.pending', ['challenge' => $challenge->id, 'phone' => $e164]);
    }

    public function confirm(string $code): string
    {
        $pending = session(self::SESSION_KEY.'.pending');
        $challenge = is_array($pending) ? OtpChallenge::query()->find($pending['challenge'] ?? 0) : null;

        if ($challenge === null || ! $this->otp->verify($challenge, trim($code))) {
            throw ValidationException::withMessages(['code' => __('public.code_invalid')]);
        }

        session()->forget(self::SESSION_KEY.'.pending');
        session()->put(self::SESSION_KEY.'.verified', ['phone' => $pending['phone'], 'at' => now()->getTimestamp()]);

        return (string) $pending['phone'];
    }

    public function pendingPhone(): ?string
    {
        $pending = session(self::SESSION_KEY.'.pending');

        return is_array($pending) ? ($pending['phone'] ?? null) : null;
    }

    /** The verified number, if confirmed within the last 30 minutes. */
    public function verifiedPhone(): ?string
    {
        $verified = session(self::SESSION_KEY.'.verified');

        if (! is_array($verified) || now()->getTimestamp() - (int) $verified['at'] > self::VALID_MINUTES * 60) {
            return null;
        }

        return (string) $verified['phone'];
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
