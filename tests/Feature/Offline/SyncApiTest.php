<?php

declare(strict_types=1);

namespace Tests\Feature\Offline;

use App\Models\ContactAttempt;
use App\Models\User;
use App\Models\WalkList;
use App\Services\Canvassing\CanvassingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithSupporters;
use Tests\TestCase;

final class SyncApiTest extends TestCase
{
    use InteractsWithSupporters, RefreshDatabase;

    private User $coordinator;

    private User $volunteer;

    private WalkList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSupporterContext();
        $this->coordinator = User::factory()->withRole('ward_coordinator', $this->ward('udu')->id)->create();
        $this->volunteer = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();
        $canvassing = app(CanvassingService::class);
        $this->list = $canvassing->createWalkList($this->coordinator, 'Sample offline round', $this->ward('udu'), ['Stop A', 'Stop B', 'Stop C']);
        $canvassing->assign($this->list, $this->volunteer, $this->coordinator);
    }

    /** @return array<string, mixed> */
    private function item(int $position = 1, array $overrides = []): array
    {
        return $overrides + [
            'type' => 'contact',
            'uuid' => (string) Str::uuid(),
            'walk_list_entry_id' => $this->list->entries()->where('position', $position)->value('id'),
            'outcome' => 'reached',
            'notes' => 'Sample note',
            'occurred_at' => now()->subHours(2)->toIso8601String(),
        ];
    }

    private function sync(array $items, ?User $as = null)
    {
        return $this->signIn($as ?? $this->volunteer)->postJson(route('api.v1.sync'), ['device_id' => '6f1c2f8e-2d8a-4f5c-9a11-3c9b0f4f2a10', 'items' => $items]);
    }

    public function test_a_batch_succeeds_item_by_item_and_is_safe_to_resend(): void
    {
        $good = $this->item(1);
        $bad = $this->item(2, ['outcome' => 'voted_for_us']);

        $first = $this->sync([$good, $bad])->assertOk()->json();
        $this->assertSame(['created', 'rejected'], array_column($first['results'], 'status'));
        $this->assertSame(['created' => 1, 'rejected' => 1], $first['summary']);

        $again = $this->sync([$good])->assertOk()->json();
        $this->assertSame('duplicate', $again['results'][0]['status']);

        $attempt = ContactAttempt::query()->sole();
        $this->assertTrue($attempt->recorded_offline);
        $this->assertSame('6f1c2f8e-2d8a-4f5c-9a11-3c9b0f4f2a10', $attempt->device_id);
        $this->assertSame('Sample note', $attempt->notes_encrypted);
        $this->assertTrue($attempt->occurred_at->lt(now()->subHour()), 'The device capture time is kept');
    }

    public function test_conflicts_are_reported_when_the_server_state_changed(): void
    {
        $other = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();
        app(CanvassingService::class)->assign($this->list, $other, $this->coordinator);

        $result = $this->sync([$this->item()])->assertOk()->json('results.0');
        $this->assertSame('conflict', $result['status']);
        $this->assertStringContainsString('Sample offline round', $result['message']);

        app(CanvassingService::class)->archive($this->list, $this->coordinator);
        $this->assertSame('conflict', $this->sync([$this->item()], $other)->json('results.0.status'));
        $this->assertSame(0, ContactAttempt::query()->count());
    }

    public function test_stale_captures_foreign_uuids_and_unknown_stops_are_rejected(): void
    {
        $taken = $this->item(1);
        $this->sync([$taken]);
        $stranger = User::factory()->withRole('ward_coordinator', $this->ward('udu')->id)->create();

        $this->assertSame('rejected', $this->sync([$taken], $stranger)->json('results.0.status'));
        $this->assertSame('rejected', $this->sync([$this->item(2, ['occurred_at' => now()->subDays(20)->toIso8601String()])])->json('results.0.status'));
        $this->assertSame('rejected', $this->sync([$this->item(2, ['walk_list_entry_id' => 999999])])->json('results.0.status'));
    }

    public function test_the_envelope_is_validated_and_authentication_is_required(): void
    {
        $this->postJson(route('api.v1.sync'), ['items' => [$this->item()]])->assertUnauthorized();
        $this->getJson(route('api.v1.sync.walk-lists'))->assertUnauthorized();
        $this->sync([])->assertUnprocessable();
        $this->sync(array_fill(0, 101, $this->item()))->assertUnprocessable();
    }

    public function test_only_the_persons_active_assigned_lists_are_downloaded(): void
    {
        $this->registerSupporter('08031119999');
        $lists = $this->signIn($this->volunteer)->getJson(route('api.v1.sync.walk-lists'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->json('walk_lists');

        $this->assertCount(1, $lists);
        $this->assertSame($this->list->uuid, $lists[0]['uuid']);
        $this->assertSame(['Stop A', 'Stop B', 'Stop C'], array_column($lists[0]['entries'], 'label'));
        $this->assertArrayNotHasKey('phone', $lists[0]['entries'][0]);

        $this->assertSame([], $this->signIn($this->coordinator)->getJson(route('api.v1.sync.walk-lists'))->json('walk_lists'));
        $analyst = User::factory()->withRole('analyst')->create();
        $this->assertSame([], $this->signIn($analyst)->getJson(route('api.v1.sync.walk-lists'))->json('walk_lists'));
    }
}
