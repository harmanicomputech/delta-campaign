<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Tests\TestCase;

final class PublicPagesTest extends TestCase
{
    public function test_home_page_renders_and_is_indexable(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee(__('public.cta_join'));
    }

    public function test_home_page_marks_unsupplied_candidate_content_as_placeholder(): void
    {
        $this->get('/')->assertSee('[PLACEHOLDER: candidate name]');
    }

    public function test_home_page_has_a_skip_link_and_landmarks(): void
    {
        $this->get('/')
            ->assertSee('href="#main"', false)
            ->assertSee('<main id="main"', false)
            ->assertSee('<footer', false);
    }

    public function test_unknown_pages_render_the_branded_404_with_a_reference(): void
    {
        $response = $this->get('/does-not-exist');

        $response->assertNotFound()
            ->assertSee(__('errors.404.title'))
            ->assertSee($response->headers->get('X-Request-Id'));
    }
}
