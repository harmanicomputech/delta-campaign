<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\OutboundMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Africa's Talking SMS delivery reports (id, status, failureReason). */
final class SmsDeliveryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $id = $request->string('id')->toString();
        $status = $request->string('status')->toString();

        $message = $id !== '' ? OutboundMessage::query()->where('provider_message_id', $id)->first() : null;

        if ($message !== null && ! in_array($message->status, [MessageStatus::Delivered, MessageStatus::Failed], true)) {
            match ($status) {
                'Success' => $message->forceFill(['status' => MessageStatus::Delivered, 'delivered_at' => now()])->save(),
                'Failed', 'Rejected', 'AbsentSubscriber', 'Expired' => $message->forceFill([
                    'status' => MessageStatus::Failed,
                    'failed_at' => now(),
                    'last_error' => mb_substr($request->string('failureReason')->toString() ?: $status, 0, 120),
                ])->save(),
                default => null, // Buffered, Sent, Submitted: still on its way.
            };
        }

        return response('', 200);
    }
}
