<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ContentType;
use App\Enums\ScopeType;
use App\Models\ContactMessage;
use App\Models\ContentItem;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Models\Ward;
use App\Services\Content\ContentService;
use App\Services\Events\EventService;
use App\Services\Projects\ProjectService;
use App\Services\RapidResponse\RapidResponseService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * SAMPLE public-site content so the website and CMS can be tried in
 * development. Every record is flagged is_sample or titled "SAMPLE"; nothing
 * here describes the real candidate, real projects or real results. The About
 * and Manifesto pages are left as drafts with placeholders, so the public site
 * shows "Coming soon" until approved text exists. Never runs in production.
 */
class SamplePublicSiteSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample public-site data must never be seeded in production.');
        }
        if (ContentItem::query()->where('is_sample', true)->exists()) {
            return;
        }

        $ward = Ward::query()->where('is_sample', true)->orderBy('id')->firstOrFail();
        $editor = $this->person('Sample Content Editor', '08170000011', 'content_editor');
        $reviewer = $this->person('Sample Communications Reviewer', '08170000012', 'dg');
        $officer = $this->person('Sample Rapid-Response Officer', '08170000013', 'rapid_response');

        $content = app(ContentService::class);
        foreach (['about' => 'About', 'manifesto' => 'Manifesto'] as $slug => $title) {
            $page = $content->create($editor, ContentType::Page, [
                'title_en' => $title, 'slug' => $slug,
                'body_en' => "[PLACEHOLDER: approved {$slug} text, supplied and signed off by the campaign]",
            ]);
            $page->forceFill(['is_sample' => true])->save();
        }

        $news = $content->create($editor, ContentType::News, [
            'title_en' => 'SAMPLE: Ward volunteer meetings this month',
            'summary_en' => 'Illustrative news item showing the format. Not a real announcement.',
            'body_en' => "> **SAMPLE content** for development and training.\n\nWard coordinators are holding volunteer meetings. Check the events page for times in your area.\n\n- Bring your volunteer identification.\n- Training modules are available in the field app.",
        ]);
        $factCheck = $content->create($editor, ContentType::FactCheck, [
            'title_en' => 'SAMPLE: Claim about volunteer payments',
            'summary_en' => 'Illustrative fact-check showing the format. Not a real claim.',
            'body_en' => "> **SAMPLE content** for development and training.\n\n**Claim:** [SAMPLE] Volunteers are paid to attend events.\n\n**Verdict:** False\n\nThe campaign does not pay people to attend events or to support it.",
        ]);
        foreach ([$news, $factCheck] as $item) {
            $item->forceFill(['is_sample' => true])->save();
            $content->submit($item, $editor);
            $content->approve($item, $reviewer);
            $content->publish($item, $reviewer);
        }

        $projects = app(ProjectService::class);
        $project = $projects->save($editor, [
            'title' => 'SAMPLE: Community borehole (illustrative record)',
            'description' => "> **SAMPLE record** for development and training. It does not describe a real project.\n\nShows how a project record looks: what was done, where, and the source of the information.",
            'category' => 'water', 'ward_id' => $ward->id, 'location' => '[SAMPLE] Near the market square',
            'source_note' => '[SAMPLE] Site visit report (illustrative)', 'verification' => 'unverified',
        ]);
        $project->forceFill(['is_sample' => true])->save();
        $projects->submit($project, $editor);
        $projects->publish($project, $reviewer);

        $events = app(EventService::class);
        $event = $events->save($editor, [
            'title_en' => 'SAMPLE: Ward town hall',
            'title_pcm' => 'SAMPLE: Ward town hall meeting',
            'description_en' => "> **SAMPLE event** for development and training.\n\nAn illustrative town hall where residents can meet ward volunteers.",
            'starts_at' => now()->addDays(14)->setTime(16, 0), 'ends_at' => now()->addDays(14)->setTime(18, 0),
            'venue' => '[SAMPLE] Community hall', 'ward_id' => $ward->id, 'capacity' => 150,
        ]);
        $event->forceFill(['is_sample' => true])->save();
        $events->publish($event, $editor);

        $rapid = app(RapidResponseService::class);
        $case = $rapid->open($officer, [
            'title' => 'SAMPLE: Edited photo shared on social media',
            'claim' => '[SAMPLE] An illustrative claim used to show the rapid-response workflow.',
            'priority' => 'high', 'deadline_at' => now()->addHours(6),
        ]);
        $rapid->startInvestigation($case, $officer);
        $rapid->addNote($case, $officer, 'SAMPLE note: compared against the original image source.');

        $message = new ContactMessage(['name' => 'Sample Person', 'topic' => 'general', 'lga_id' => $ward->lga_id]);
        $message->message_encrypted = '[SAMPLE] When is the next town hall in my ward?';
        $message->save();
    }

    private function person(string $name, string $phone, string $role): User
    {
        $user = User::findByPhone($phone) ?? tap(new User(['name' => $name]), fn (User $u) => $u->setPhone($phone)->save());

        $roleId = Role::query()->where('slug', $role)->value('id');
        if (! $user->activeAssignments()->where('role_id', $roleId)->exists()) {
            RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $roleId, 'scope_type' => ScopeType::State, 'scope_id' => null]);
            $user->flushAuthorizationCache();
        }

        return $user;
    }
}
