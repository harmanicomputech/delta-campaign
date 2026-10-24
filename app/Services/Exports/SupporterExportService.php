<?php

declare(strict_types=1);

namespace App\Services\Exports;

use App\Enums\ExportStatus;
use App\Jobs\GenerateSupporterExport;
use App\Models\ExportJob;
use App\Models\Supporter;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Imports\ImportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Supporter exports are queued, generated inside the requester's geographic
 * scope (re-checked when the job runs), masked unless full contact details are
 * permitted, stored privately, downloadable only by the requester through a
 * short-lived signed URL, and deleted after EXPORT_TTL_HOURS. Every step is audited.
 */
final class SupporterExportService
{
    public const array FILTERS = ['lga_id', 'ward_id', 'verification_status', 'source', 'consent_sms'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function request(User $user, array $filters, bool $includeContact): ExportJob
    {
        $this->authorise($user, $includeContact);

        $export = ExportJob::query()->create([
            'type' => 'supporters',
            'status' => ExportStatus::Queued,
            'filters' => array_intersect_key(array_filter($filters, fn ($v) => $v !== null && $v !== ''), array_flip(self::FILTERS)),
            'include_contact' => $includeContact,
            'disk' => (string) config('campaign.privacy.exports_disk'),
            'requested_by' => $user->id,
        ]);

        $this->audit->record('export.requested', $export, null, ['type' => 'supporters', 'filters' => $export->filters, 'include_contact' => $includeContact], $user);
        GenerateSupporterExport::dispatch($export->id);

        return $export;
    }

    public function generate(ExportJob $export): void
    {
        if ($export->status !== ExportStatus::Queued) {
            return;
        }

        $export->update(['status' => ExportStatus::Running]);

        try {
            $user = User::query()->findOrFail($export->requested_by);
            $this->authorise($user, $export->include_contact);

            $path = "exports/{$export->id}.csv";
            $stream = fopen('php://temp', 'w+');
            fputcsv($stream, ['reference', 'full_name', 'phone', 'senatorial_district', 'lga', 'ward', 'polling_unit', 'verification_status', 'source', 'sms_updates', 'whatsapp_updates', 'wants_to_volunteer', 'registered_at'], escape: '');
            $rows = 0;

            $this->query($user, $export->filters ?? [])->lazyById(1000, 'supporters.id', 'id')->each(function (Supporter $s) use ($stream, $export, &$rows): void {
                $rows++;
                fputcsv($stream, array_map(fn ($v): string => ImportService::spreadsheetSafe((string) $v), [
                    $s->reference,
                    $s->full_name,
                    $export->include_contact ? $s->phone() : $s->maskedPhone(),
                    $s->getAttribute('district_name'),
                    $s->getAttribute('lga_name'),
                    $s->getAttribute('ward_name'),
                    $s->getAttribute('unit_code'),
                    $s->verification_status->label(),
                    $s->source->label(),
                    $s->getAttribute('sms_consent') ?? 'none',
                    $s->getAttribute('whatsapp_consent') ?? 'none',
                    $s->wants_to_volunteer ? 'yes' : 'no',
                    $s->created_at->timezone(config('campaign.display_timezone'))->format('Y-m-d H:i'),
                ]), escape: '');
            });

            rewind($stream);
            Storage::disk($export->disk)->put($path, $stream);
            fclose($stream);

            $export->update([
                'status' => ExportStatus::Completed,
                'path' => $path,
                'row_count' => $rows,
                'completed_at' => now(),
                'expires_at' => now()->addHours((int) config('campaign.privacy.export_ttl_hours')),
            ]);
            $this->audit->as('export-worker', fn () => $this->audit->record('export.completed', $export, null, ['rows' => $rows]));
        } catch (Throwable $exception) {
            report($exception);
            $export->update(['status' => ExportStatus::Failed, 'failure_reason' => $exception instanceof AuthorizationException ? 'The requester is no longer permitted to export.' : 'The export could not be generated.']);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Supporter>
     */
    public function query(User $user, array $filters): Builder
    {
        $consent = fn (string $channel): string => "(select status from consent_records c where c.phone_hash = supporters.phone_hash and c.channel = '{$channel}' and c.purpose = 'campaign_updates' order by c.recorded_at desc, c.id desc limit 1)";

        return Supporter::query()
            ->visibleTo($user, 'supporters.export')
            ->whereNull('supporters.erased_at')
            ->where('supporters.processing_restricted', false)
            ->leftJoin('senatorial_districts as d', 'd.id', '=', 'supporters.senatorial_district_id')
            ->leftJoin('lgas as l', 'l.id', '=', 'supporters.lga_id')
            ->leftJoin('wards as w', 'w.id', '=', 'supporters.ward_id')
            ->leftJoin('polling_units as pu', 'pu.id', '=', 'supporters.polling_unit_id')
            ->select('supporters.*', 'd.name as district_name', 'l.name as lga_name', 'w.name as ward_name', 'pu.code as unit_code')
            ->selectRaw($consent('sms').' as sms_consent')
            ->selectRaw($consent('whatsapp').' as whatsapp_consent')
            ->when($filters['lga_id'] ?? null, fn ($q, $v) => $q->where('supporters.lga_id', (int) $v))
            ->when($filters['ward_id'] ?? null, fn ($q, $v) => $q->where('supporters.ward_id', (int) $v))
            ->when($filters['verification_status'] ?? null, fn ($q, $v) => $q->where('supporters.verification_status', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('supporters.source', $v))
            ->when(($filters['consent_sms'] ?? null) === 'granted', fn ($q) => $q->whereRaw($consent('sms')." = 'granted'"));
    }

    public function recordDownload(ExportJob $export, User $user): void
    {
        DB::table('export_jobs')->where('id', $export->id)->update(['download_count' => DB::raw('download_count + 1'), 'last_downloaded_at' => now()]);
        $this->audit->record('export.downloaded', $export, null, ['rows' => $export->row_count, 'include_contact' => $export->include_contact], $user);
    }

    /** Deletes expired export files. */
    public function prune(): int
    {
        $count = 0;

        ExportJob::query()->where('status', ExportStatus::Completed)->where('expires_at', '<', now())->each(function (ExportJob $export) use (&$count): void {
            if ($export->path) {
                Storage::disk($export->disk)->delete($export->path);
            }
            $export->update(['status' => ExportStatus::Expired, 'path' => null]);
            $count++;
        });

        return $count;
    }

    private function authorise(User $user, bool $includeContact): void
    {
        if (! $user->isActive() || ! $user->hasPermission('supporters.export') || ($includeContact && ! $user->hasPermission('supporters.view-contact'))) {
            throw new AuthorizationException('You are not permitted to make this export.');
        }
    }
}
