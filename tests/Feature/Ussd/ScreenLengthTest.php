<?php

declare(strict_types=1);

namespace Tests\Feature\Ussd;

use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\Ward;
use App\Services\Ussd\Screens\OptionMenu;
use App\Services\Ussd\Screens\PagedChoice;
use App\Services\Ussd\Screens\TextInput;
use App\Services\Ussd\UssdMenu;
use App\Services\Ussd\UssdSession;
use Database\Seeders\GeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The original specification's hard limit: no USSD screen may exceed 182 characters. */
final class ScreenLengthTest extends TestCase
{
    use RefreshDatabase;

    private const int MAX = 182;

    private Ward $ward;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GeographySeeder::class);

        // Worst-case reference data: long names and many items.
        $lga = Lga::query()->where('name', 'Warri South West')->firstOrFail();
        foreach (range(1, 30) as $i) {
            $ward = Ward::query()->create(['lga_id' => $lga->id, 'code' => 'W'.$i, 'name' => str_repeat('Longwardname ', 5).$i, 'is_sample' => true]);
        }
        $this->ward = $ward;
        foreach (range(1, 30) as $i) {
            PollingUnit::query()->create(['ward_id' => $ward->id, 'code' => 'SMP-'.str_pad((string) $i, 30, '0', STR_PAD_LEFT), 'name' => str_repeat('Very long polling unit name ', 4).$i, 'is_sample' => true]);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function languages(): iterable
    {
        yield 'English' => ['en'];
        yield 'Pidgin' => ['pcm'];
    }

    private function ussdSession(string $lang): UssdSession
    {
        $session = new UssdSession('length-test');
        $session->lang = $lang;
        $session->data = ['name' => str_repeat('N', 60), 'lga_id' => $this->ward->lga_id, 'ward_id' => $this->ward->id, 'pref' => 'resume', 'topic' => 'volunteering'];

        return $session;
    }

    #[DataProvider('languages')]
    public function test_every_screen_page_and_error_fits(string $lang): void
    {
        $menu = app(UssdMenu::class);
        $errors = ['invalid_choice', 'invalid_name', 'invalid_code', 'pu_not_found'];
        $checked = 0;

        foreach ($menu->screens() as $key => $screen) {
            foreach (['stop', 'resume', 'delete'] as $pref) {
                $session = $this->ussdSession($lang);
                $session->data['pref'] = $pref;
                $screen->enter($session);

                do {
                    $prompt = $screen->prompt($session);
                    $this->assertLessThanOrEqual(self::MAX, mb_strlen($prompt), "[{$lang}] {$key}: ".mb_strlen($prompt)."\n".$prompt);

                    $relevant = $screen instanceof TextInput ? $errors : ['invalid_choice'];
                    foreach ($relevant as $error) {
                        if ($screen->handlesErrors()) {
                            $session->error = $session->t($error);
                            $withError = $screen->prompt($session);
                            $session->error = null;
                        } else {
                            $withError = $session->t($error)."\n".$prompt;
                        }
                        $this->assertLessThanOrEqual(self::MAX, mb_strlen($withError), "[{$lang}] {$key} with {$error}: ".mb_strlen($withError));
                    }
                    $checked++;
                } while ($screen instanceof PagedChoice && $screen->nextPage($session));

                if (! $screen instanceof OptionMenu || $key !== 'prefs.confirm') {
                    break;
                }
            }
        }

        $this->assertGreaterThan(20, $checked);
    }

    #[DataProvider('languages')]
    public function test_every_closing_message_fits_with_worst_case_values(string $lang): void
    {
        $session = $this->ussdSession($lang);
        $longestLga = Lga::query()->get()->sortByDesc(fn (Lga $l) => mb_strlen($l->name))->first()->name;

        $messages = [
            $session->t('join_done', ['name' => str_repeat('N', 29).'…', 'ref' => 'SUP-XXXXXXXXXX']),
            $session->t('join_done_sms', ['name' => str_repeat('N', 29).'…', 'ref' => 'SUP-XXXXXXXXXX']),
            $session->t('join_exists', ['ref' => 'SUP-XXXXXXXXXX']),
            $session->t('pu_details', ['name' => str_repeat('P', 44).'…', 'code' => str_repeat('C', 40), 'ward' => str_repeat('W', 34).'…', 'lga' => $longestLga])."\n".$session->t('sample'),
            $session->t('projects_none', ['lga' => $longestLga]),
            $session->t('callback_done', ['ref' => 'CB-XXXXXX']),
            $session->t('callback_exists', ['ref' => 'CB-XXXXXX']),
            $session->t('delete_done', ['ref' => 'DSR-XXXXXXXXXX', 'days' => 90]),
        ];

        foreach (['must_be_adult', 'not_open', 'volunteer_exists', 'volunteer_done', 'cancelled', 'stop_done', 'resume_done', 'expired', 'error', 'unsupported_number'] as $key) {
            $messages[] = $session->t($key);
        }

        foreach ($messages as $message) {
            $this->assertLessThanOrEqual(self::MAX, mb_strlen($message), "[{$lang}] ".mb_strlen($message).': '.$message);
        }
    }
}
