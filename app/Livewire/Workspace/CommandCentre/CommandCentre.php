<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\CommandCentre;

use App\Models\Lga;
use App\Models\SenatorialDistrict;
use App\Models\Task;
use App\Models\User;
use App\Services\Reporting\ApprovalQueues;
use App\Services\Reporting\AreaMetrics;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Leadership view: aggregate metrics for the viewer's area with drill-down
 * (district → LGA → ward), 30-day trends, what needs a decision and escalations.
 * Refreshes every minute (aggregates are cached for the same period).
 */
final class CommandCentre extends Component
{
    /** Counts below this are shown as "<5" to people who cannot see individual supporters. */
    public const int SMALL_NUMBER = 5;

    #[Url(except: '')]
    public string $level = '';

    #[Url(except: null)]
    public ?int $parent = null;

    public function mount(AreaMetrics $metrics): void
    {
        $this->authorize('command-centre.view');
        $this->normalise($metrics);
    }

    public function drill(int $id, AreaMetrics $metrics): void
    {
        $child = $metrics->childLevel($this->currentLevel($metrics));
        if ($child !== null) {
            $this->level = $child;
            $this->parent = $id;
            $this->normalise($metrics);
        }
    }

    public function up(AreaMetrics $metrics): void
    {
        $level = $this->currentLevel($metrics);

        if ($level === 'ward' && $this->parent !== null) {
            $this->level = 'lga';
            $this->parent = Lga::query()->whereKey($this->parent)->value('senatorial_district_id');
        } else {
            $this->level = '';
            $this->parent = null;
        }
        $this->normalise($metrics);
    }

    public function render(AreaMetrics $metrics, ApprovalQueues $approvals): View
    {
        $actor = $this->actor();
        $scope = $actor->geoScope('command-centre.view');
        $level = $this->currentLevel($metrics);

        $rows = $metrics->breakdown($scope, $level, $this->parent);
        $summary = $metrics->summary($rows);

        return view('livewire.workspace.command-centre.command-centre', [
            'currentLevel' => $level,
            'canDrill' => $metrics->childLevel($level) !== null,
            'rows' => $rows,
            'summary' => $summary,
            'trend' => $metrics->trend($scope, $level, $this->parent !== null ? $rows->pluck('id')->all() : null),
            'mask' => ! $actor->can('supporters.view'),
            'parentName' => $this->parentName($level),
            'atTop' => $this->level === '' || $level === $metrics->startLevel($scope),
            'queues' => $actor->can('approvals.view') ? $approvals->for($actor, 3) : [],
            'escalations' => $actor->can('tasks.view') ? Task::query()->visibleTo($actor, 'tasks.view')->open()->where('escalation_level', '>', 0)
                ->with(['ward:id,name', 'lga:id,name'])->orderByDesc('escalated_at')->limit(5)->get() : collect(),
        ])->title(__('command.title'))->layoutData(['breadcrumbs' => [__('nav.groups.overview') => null, __('command.title') => null]]);
    }

    /** Formats a count, hiding small numbers from viewers without access to individual records. */
    public static function figure(int $value, bool $mask): string
    {
        return $mask && $value > 0 && $value < self::SMALL_NUMBER ? '<'.self::SMALL_NUMBER : number_format($value);
    }

    private function currentLevel(AreaMetrics $metrics): string
    {
        return $this->level !== '' ? $this->level : $metrics->startLevel($this->actor()->geoScope('command-centre.view'));
    }

    /** Ignores tampered URL parameters: unknown levels, or a parent outside the viewer's scope. */
    private function normalise(AreaMetrics $metrics): void
    {
        $scope = $this->actor()->geoScope('command-centre.view');

        if (! array_key_exists($this->level, AreaMetrics::LEVELS) || $this->parent === null) {
            $this->level = '';
            $this->parent = null;

            return;
        }

        $parentLevel = ['lga' => 'district', 'ward' => 'lga'][$this->level] ?? null;
        $visible = $parentLevel !== null && $metrics->areas($scope, $parentLevel)->has($this->parent);
        $start = $metrics->startLevel($scope);

        // A parent above the viewer's own level (e.g. a district for an LGA coordinator) is not allowed either.
        $order = ['district' => 0, 'lga' => 1, 'ward' => 2];
        if (! $visible || $order[$parentLevel] < $order[$start]) {
            $this->level = '';
            $this->parent = null;
        }
    }

    private function parentName(string $level): ?string
    {
        return match (true) {
            $this->parent === null => null,
            $level === 'lga' => SenatorialDistrict::query()->whereKey($this->parent)->value('name'),
            $level === 'ward' => Lga::query()->whereKey($this->parent)->value('name'),
            default => null,
        };
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
