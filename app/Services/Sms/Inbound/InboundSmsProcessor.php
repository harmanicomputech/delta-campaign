<?php

declare(strict_types=1);

namespace App\Services\Sms\Inbound;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\RequestChannel;
use App\Jobs\SendTransactionalSms;
use App\Models\InboundSms;
use App\Models\PollingUnit;
use App\Models\Supporter;
use App\Models\User;
use App\Services\Consent\ConsentService;
use App\Services\Consent\NoPublishedNotice;
use App\Services\Tasks\TaskService;

/**
 * Inbound SMS commands (docs/SMS_COMMANDS.md):
 *
 *   STOP (STOPALL, UNSUBSCRIBE, CANCEL, END, QUIT)   stop all campaign messages
 *   START (UNSTOP, YES)                               receive SMS updates again
 *   HELP (INFO)                                       list commands
 *   REPORT <polling-unit code> <message>              field report from registered staff
 *
 * Keywords are case-insensitive and may be followed by extra words.
 */
final class InboundSmsProcessor
{
    public const array STOP = ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'];

    public const array START = ['START', 'UNSTOP', 'YES'];

    public const array HELP = ['HELP', 'INFO'];

    public function __construct(private readonly ConsentService $consent, private readonly TaskService $tasks) {}

    public function process(InboundSms $sms, string $from): void
    {
        if ($sms->processed_at !== null) {
            return; // Idempotent: providers retry callbacks.
        }

        $text = trim((string) $sms->text_encrypted);
        $keyword = strtoupper((string) strtok($text, " \t\n\r"));
        $user = User::findByPhone($from);

        [$status, $result, $reply, $taskId] = match (true) {
            in_array($keyword, self::STOP, true) => $this->stop($sms),
            in_array($keyword, self::START, true) => $this->start($sms),
            in_array($keyword, self::HELP, true) => ['processed', 'help', __('sms.help'), null],
            $keyword === 'REPORT' => $this->report($text, $user),
            // Unknown text from someone we know gets the help text; from an unknown number, no reply (no cost, no spam loop).
            default => ['ignored', 'unrecognised', $user ? __('sms.help') : null, null],
        };

        $sms->forceFill([
            'keyword' => mb_substr($keyword, 0, 20) ?: null,
            'status' => $status,
            'result' => $result,
            'user_id' => $user?->id,
            'task_id' => $taskId,
            'processed_at' => now(),
        ])->save();

        if ($reply !== null) {
            SendTransactionalSms::dispatch($from, $reply, 'sms.reply');
        }
    }

    /** @return array{0: string, 1: string, 2: string, 3: null} */
    private function stop(InboundSms $sms): array
    {
        $this->consent->withdrawAll($sms->phone_hash, null, RequestChannel::Sms, Supporter::query()->where('phone_hash', $sms->phone_hash)->first(), ['channel' => 'sms', 'inbound' => $sms->id]);

        return ['processed', 'opted_out', __('sms.stopped'), null];
    }

    /** @return array{0: string, 1: string, 2: string, 3: null} */
    private function start(InboundSms $sms): array
    {
        try {
            $this->consent->record($sms->phone_hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates, ConsentStatus::Granted, RequestChannel::Sms,
                Supporter::query()->where('phone_hash', $sms->phone_hash)->first(), ['channel' => 'sms', 'inbound' => $sms->id]);
        } catch (NoPublishedNotice) {
            return ['failed', 'no_notice', __('sms.not_open'), null];
        }

        return ['processed', 'opted_in', __('sms.started'), null];
    }

    /** @return array{0: string, 1: string, 2: string|null, 3: int|null} */
    private function report(string $text, ?User $user): array
    {
        if ($user === null || ! $user->isActive() || ! ($user->hasPermission('election.report') || $user->hasPermission('tasks.work'))) {
            return ['ignored', 'report_unauthorised', __('sms.report_not_registered'), null];
        }

        if (preg_match('/^\S+\s+(\S+)\s+(.+)$/su', $text, $m) !== 1) {
            return ['failed', 'report_format', __('sms.report_format'), null];
        }

        $unit = PollingUnit::query()->where('code', strtoupper($m[1]))->first();
        if ($unit === null) {
            return ['failed', 'report_unknown_unit', __('sms.report_unknown_unit', ['code' => mb_substr($m[1], 0, 40)]), null];
        }

        $scope = $user->geoScope($user->hasPermission('election.report') ? 'election.report' : 'tasks.work');
        if (! $scope->contains($unit->senatorial_district_id, $unit->lga_id, $unit->ward_id, $unit->id)) {
            return ['failed', 'report_outside_area', __('sms.report_outside_area'), null];
        }

        $message = mb_substr(trim($m[2]), 0, 480);
        $task = $this->tasks->createSystem([
            'title' => __('sms.report_task_title', ['name' => $user->name, 'code' => $unit->code]),
            'description' => __('sms.report_task_description', ['name' => $user->name, 'code' => $unit->code, 'unit' => $unit->name])."\n\n".$message,
            'priority' => 'high',
            'due_at' => now()->addHours(12),
            'polling_unit_id' => $unit->id,
        ], 'sms');

        return ['processed', 'report_created', __('sms.report_received', ['ref' => strtoupper(substr($task->uuid, 0, 8))]), $task->id];
    }
}
