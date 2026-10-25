<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TrainingModule;
use App\Models\User;
use App\Models\WalkList;
use App\Services\Election\AgentService;
use App\Services\Reporting\ApprovalQueues;
use App\Services\Reporting\AreaMetrics;
use App\Services\Volunteers\TrainingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Role-aware home: my field work, decisions waiting on me, and an area snapshot for leaders. */
final class DashboardController extends Controller
{
    public function __invoke(Request $request, ApprovalQueues $approvals, AreaMetrics $metrics, TrainingService $training): View
    {
        /** @var User $user */
        $user = $request->user();

        $work = null;
        if ($user->can('tasks.work') || $user->can('canvassing.record')) {
            $modules = $user->can('training.take')
                ? TrainingModule::query()->whereNotNull('published_at')->whereIn('audience', $training->audiencesFor($user))->pluck('id')
                : collect();
            $results = $training->bestResults($user);

            $work = [
                'tasks' => Task::query()->open()->where('assigned_to', $user->id)->with(['ward:id,name', 'lga:id,name'])
                    ->orderByRaw('due_at asc nulls last')->limit(4)->get(),
                'open_tasks' => Task::query()->open()->where('assigned_to', $user->id)->count(),
                'lists' => WalkList::query()->where('assigned_to', $user->id)->whereIn('status', ['assigned', 'in_progress'])
                    ->withCount(['entries', 'entries as done_count' => fn ($q) => $q->where('status', '!=', 'pending')])->latest('id')->limit(3)->get(),
                'training_total' => $modules->count(),
                'training_passed' => $modules->filter(fn ($id) => $results->get($id)?->passed)->count(),
            ];
        }

        $snapshot = null;
        if ($user->can('command-centre.view')) {
            $scope = $user->geoScope('command-centre.view');
            $snapshot = $metrics->summary($metrics->breakdown($scope, $metrics->startLevel($scope)));
        }

        return view('pages.workspace.dashboard', [
            'user' => $user,
            'assignments' => $user->loadedAssignments(),
            'recentLogins' => $user->loginEvents()->latest('created_at')->limit(5)->get(),
            'work' => $work,
            'agentAssignment' => $user->can('election.report') ? app(AgentService::class)->activeAssignmentFor($user) : null,
            'queues' => $user->can('approvals.view') ? $approvals->for($user, 0) : [],
            'snapshot' => $snapshot,
            'maskSnapshot' => ! $user->can('supporters.view'),
        ]);
    }
}
