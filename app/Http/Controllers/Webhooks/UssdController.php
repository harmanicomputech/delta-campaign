<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Ussd\Contracts\UssdProvider;
use App\Services\Ussd\UssdMenu;
use App\Services\Ussd\UssdReply;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class UssdController extends Controller
{
    public function __invoke(Request $request, UssdProvider $provider, UssdMenu $menu): Response
    {
        $ussd = $provider->parse($request);

        return $provider->respond($ussd === null ? UssdReply::end(__('ussd.error')) : $menu->handle($ussd));
    }
}
