<?php

declare(strict_types=1);

namespace App\Services\Ussd\Contracts;

use App\Services\Ussd\UssdReply;
use App\Services\Ussd\UssdRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Translates a provider's USSD callback to and from the provider-neutral menu. */
interface UssdProvider
{
    /** Null when the request is malformed. */
    public function parse(Request $request): ?UssdRequest;

    public function respond(UssdReply $reply): Response;
}
