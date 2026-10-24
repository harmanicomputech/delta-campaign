<?php

declare(strict_types=1);

namespace Tests\Feature\Geography;

use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\SenatorialDistrict;
use App\Models\Ward;
use Database\Seeders\GeographySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class GeographySchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GeographySeeder::class);
    }

    public function test_seeds_three_districts_and_twenty_five_lgas(): void
    {
        $this->assertSame(3, SenatorialDistrict::query()->count());
        $this->assertSame(25, Lga::query()->count());
        $this->assertSame(
            ['Delta Central' => 8, 'Delta North' => 9, 'Delta South' => 8],
            SenatorialDistrict::query()->withCount('lgas')->orderBy('name')->pluck('lgas_count', 'name')->all(),
        );
        $this->assertNull(Lga::query()->whereNotNull('inec_code')->first(), 'Official codes must not be invented.');
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->seed(GeographySeeder::class);

        $this->assertSame(25, Lga::query()->count());
    }

    public function test_denormalised_geography_is_set_by_the_database(): void
    {
        $lga = Lga::query()->where('slug', 'oshimili-south')->firstOrFail();
        $ward = Ward::factory()->create(['lga_id' => $lga->id]);
        $unit = PollingUnit::factory()->create(['ward_id' => $ward->id])->refresh();

        $this->assertSame($lga->senatorial_district_id, $ward->refresh()->senatorial_district_id);
        $this->assertSame($lga->id, $unit->lga_id);
        $this->assertSame($lga->senatorial_district_id, $unit->senatorial_district_id);
    }

    public function test_denormalised_columns_cannot_be_forged(): void
    {
        $ward = Ward::factory()->create(['lga_id' => Lga::query()->where('slug', 'sapele')->value('id')]);
        $unit = PollingUnit::factory()->create(['ward_id' => $ward->id]);

        DB::table('polling_units')->where('id', $unit->id)->update(['lga_id' => Lga::query()->where('slug', 'bomadi')->value('id')]);

        $this->assertSame($ward->lga_id, $unit->refresh()->lga_id);
    }

    public function test_moving_a_ward_or_lga_cascades_to_children(): void
    {
        $ward = Ward::factory()->create(['lga_id' => Lga::query()->where('slug', 'udu')->value('id')]);
        $unit = PollingUnit::factory()->create(['ward_id' => $ward->id]);
        $patani = Lga::query()->where('slug', 'patani')->firstOrFail();

        $ward->update(['lga_id' => $patani->id]);
        $this->assertSame($patani->id, $unit->refresh()->lga_id);
        $this->assertSame($patani->senatorial_district_id, $unit->senatorial_district_id);

        $north = SenatorialDistrict::query()->where('code', 'DN')->value('id');
        $patani->update(['senatorial_district_id' => $north]);
        $this->assertSame($north, $unit->refresh()->senatorial_district_id);
        $this->assertSame($north, $ward->refresh()->senatorial_district_id);
    }

    public function test_polling_unit_codes_are_unique(): void
    {
        $unit = PollingUnit::factory()->create();

        $this->expectException(QueryException::class);
        PollingUnit::factory()->create(['code' => $unit->code]);
    }

    public function test_ward_codes_are_unique_within_an_lga_only(): void
    {
        $lgas = Lga::query()->limit(2)->pluck('id');
        Ward::factory()->create(['lga_id' => $lgas[0], 'code' => 'W01']);
        Ward::factory()->create(['lga_id' => $lgas[1], 'code' => 'W01']);

        $this->expectException(QueryException::class);
        Ward::factory()->create(['lga_id' => $lgas[0], 'code' => 'W01']);
    }

    public function test_coordinates_must_be_given_as_a_pair(): void
    {
        $this->expectException(QueryException::class);
        PollingUnit::factory()->create(['latitude' => 5.5, 'longitude' => null]);
    }

    public function test_spatial_queries_find_nearby_units_in_distance_order(): void
    {
        $ward = Ward::factory()->create();
        $near = PollingUnit::factory()->create(['ward_id' => $ward->id, 'latitude' => 6.200, 'longitude' => 6.700]);
        $nearer = PollingUnit::factory()->create(['ward_id' => $ward->id, 'latitude' => 6.201, 'longitude' => 6.701]);
        PollingUnit::factory()->create(['ward_id' => $ward->id, 'latitude' => 5.100, 'longitude' => 5.300]);

        $found = PollingUnit::query()->near(6.2012, 6.7012, 1000)->pluck('id')->all();

        $this->assertSame([$nearer->id, $near->id], $found);
        $this->assertEqualsWithDelta(31, $nearer->distanceTo(6.2012, 6.7012), 5);
    }

    public function test_lgas_with_wards_cannot_be_deleted(): void
    {
        $ward = Ward::factory()->create();

        $this->expectException(QueryException::class);
        $ward->lga->delete();
    }
}
