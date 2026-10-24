<?php

declare(strict_types=1);

namespace App\Services\Users;

use Illuminate\Auth\Access\AuthorizationException;

final class AuthorizationDenied extends AuthorizationException {}
