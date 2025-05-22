<?php

namespace Tests\Feature\App\Filters\V1;

use Tests\TestCase;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Filters\V1\VendorFilter;

class VendorFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_filters_by_name()
    {
        $vendor = Vendor::factory()->create(['name' => 'Acme Ltd']);
        Vendor::factory()->create(['name' => 'Beta Co']);

        $request = Request::create('', 'GET', ['name' => 'Acme*']);
        $filter = new VendorFilter($request);

        $result = $filter->apply(Vendor::query())->get();

        $this->assertTrue($result->contains($vendor));
        $this->assertCount(1, $result);
    }

    public function test_filters_by_created_at_single_date()
    {
        $vendor = Vendor::factory()->create(['created_at' => now()]);
        Vendor::factory()->create(['created_at' => now()->subDays(5)]);

        $request = Request::create('', 'GET', ['createdAt' => now()->toDateString()]);
        $filter = new VendorFilter($request);

        $result = $filter->apply(Vendor::query())->get();

        $this->assertTrue($result->contains($vendor));
        $this->assertCount(1, $result);
    }

    public function test_filters_by_updated_at_range()
    {
        $vendor = Vendor::factory()->create(['updated_at' => now()->subDays(2)]);
        Vendor::factory()->create(['updated_at' => now()->subDays(10)]);

        $range = now()->subDays(3)->toDateString() . ',' . now()->toDateString();

        $request = Request::create('', 'GET', ['updatedAt' => $range]);
        $filter = new VendorFilter($request);

        $result = $filter->apply(Vendor::query())->get();

        $this->assertTrue($result->contains($vendor));
        $this->assertCount(1, $result);
    }

    public function test_filters_by_user_id()
    {
        $user = User::factory()->create();
        $vendor = Vendor::factory()->create(['user_id' => $user->id]);
        Vendor::factory()->create(); // unrelated vendor

        $request = Request::create('', 'GET', ['user' => (string)$user->id]);
        $filter = new VendorFilter($request);

        $result = $filter->apply(Vendor::query())->get();

        $this->assertTrue($result->contains($vendor));
        $this->assertCount(1, $result);
    }

    public function test_includes_user_relation()
    {
        $vendor = Vendor::factory()->for(User::factory())->create();

        $request = Request::create('', 'GET', ['include' => 'user']);
        $filter = new VendorFilter($request);

        $result = $filter->apply(Vendor::query())->get();

        $this->assertTrue($result->pluck('user')->every(fn($u) => !is_null($u)));
    }

    public function test_searches_by_name()
    {
        $vendor = Vendor::factory()->create(['name' => 'Global Express']);
        Vendor::factory()->create(['name' => 'Alpha Logistics']);

        $request = Request::create('', 'GET', ['search' => 'Global*']);
        $filter = new VendorFilter($request);

        $result = $filter->apply(Vendor::query())->get();

        $this->assertTrue($result->contains($vendor));
        $this->assertCount(1, $result);
    }

    public function test_sorts_by_name()
    {
        $vendors = Vendor::factory()->count(3)->create()->sortBy('name')->values();

        $request = Request::create('', 'GET', ['sort' => 'name']);
        $filter = new VendorFilter($request);

        $result = $filter->apply(Vendor::query())->get()->sortBy('name')->values();

        $this->assertEquals(
            $vendors->pluck('id')->toArray(),
            $result->pluck('id')->toArray()
        );
    }
}
