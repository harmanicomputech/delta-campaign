<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\DesignSystem\SampleForm;
use Livewire\Livewire;
use Tests\TestCase;

final class SampleFormTest extends TestCase
{
    public function test_requires_fields_on_submit(): void
    {
        Livewire::test(SampleForm::class)
            ->call('submit')
            ->assertHasErrors(['name' => 'required', 'phone' => 'required', 'area' => 'required', 'acknowledged' => 'accepted'])
            ->assertNotDispatched('toast');
    }

    public function test_validates_a_field_when_it_changes(): void
    {
        Livewire::test(SampleForm::class)
            ->set('phone', '0123')
            ->assertHasErrors('phone')
            ->assertSee(__('validation.nigerian_mobile'))
            ->set('phone', '0803 123 4567')
            ->assertHasNoErrors('phone');
    }

    public function test_rejects_areas_outside_the_allowed_list(): void
    {
        Livewire::test(SampleForm::class)->set('area', 'area-99')->assertHasErrors(['area' => 'in']);
    }

    public function test_valid_submission_reports_and_resets_without_persisting(): void
    {
        Livewire::test(SampleForm::class)
            ->set('name', 'Sample Tester')
            ->set('phone', '+234 803 123 4567')
            ->set('area', 'area-1')
            ->set('acknowledged', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertDispatched('toast', fn (string $event, array $params): bool => str_contains($params['message'], '4567') && ! str_contains($params['message'], '8031234567'))
            ->assertSet('name', '');
    }
}
