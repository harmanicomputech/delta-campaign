<?php

declare(strict_types=1);

namespace Tests\Feature\Election;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\ResultStatus;
use App\Livewire\Workspace\Election\AgentHome;
use App\Models\User;
use App\Notifications\IncidentReported;
use App\Services\Election\AgentService;
use App\Services\Election\ElectionSetup;
use App\Services\Election\FieldReports;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

final class AgentsAndReportsTest extends ElectionTestCase
{
    public function test_activation_requires_parties_and_closes_other_elections(): void
    {
        $setup = app(ElectionSetup::class);
        $second = $setup->save($this->director, ['name' => 'Second', 'election_date' => now()->toDateString(), 'checklist' => ['A']], []);
        try {
            $setup->activate($second, $this->director);
            $this->fail('Activation without parties must fail.');
        } catch (ValidationException) {
        }

        $setup->save($this->director, ['name' => 'Second', 'election_date' => now()->toDateString(), 'checklist' => ['A']], [['code' => 'X1', 'name' => 'X'], ['code' => 'X2', 'name' => 'Y']], $second);
        $setup->activate($second, $this->director);
        $this->assertSame('closed', $this->election->fresh()->status->value);

        $this->expectException(ValidationException::class);
        $setup->save($this->director, ['name' => 'Second', 'election_date' => now()->toDateString(), 'checklist' => ['A']], [['code' => 'bad code!', 'name' => 'X']], $second->fresh('parties'));
    }

    public function test_assignment_grants_and_withdrawal_revokes_unit_access(): void
    {
        $unit = $this->unit();
        $assignment = $this->agent($unit);
        $agent = $assignment->user;

        $this->assertTrue($agent->hasPermission('election.report'));
        $this->assertTrue($agent->geoScope('election.report')->contains($unit->senatorial_district_id, $unit->lga_id, $unit->ward_id, $unit->id));
        $this->assertSame(1, $this->messagesTo('+2348061230001'));

        app(AgentService::class)->withdraw($this->director, $assignment, 'Sample reason');
        $agent->flushAuthorizationCache();
        $this->assertFalse($agent->fresh()->hasPermission('election.report'));
    }

    public function test_coordinators_assign_only_inside_their_area_and_one_primary_per_unit(): void
    {
        $udu = $this->unit('udu', 'SMP-001');
        $other = $this->unit('warri-south', 'SMP-900');
        $coordinator = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();
        $agents = app(AgentService::class);

        $agents->assign($coordinator, $this->election, $udu, 'Sample One', '08061230001');
        try {
            $agents->assign($coordinator, $this->election, $udu, 'Sample Two', '08061230002');
            $this->fail('Second primary must be refused.');
        } catch (ValidationException) {
        }
        $agents->assign($coordinator, $this->election, $udu, 'Sample Two', '08061230002', 'backup');

        $this->expectException(AuthorizationDenied::class);
        $agents->assign($coordinator, $this->election, $other, 'Sample Three', '08061230003');
    }

    public function test_arrival_records_distance_from_the_unit(): void
    {
        $assignment = $this->agent($this->unit(location: [5.5, 5.75]));
        $checkin = app(FieldReports::class)->arrive($assignment, $assignment->user, 'web', 5.509, 5.75, 15);

        $this->assertEqualsWithDelta(1000, $checkin->distance_m, 30);
        $this->assertNotNull($assignment->fresh()->arrived_at);
    }

    public function test_agents_report_only_for_their_own_unit_while_the_election_is_active(): void
    {
        $mine = $this->agent($this->unit('udu', 'SMP-001'));
        $theirs = $this->agent($this->unit('udu', 'SMP-002'), '08061230002');
        $reports = app(FieldReports::class);

        try {
            $reports->turnout($theirs, $mine->user, 10, 'web');
            $this->fail('Reporting for another unit must fail.');
        } catch (AuthorizationDenied) {
        }

        app(ElectionSetup::class)->close($this->election, $this->director);
        $this->expectException(AuthorizationDenied::class);
        $reports->turnout($mine->fresh(), $mine->user, 10, 'web');
    }

    public function test_turnout_cannot_exceed_registered_voters_and_retries_are_idempotent(): void
    {
        $assignment = $this->agent($this->unit(registered: 300));
        $reports = app(FieldReports::class);
        $uuid = (string) Str::uuid();

        $first = $reports->turnout($assignment, $assignment->user, 120, 'web', clientUuid: $uuid);
        $again = $reports->turnout($assignment, $assignment->user, 120, 'web', clientUuid: $uuid);
        $this->assertSame($first->id, $again->id);

        $this->expectException(ValidationException::class);
        $reports->turnout($assignment, $assignment->user, 301, 'web');
    }

    public function test_high_severity_incidents_alert_area_coordinators_and_keep_hashed_evidence(): void
    {
        Notification::fake();
        $unit = $this->unit();
        $coordinator = User::factory()->withRole('lga_coordinator', $unit->lga_id)->create();
        $assignment = $this->agent($unit);

        $photo = $this->photo();
        $hash = hash_file('sha256', $photo->getRealPath());
        $incident = app(FieldReports::class)->incident($assignment, $assignment->user, IncidentCategory::Violence, IncidentSeverity::High, 'Sample description of an incident', 'web', [$photo]);

        Notification::assertSentTo($coordinator, IncidentReported::class);
        $this->assertSame($hash, $incident->evidence()->firstOrFail()->sha256);
    }

    public function test_result_arithmetic_problems_are_flagged_not_corrected(): void
    {
        $assignment = $this->agent($this->unit(registered: 100));
        $result = app(FieldReports::class)->result($assignment, $assignment->user, $this->figures(accredited: 110, valid: 100, rejected: 5, total: 110), ['SPA' => 60, 'SPB' => 30], 'web', [$this->photo()]);

        $this->assertSame(ResultStatus::Flagged, $result->status);
        $codes = array_column($result->issues, 'code');
        foreach (['sum_valid', 'total_cast', 'over_accreditation'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertSame(['SPA' => 60, 'SPB' => 30], $result->scores);
    }

    public function test_web_results_need_a_photo_and_a_new_sheet_replaces_the_agents_unreviewed_one(): void
    {
        $assignment = $this->agent($this->unit());
        $reports = app(FieldReports::class);
        try {
            $reports->result($assignment, $assignment->user, $this->figures(), ['SPA' => 60, 'SPB' => 40], 'web');
            $this->fail('A photo is required.');
        } catch (ValidationException) {
        }

        $first = $reports->result($assignment, $assignment->user, $this->figures(), ['SPA' => 60, 'SPB' => 40], 'web', [$this->photo()]);
        $second = $reports->result($assignment, $assignment->user, $this->figures(), ['SPA' => 61, 'SPB' => 39], 'web', [$this->photo()]);

        $this->assertSame(ResultStatus::Superseded, $first->fresh()->status);
        $this->assertSame($first->id, $second->supersedes_id);
        $this->assertSame(ResultStatus::Submitted, $second->status);
    }

    public function test_conflicting_sheets_from_two_agents_are_flagged(): void
    {
        $unit = $this->unit();
        $primary = $this->agent($unit);
        $backup = $this->agent($unit, '08061230002', 'backup');
        $reports = app(FieldReports::class);

        $reports->result($primary, $primary->user, $this->figures(), ['SPA' => 60, 'SPB' => 40], 'web', [$this->photo()]);
        $second = $reports->result($backup, $backup->user, $this->figures(), ['SPA' => 70, 'SPB' => 30], 'web', [$this->photo()]);

        $this->assertSame(ResultStatus::Flagged, $second->status);
        $this->assertContains('conflict', array_column($second->issues, 'code'));
    }

    public function test_agent_screen_submits_a_result_with_photo(): void
    {
        $assignment = $this->agent($this->unit());
        $this->signIn($assignment->user);

        Livewire::test(AgentHome::class)->set('tab', 'result')
            ->set('accredited', '120')->set('valid', '100')->set('rejected', '5')->set('totalCast', '105')
            ->set('scores.SPA', '60')->set('scores.SPB', '40')->set('sheetPhoto', $this->photo())
            ->call('submitResult')->assertHasNoErrors();

        $this->assertSame(1, $assignment->fresh()->pollingUnit->agentAssignments()->count());
        $this->assertDatabaseHas('result_submissions', ['polling_unit_id' => $assignment->polling_unit_id, 'valid_votes' => 100, 'status' => 'submitted']);
        $this->get(route('workspace.election.agent'))->assertOk()->assertSee($assignment->pollingUnit->code);
    }

    public function test_people_without_an_assignment_see_an_explanation(): void
    {
        $agent = User::factory()->withRole('polling_agent', $this->unit()->id)->create();
        $this->signIn($agent)->get(route('workspace.election.agent'))->assertOk()->assertSee(__('election.no_assignment'));
    }
}
