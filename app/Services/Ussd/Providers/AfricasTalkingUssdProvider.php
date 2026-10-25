<?php

declare(strict_types=1);

namespace App\Services\Ussd\Providers;

use App\Services\Ussd\Contracts\UssdProvider;
use App\Services\Ussd\UssdReply;
use App\Services\Ussd\UssdRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Africa's Talking USSD: form POST with sessionId, serviceCode, phoneNumber and
 * the cumulative text; the reply is plain text starting with CON or END.
 *
 * @see https://developers.africastalking.com/docs/ussd/handle_sessions
 */
final class AfricasTalkingUssdProvider implements UssdProvider
{
    public function parse(Request $request): ?UssdRequest
    {
        $session = $request->string('sessionId')->toString();
        $phone = $request->string('phoneNumber')->toString();

        if ($session === '' || $phone === '' || mb_strlen($session) > 100) {
            return null;
        }

        return new UssdRequest($session, $phone, mb_substr($request->string('text')->toString(), 0, 500), $request->string('serviceCode')->toString() ?: null);
    }

    public function respond(UssdReply $reply): Response
    {
        return response($reply->toProviderString(), 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }
}
