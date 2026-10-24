<?php

declare(strict_types=1);

namespace App\Enums;

enum OtpPurpose: string
{
    case Login = 'login';
    case Reauthenticate = 'reauthenticate';
    case PhoneChange = 'phone_change';
    case PublicVerification = 'public_verification';
}
