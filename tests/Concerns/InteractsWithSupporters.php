<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\SupporterSource;
use App\Models\Supporter;
use App\Models\User;
use App\Services\Supporters\SupporterRegistrar;
use Database\Seeders\PlaceholderPrivacyNoticeSeeder;

trait InteractsWithSupporters
{
    use InteractsWithAccounts;

    protected function seedSupporterContext(): void
    {
        $this->seedAccessControl();
        $this->seed(PlaceholderPrivacyNoticeSeeder::class);
    }

    /**
     * @param  array<string, bool>  $consents
     */
    protected function registerSupporter(string $phone, string $lgaSlug = 'udu', array $consents = ['sms' => true], ?User $by = null, string $name = 'Sample Person', SupporterSource $source = SupporterSource::Staff): Supporter
    {
        return app(SupporterRegistrar::class)->register(
            ['full_name' => $name, 'phone' => $phone, 'ward_id' => $this->ward($lgaSlug)->id],
            $source,
            $consents,
            true,
            $by,
        )->supporter;
    }
}
