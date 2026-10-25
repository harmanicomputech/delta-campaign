<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Services\Operations\LaunchReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class LaunchCheckTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    public function test_a_development_configuration_is_not_ready_and_says_why(): void
    {
        $this->seedAccessControl();
        $this->artisan('campaign:launch-check')->assertFailed();

        $checks = collect(app(LaunchReadiness::class)->run())->keyBy('name');
        $this->assertFalse($checks['APP_ENV is production']['ok']);
        $this->assertFalse($checks['SMS uses a real provider']['ok']);
        $this->assertFalse($checks['Approved privacy notice published']['ok']);
    }

    public function test_output_never_contains_secret_values(): void
    {
        $this->seedAccessControl();
        Artisan::call('campaign:launch-check', ['--json' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('"ready": false', $output);
        foreach (['services.africastalking.webhook_secret', 'campaign.keys.phone_hash', 'campaign.keys.field_encryption', 'app.key'] as $key) {
            $value = (string) config($key);
            if ($value !== '') {
                $this->assertStringNotContainsString($value, $output, "{$key} leaked");
            }
        }
    }

    public function test_sample_records_are_reported_as_a_blocker(): void
    {
        $this->seedAccessControl();
        $this->ward('udu'); // creates a SAMPLE ward

        $sample = collect(app(LaunchReadiness::class)->run())->firstWhere('name', 'No SAMPLE records');
        $this->assertFalse($sample['ok']);
        $this->assertTrue($sample['blocker']);
        $this->assertStringContainsString('wards: 1', $sample['detail']);
    }
}
