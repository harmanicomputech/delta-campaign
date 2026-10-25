<?php

declare(strict_types=1);

namespace App\Services\Callbacks;

use App\Models\CallbackRequest;
use App\Models\Lga;
use App\Models\Supporter;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Tasks\TaskService;
use App\Services\Users\AuthorizationDenied;
use App\Support\Crypto\PhoneHasher;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Call-back requests: one open request per number per day, each tracked as a task in the caller's LGA. */
final class CallbackService
{
    public const int DUPLICATE_HOURS = 24;

    public function __construct(private readonly TaskService $tasks, private readonly AuditLogger $audit) {}

    /** @return array{request: CallbackRequest, created: bool} */
    public function request(string $phone, Lga $lga, string $topic, string $channel, string $language = 'en', bool $sample = false): array
    {
        if (! in_array($topic, CallbackRequest::TOPICS, true)) {
            throw new InvalidArgumentException("Unknown callback topic [{$topic}].");
        }

        $e164 = NigerianPhoneNumber::normalise($phone) ?? throw new InvalidArgumentException('Invalid phone number.');
        $hash = PhoneHasher::hash($e164);

        $recent = CallbackRequest::query()->where('phone_hash', $hash)->where('created_at', '>', now()->subHours(self::DUPLICATE_HOURS))->latest('id')->first();
        if ($recent !== null) {
            return ['request' => $recent, 'created' => false];
        }

        return DB::transaction(function () use ($e164, $hash, $lga, $topic, $channel, $language, $sample): array {
            $request = new CallbackRequest([
                'phone_hash' => $hash,
                'phone_last4' => substr($e164, -4),
                'supporter_id' => Supporter::query()->where('phone_hash', $hash)->value('id'),
                'senatorial_district_id' => $lga->senatorial_district_id,
                'lga_id' => $lga->id,
                'topic' => $topic,
                'language' => $language,
                'channel' => $channel,
                'is_sample' => $sample,
            ]);
            $request->phone_encrypted = $e164;
            $request->save();

            $task = $this->tasks->createSystem([
                'title' => __('callbacks.task_title', ['topic' => __('callbacks.topic_'.$topic), 'reference' => $request->reference]),
                'description' => __('callbacks.task_description', ['reference' => $request->reference, 'language' => $language === 'pcm' ? 'Pidgin' : 'English']),
                'priority' => $topic === 'problem' ? 'high' : 'normal',
                'due_at' => now()->addDay(),
                'lga_id' => $lga->id,
            ], $channel);

            $request->forceFill(['task_id' => $task->id])->save();

            return ['request' => $request, 'created' => true];
        });
    }

    public function canReveal(CallbackRequest $request, User $user): bool
    {
        $task = $request->loadMissing('task')->task;

        return $task !== null && $this->tasks->canWork($task, $user);
    }

    public function reveal(CallbackRequest $request, User $user): string
    {
        if (! $this->canReveal($request, $user)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }

        $this->audit->record('callback.number_revealed', $request, null, ['reference' => $request->reference], $user);

        return (string) $request->phone_encrypted;
    }
}
