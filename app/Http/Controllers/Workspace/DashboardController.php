<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('pages.workspace.dashboard', [
            'user' => $user,
            'assignments' => $user->loadedAssignments(),
            'recentLogins' => $user->loginEvents()->latest('created_at')->limit(5)->get(),
        ]);
    }
}
