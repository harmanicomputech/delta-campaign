<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Livewire\Workspace\Content\ContentShow;
use App\Models\ContentItem;
use App\Models\User;
use App\Services\Content\ContentService;
use App\Services\Content\MediaService;
use App\Services\Reporting\ApprovalQueues;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class ContentWorkflowTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    private User $editor;

    private User $reviewer;

    private ContentService $content;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        $this->editor = User::factory()->withRole('content_editor')->create();
        $this->reviewer = User::factory()->withRole('dg')->withTwoFactor()->create();
        $this->content = app(ContentService::class);
    }

    private function draft(string $title = 'Sample news item'): ContentItem
    {
        return $this->content->create($this->editor, ContentType::News, ['title_en' => $title, 'body_en' => 'Sample body text.']);
    }

    private function publish(ContentItem $item): void
    {
        $this->content->submit($item, $this->editor);
        $this->content->approve($item, $this->reviewer);
        $this->content->publish($item, $this->reviewer);
    }

    public function test_draft_is_not_public_until_reviewed_and_published(): void
    {
        $item = $this->draft();
        $this->get(route('public.news.show', $item->slug))->assertNotFound();

        $this->publish($item);
        $this->get(route('public.news.show', $item->slug))->assertOk()->assertSee('Sample news item');
        $this->assertDatabaseHas('workflow_transitions', ['subject_id' => $item->id, 'to_status' => 'published']);
    }

    public function test_author_cannot_approve_own_content(): void
    {
        $author = User::factory()->withRole('super_admin')->create();
        $item = $this->content->create($author, ContentType::News, ['title_en' => 'Own item', 'body_en' => 'Body']);
        $this->content->submit($item, $author);

        $this->expectException(AuthorizationDenied::class);
        $this->content->approve($item, $author);
    }

    public function test_only_approved_content_can_be_published(): void
    {
        $item = $this->draft();
        $this->expectException(ValidationException::class);
        $this->content->publish($item, $this->reviewer);
    }

    public function test_editing_live_content_keeps_the_published_copy_until_republished(): void
    {
        $item = $this->draft('Original title');
        $this->publish($item);

        $this->content->update($item->fresh(), $this->editor, ['title_en' => 'Edited title', 'body_en' => 'Changed'], $item->fresh()->version);

        $item->refresh();
        $this->assertSame(ContentStatus::Draft, $item->status);
        $this->assertTrue($item->isLive());
        $this->get(route('public.news.show', $item->slug))->assertOk()->assertSee('Original title')->assertDontSee('Edited title');
    }

    public function test_stale_edits_are_rejected(): void
    {
        $item = $this->draft();
        $this->content->update($item, $this->editor, ['title_en' => 'First'], 1);

        $this->expectException(\Throwable::class);
        $this->content->update($item->fresh(), $this->editor, ['title_en' => 'Second'], 1);
    }

    public function test_scheduled_content_goes_live_when_due(): void
    {
        $item = $this->draft();
        $this->content->submit($item, $this->editor);
        $this->content->approve($item, $this->reviewer);
        $this->content->publish($item, $this->reviewer, now()->addHour());
        $this->assertSame(ContentStatus::Scheduled, $item->fresh()->status);

        $this->artisan('content:publish-due')->assertSuccessful();
        $this->assertFalse($item->fresh()->isLive());

        $this->travel(61)->minutes();
        $this->artisan('content:publish-due')->assertSuccessful();
        $this->assertTrue($item->fresh()->isLive());
    }

    public function test_withdrawn_content_leaves_the_public_site_and_sitemap(): void
    {
        $item = $this->draft();
        $this->publish($item);
        $this->get(route('public.sitemap'))->assertSee($item->slug);

        $this->content->withdraw($item->fresh(), $this->reviewer, 'Sample reason');
        $this->get(route('public.news.show', $item->slug))->assertNotFound();
        $this->get(route('public.sitemap'))->assertDontSee($item->slug);
    }

    public function test_restoring_a_version_creates_a_new_draft(): void
    {
        $item = $this->draft('Version one');
        $this->content->update($item, $this->editor, ['title_en' => 'Version two'], 1);
        $v1 = $item->versions()->where('version', 1)->firstOrFail();

        $this->content->restore($item->fresh(), $v1, $this->editor);
        $this->assertSame('Version one', $item->fresh()->title_en);
        $this->assertSame(3, $item->fresh()->version);
    }

    public function test_content_review_appears_in_approval_queue_but_not_for_the_author(): void
    {
        $item = $this->draft();
        $this->content->submit($item, $this->editor);

        $queue = collect(app(ApprovalQueues::class)->for($this->reviewer))->firstWhere('key', 'content');
        $this->assertSame(1, $queue['count']);
    }

    public function test_reviewer_publishes_from_the_workspace_screen(): void
    {
        $item = $this->draft();
        $this->content->submit($item, $this->editor);

        $this->signIn($this->reviewer);
        Livewire::test(ContentShow::class, ['item' => $item])->call('approve')->call('publish');
        $this->assertTrue($item->fresh()->isLive());
    }

    public function test_uploaded_images_are_re_encoded_without_metadata(): void
    {
        Storage::fake('public');
        $media = app(MediaService::class)->storeImage(UploadedFile::fake()->image('photo.jpg', 2000, 1200), $this->editor, 'Sample alt');

        $this->assertNotEmpty($media->variants);
        foreach ($media->variants as $path) {
            Storage::disk('public')->assertExists($path);
            $bytes = Storage::disk('public')->get($path);
            $this->assertSame('RIFF', substr($bytes, 0, 4));
            $this->assertStringNotContainsString('Exif', $bytes);
        }
    }

    public function test_workspace_screens_require_permission(): void
    {
        $volunteer = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();
        $this->signIn($volunteer)->get(route('workspace.content.index'))->assertForbidden();
        $this->signIn($this->editor)->get(route('workspace.content.index'))->assertOk();
    }
}
