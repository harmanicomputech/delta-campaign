<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Content\ContentService;
use Illuminate\Console\Command;

/** Publishes scheduled content whose time has come. Runs every minute. */
final class PublishDueContent extends Command
{
    protected $signature = 'content:publish-due';

    protected $description = 'Publish scheduled content whose publish time has passed';

    public function handle(ContentService $content): int
    {
        $count = $content->publishDue();
        $this->info("Published {$count} scheduled item(s).");

        return self::SUCCESS;
    }
}
