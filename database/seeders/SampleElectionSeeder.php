<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\ScopeType;
use App\Models\Election;
use App\Models\PollingUnit;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Election\AgentService;
use App\Services\Election\ElectionSetup;
use App\Services\Election\FieldReports;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * A SAMPLE training election so election-day screens can be tried in
 * development. Party codes are invented placeholders (SPA, SPB, SPC) that
 * represent no real party; every figure is illustrative. Never runs in production.
 */
class SampleElectionSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample election data must never be seeded in production.');
        }
        if (Election::query()->where('is_sample', true)->exists()) {
            return;
        }

        $units = PollingUnit::query()->where('is_sample', true)->orderBy('id')->limit(3)->get();
        if ($units->count() < 2) {
            return;
        }

        $admin = $this->person('Sample Election Administrator', '08170000020', 'state_director');
        RoleAssignment::query()->firstOrCreate(['user_id' => $admin->id, 'role_id' => Role::query()->where('slug', 'dg')->value('id'), 'scope_type' => ScopeType::State, 'scope_id' => null]);
        $admin->flushAuthorizationCache();

        $election = app(ElectionSetup::class)->save($admin, [
            'name' => 'SAMPLE: Training election',
            'election_date' => now()->toDateString(),
            'checklist' => (array) __('election.default_checklist'),
            'turnout_times' => ['10:00', '12:00', '14:00'],
        ], [
            ['code' => 'SPA', 'name' => 'Sample Party A (placeholder)'],
            ['code' => 'SPB', 'name' => 'Sample Party B (placeholder)'],
            ['code' => 'SPC', 'name' => 'Sample Party C (placeholder)'],
        ]);
        $election->forceFill(['is_sample' => true])->save();
        app(ElectionSetup::class)->activate($election, $admin);
        $election->refresh();

        $agents = app(AgentService::class);
        $reports = app(FieldReports::class);
        foreach ($units as $i => $unit) {
            $assignment = $agents->assign($admin, $election, $unit, sprintf('Sample Polling Agent %02d', $i + 1), sprintf('081700001%02d', $i + 1));
            $assignment->forceFill(['is_sample' => true])->save();
            if ($i === 2) {
                continue; // One agent has not reported yet.
            }
            $agent = $assignment->user;
            $reports->arrive($assignment, $agent, 'web');
            $reports->checklist($assignment, $agent, array_fill_keys(array_column($election->checklist, 'key'), true), 'web');
            $reports->turnout($assignment, $agent, 40 + 25 * $i, 'web', 'short');

            if ($i === 0) {
                $incident = $reports->incident($assignment, $agent, IncidentCategory::LateOpening, IncidentSeverity::Medium, 'SAMPLE: officials arrived late; voting started after the scheduled time.', 'web');
                $incident->forceFill(['is_sample' => true])->save();
            }

            $result = $reports->result($assignment, $agent,
                ['accredited_voters' => 120, 'valid_votes' => 100, 'rejected_votes' => 5, 'total_votes_cast' => 105],
                ['SPA' => 50 + $i * 5, 'SPB' => 30, 'SPC' => 20 - $i * 5], 'web', [$this->samplePhoto()]);
            $result->forceFill(['is_sample' => true])->save();
        }
    }

    private function samplePhoto(): UploadedFile
    {
        $image = imagecreatetruecolor(800, 600);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 245, 245, 240));
        $ink = (int) imagecolorallocate($image, 40, 40, 40);
        imagestring($image, 5, 40, 40, 'SAMPLE RESULT SHEET - NOT A REAL DOCUMENT', $ink);
        imagestring($image, 4, 40, 90, 'Generated for development and training only.', $ink);
        $path = tempnam(sys_get_temp_dir(), 'sample').'.jpg';
        imagejpeg($image, $path, 85);

        return new UploadedFile($path, 'sample-result-sheet.jpg', 'image/jpeg', null, true);
    }

    private function person(string $name, string $phone, string $role): User
    {
        $user = User::findByPhone($phone) ?? tap(new User(['name' => $name]), fn (User $u) => $u->setPhone($phone)->save());
        RoleAssignment::query()->firstOrCreate(['user_id' => $user->id, 'role_id' => Role::query()->where('slug', $role)->value('id'), 'scope_type' => ScopeType::State, 'scope_id' => null]);
        $user->flushAuthorizationCache();

        return $user;
    }
}
