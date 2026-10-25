<?php

declare(strict_types=1);

namespace App\Services\Election;

use App\Jobs\BuildEvidencePack;
use App\Models\Election;
use App\Models\EvidenceFile;
use App\Models\EvidencePack;
use App\Models\Incident;
use App\Models\Lga;
use App\Models\OfficialResult;
use App\Models\PollingUnit;
use App\Models\ResultSubmission;
use App\Models\User;
use App\Models\Ward;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\GeoScope;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Evidence packs: a ZIP with every result submission, official figure and
 * incident for an area, the original evidence files, and a manifest listing
 * each file's SHA-256 (re-checked while building). The pack's own hash is
 * recorded so a copy can be checked later.
 */
final class EvidencePackService
{
    public function __construct(private readonly AuditLogger $audit, private readonly EvidenceStore $store) {}

    public function request(User $by, Election $election, string $scopeType, ?int $scopeId): EvidencePack
    {
        if (! $by->hasPermission('evidence.export')) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
        $scope = $this->scopeFor($scopeType, $scopeId);
        $mine = $by->geoScope('evidence.export');
        $allowed = match ($scopeType) {
            'state' => $mine->statewide,
            'lga' => $mine->contains(Lga::query()->findOrFail($scopeId)->senatorial_district_id, $scopeId),
            'ward' => ($w = Ward::query()->findOrFail($scopeId)) && $mine->contains($w->senatorial_district_id, $w->lga_id, $w->id),
            'polling_unit' => ($u = PollingUnit::query()->findOrFail($scopeId)) && $mine->contains($u->senatorial_district_id, $u->lga_id, $u->ward_id, $u->id),
            default => false,
        };
        if (! $allowed || $scope === null) {
            throw new AuthorizationDenied(__('election.pack_outside_area'));
        }

        $pack = EvidencePack::query()->create(['election_id' => $election->id, 'requested_by' => $by->id, 'scope_type' => $scopeType, 'scope_id' => $scopeId]);
        $this->audit->record('evidence_pack.requested', $pack, null, ['scope' => $scopeType.':'.($scopeId ?? 'state')], $by);
        BuildEvidencePack::dispatch($pack->id);

        return $pack;
    }

    public function build(EvidencePack $pack): void
    {
        $pack->forceFill(['status' => 'building'])->save();
        $scope = $this->scopeFor($pack->scope_type, $pack->scope_id) ?? GeoScope::none();
        $election = $pack->election;
        $tmp = tempnam(sys_get_temp_dir(), 'pack');

        try {
            $zip = new ZipArchive;
            $zip->open($tmp, ZipArchive::OVERWRITE);
            $manifest = ['election' => $election->name, 'scope' => $pack->scope_type.':'.($pack->scope_id ?? 'state'), 'generated_at' => now()->toIso8601String(),
                'notice' => 'Campaign records. Submitted and verified figures are NOT official INEC results. Official figures are transcribed with their INEC source.', 'files' => []];

            $submissions = ResultSubmission::query()->where('election_id', $election->id)->visibleTo($scope)->with(['pollingUnit:id,code,name', 'evidence', 'submitter:id,name', 'reviewer:id,name'])->orderBy('polling_unit_id')->get();
            $official = OfficialResult::query()->where('election_id', $election->id)->visibleTo($scope)->with(['pollingUnit:id,code,name', 'evidence'])->get();
            $incidents = Incident::query()->where('election_id', $election->id)->visibleTo($scope)->with(['pollingUnit:id,code,name', 'evidence'])->get();

            $zip->addFromString('result_submissions.csv', $this->csv(['reference', 'polling_unit', 'status', 'source', 'submitted_by', 'submitted_at', 'reviewed_by', 'accredited', 'valid', 'rejected', 'total_cast', 'scores', 'issues'],
                $submissions->map(fn (ResultSubmission $s) => [$s->reference, $s->pollingUnit->code, $s->status->value, $s->source, $s->submitter?->name, $s->created_at->toIso8601String(), $s->reviewer?->name,
                    $s->accredited_voters, $s->valid_votes, $s->rejected_votes, $s->total_votes_cast, json_encode($s->scores), implode('; ', array_column($s->issues, 'code'))])->all()));
            $zip->addFromString('official_results.csv', $this->csv(['polling_unit', 'source', 'source_reference', 'valid', 'rejected', 'total_cast', 'scores', 'recorded_at'],
                $official->map(fn (OfficialResult $o) => [$o->pollingUnit->code, $o->source->value, $o->source_reference, $o->valid_votes, $o->rejected_votes, $o->total_votes_cast, json_encode($o->scores), $o->updated_at->toIso8601String()])->all()));
            $zip->addFromString('incidents.csv', $this->csv(['reference', 'polling_unit', 'category', 'severity', 'status', 'occurred_at', 'description', 'resolution'],
                $incidents->map(fn (Incident $i) => [$i->reference, $i->pollingUnit?->code, $i->category->value, $i->severity->value, $i->status->value, $i->occurred_at->toIso8601String(), $i->description, $i->resolution])->all()));

            $count = 0;
            foreach ([['results', $submissions], ['official', $official], ['incidents', $incidents]] as [$folder, $records]) {
                foreach ($records as $record) {
                    foreach ($record->evidence as $file) {
                        /** @var EvidenceFile $file */
                        $intact = $this->store->verify($file);
                        $name = sprintf('evidence/%s/%s/%s-%s', $folder, $record->pollingUnit?->code ?? 'none', substr($file->uuid, 0, 8), preg_replace('/[^A-Za-z0-9._-]/', '_', $file->original_name));
                        if (Storage::disk($file->disk)->exists($file->path)) {
                            $zip->addFromString($name, (string) Storage::disk($file->disk)->get($file->path));
                            $count++;
                        }
                        $manifest['files'][] = ['path' => $name, 'sha256' => $file->sha256, 'integrity_ok' => $intact, 'record' => $record->reference ?? $record->pollingUnit?->code, 'uploaded_at' => $file->created_at->toIso8601String()];
                    }
                }
            }
            $zip->addFromString('manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $zip->addFromString('README.txt', "Evidence pack for {$election->name}.\n\nSubmitted and campaign-verified figures are campaign records, NOT official INEC results.\nmanifest.json lists the SHA-256 of every original evidence file; integrity_ok shows whether each file still matched its hash when this pack was built.\n");
            $zip->close();

            $disk = (string) config('campaign.election.evidence_disk');
            $path = 'evidence-packs/'.$pack->uuid.'.zip';
            Storage::disk($disk)->put($path, (string) file_get_contents($tmp));
            $pack->forceFill(['status' => 'ready', 'disk' => $disk, 'path' => $path, 'sha256' => hash_file('sha256', $tmp), 'file_count' => $count,
                'expires_at' => now()->addHours((int) config('campaign.election.pack_ttl_hours'))])->save();
        } catch (\Throwable $e) {
            $pack->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 300)])->save();
            throw $e;
        } finally {
            @unlink($tmp);
        }
    }

    public function recordDownload(EvidencePack $pack, User $by): void
    {
        $this->audit->record('evidence_pack.downloaded', $pack, null, ['sha256' => $pack->sha256], $by);
    }

    public function prune(): int
    {
        $count = 0;
        EvidencePack::query()->where('status', 'ready')->where('expires_at', '<', now())->each(function (EvidencePack $pack) use (&$count): void {
            Storage::disk((string) $pack->disk)->delete((string) $pack->path);
            $pack->forceFill(['status' => 'expired', 'path' => null])->save();
            $count++;
        });

        return $count;
    }

    private function scopeFor(string $type, ?int $id): ?GeoScope
    {
        return match ($type) {
            'state' => GeoScope::statewideScope(),
            'lga' => $id ? new GeoScope(lgaIds: [$id]) : null,
            'ward' => $id ? new GeoScope(wardIds: [$id]) : null,
            'polling_unit' => $id ? new GeoScope(pollingUnitIds: [$id]) : null,
            default => throw ValidationException::withMessages(['scope' => __('validation.in', ['attribute' => 'scope'])]),
        };
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<mixed>>  $rows
     */
    private function csv(array $header, array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $header, escape: '');
        foreach ($rows as $row) {
            // Neutralise spreadsheet formulas.
            fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) === 1 ? "'".$v : $v, $row), escape: '');
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }
}
