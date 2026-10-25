<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Arr;
use Tests\TestCase;

final class LocaleTest extends TestCase
{
    public function test_switching_to_pidgin_persists_in_session_and_cookie(): void
    {
        $response = $this->from('/')->post('/locale', ['locale' => 'pcm']);

        $response->assertRedirect('/')
            ->assertSessionHas('locale', 'pcm')
            ->assertCookie(SetLocale::COOKIE, 'pcm');

        $this->get('/')->assertSee('lang="pcm"', false)->assertSee(__('public.nav_contact', [], 'pcm'));
    }

    public function test_unsupported_locales_are_rejected(): void
    {
        $this->from('/')->post('/locale', ['locale' => 'fr'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('locale')
            ->assertSessionMissing('locale');
    }

    public function test_redirects_only_to_same_origin_pages(): void
    {
        $this->from('https://evil.example/phish')->post('/locale', ['locale' => 'en'])
            ->assertRedirect(route('home'));
    }

    public function test_browser_language_is_used_when_no_choice_was_made(): void
    {
        $this->get('/', ['Accept-Language' => 'pcm,en;q=0.5'])->assertSee('lang="pcm"', false);
        $this->get('/', ['Accept-Language' => 'fr-FR'])->assertSee('lang="en"', false);
    }

    public function test_every_english_translation_key_exists_in_pidgin(): void
    {
        foreach (glob(lang_path('en/*.php')) as $file) {
            $group = basename($file, '.php');
            $english = array_keys(Arr::dot(require $file));
            $pidgin = array_keys(Arr::dot(require lang_path("pcm/{$group}.php")));

            $this->assertSame([], array_values(array_diff($english, $pidgin)), "Missing Pidgin keys in {$group}.php");
        }
    }
}
