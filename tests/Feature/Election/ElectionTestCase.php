<?php

declare(strict_types=1);

namespace Tests\Feature\Election;

use App\Models\AgentAssignment;
use App\Models\Election;
use App\Models\PollingUnit;
use App\Models\User;
use App\Services\Election\AgentService;
use App\Services\Election\ElectionSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

abstract class ElectionTestCase extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    protected User $director;

    protected Election $election;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        Storage::fake('local');
        $this->director = User::factory()->withRole('super_admin')->withTwoFactor()->create(['name' => 'Sample Director']);
        $this->election = app(ElectionSetup::class)->save($this->director, ['name' => 'Sample election', 'election_date' => now()->toDateString(), 'checklist' => ['Opened on time', 'Materials complete']],
            [['code' => 'SPA', 'name' => 'Sample Party A'], ['code' => 'SPB', 'name' => 'Sample Party B']]);
        app(ElectionSetup::class)->activate($this->election, $this->director);
        $this->election->refresh();
    }

    protected function unit(string $lga = 'udu', string $code = 'SMP-001', ?int $registered = 500, ?array $location = null): PollingUnit
    {
        $ward = $this->ward($lga);

        if ($existing = PollingUnit::query()->where('code', $code)->first()) {
            return $existing;
        }

        $unit = new PollingUnit;
        $unit->forceFill([
            'code' => $code, 'ward_id' => $ward->id, 'lga_id' => $ward->lga_id, 'senatorial_district_id' => $ward->lga->senatorial_district_id,
            'name' => "Sample unit {$code}", 'registered_voters' => $registered, 'is_sample' => true,
            'latitude' => $location[0] ?? null, 'longitude' => $location[1] ?? null,
        ])->save();

        return $unit->fresh();
    }

    protected function agent(PollingUnit $unit, string $phone = '08061230001', string $role = 'primary'): AgentAssignment
    {
        return app(AgentService::class)->assign($this->director, $this->election, $unit, 'Sample Agent '.substr($phone, -4), $phone, $role)->fresh(['user', 'pollingUnit', 'election']);
    }

    protected function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('sheet.jpg', 400, 300);
    }

    /** @return array{accredited_voters: int, valid_votes: int, rejected_votes: int, total_votes_cast: int} */
    protected function figures(int $accredited = 120, int $valid = 100, int $rejected = 5, ?int $total = null): array
    {
        return ['accredited_voters' => $accredited, 'valid_votes' => $valid, 'rejected_votes' => $rejected, 'total_votes_cast' => $total ?? $valid + $rejected];
    }
}
