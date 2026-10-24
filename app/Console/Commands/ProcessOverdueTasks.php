<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tasks\TaskService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tasks:process-overdue')]
#[Description('Remind assignees of overdue tasks and escalate long-overdue tasks')]
final class ProcessOverdueTasks extends Command
{
    public function handle(TaskService $tasks): int
    {
        $result = $tasks->processOverdue();
        $this->info("Reminded {$result['reminded']}, escalated {$result['escalated']}.");

        return self::SUCCESS;
    }
}
