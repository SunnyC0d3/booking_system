<?php

namespace Tests\Feature\App\Filters\V1;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Filters\V1\UserFilter;
use App\Models\Role;

class UserFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_filters_by_name()
    {
        $user = User::factory()->create(['name' => 'John Doe']);
        User::factory()->create(['name' => 'Jane Smith']);

        $request = Request::create('', 'GET', ['name' => 'John*']);
        $filter = new UserFilter($request);

        $result = $filter->apply(User::query())->get();

        $this->assertTrue($result->contains($user));
        $this->assertCount(1, $result);
    }

    public function test_filters_by_email()
    {
        $user = User::factory()->create(['email' => 'johndoe@example.com']);
        User::factory()->create(['email' => 'janedoe@example.com']);

        $request = Request::create('', 'GET', ['email' => 'john*']);
        $filter = new UserFilter($request);

        $result = $filter->apply(User::query())->get();

        $this->assertTrue($result->contains($user));
        $this->assertCount(1, $result);
    }

    public function test_filters_by_created_at_single_date()
    {
        $user = User::factory()->create(['created_at' => now()]);
        User::factory()->create(['created_at' => now()->subDays(3)]);

        $request = Request::create('', 'GET', ['createdAt' => now()->toDateString()]);
        $filter = new UserFilter($request);

        $result = $filter->apply(User::query())->get();

        $this->assertTrue($result->contains($user));
        $this->assertCount(1, $result);
    }

    public function test_filters_by_updated_at_range()
    {
        $user = User::factory()->create(['updated_at' => now()->subDays(2)]);
        User::factory()->create(['updated_at' => now()->subDays(10)]);

        $range = now()->subDays(5)->toDateString() . ',' . now()->toDateString();

        $request = Request::create('', 'GET', ['updatedAt' => $range]);
        $filter = new UserFilter($request);

        $result = $filter->apply(User::query())->get();

        $this->assertTrue($result->contains($user));
        $this->assertCount(1, $result);
    }

    public function test_filters_by_role()
    {
        $role = Role::factory()->create();
        $user = User::factory()->create(['role_id' => $role->id]);
        User::factory()->create(['role_id' => null]);

        $request = Request::create('', 'GET', ['role' => (string)$role->id]);
        $filter = new UserFilter($request);

        $result = $filter->apply(User::query())->get();

        $this->assertTrue($result->contains($user));
        $this->assertCount(1, $result);
    }

    public function test_includes_relations()
    {
        $user = User::factory()->hasAddress()->create();

        $request = Request::create('', 'GET', ['include' => 'userAddress']);
        $filter = new UserFilter($request);

        $result = $filter->apply(User::query())->get();

        $this->assertTrue($result->pluck('userAddress')->every(fn($addr) => !is_null($addr)));
    }

    public function test_searches_by_name_or_email()
    {
        $user = User::factory()->create(['name' => 'Alpha Test', 'email' => 'alpha@example.com']);
        User::factory()->create(['name' => 'Beta User']);

        $request = Request::create('', 'GET', ['search' => 'alpha*']);
        $filter = new UserFilter($request);

        $result = $filter->apply(User::query())->get();

        $this->assertTrue($result->contains($user));
        $this->assertCount(1, $result);
    }

    public function test_sorts_by_columns()
    {
        $users = User::factory()->count(5)->create()->sortBy('email')->values();

        $request = Request::create('', 'GET', ['sort' => 'email']);
        $filter = new UserFilter($request);

        $result = $filter->apply(User::query())->get()->sortBy('email')->values();

        $this->assertEquals(
            $users->pluck('id')->toArray(),
            $result->pluck('id')->toArray()
        );
    }
}
