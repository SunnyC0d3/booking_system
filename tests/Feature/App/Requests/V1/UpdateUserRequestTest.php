<?php

namespace Tests\Feature\App\Requests\V1;

use App\Models\Role;
use App\Models\User;
use App\Requests\V1\UpdateUserRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UpdateUserRequestTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('dummy-route/{user}', fn(UpdateUserRequest $request) => 'OK')
            ->middleware('web');
    }

    public function test_validation_fails_when_email_is_not_unique()
    {
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        $request = new UpdateUserRequest();
        $this->app->instance(UpdateUserRequest::class, $request);
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $this->app['request']->setRouteResolver(function () use ($user2) {
            return (object) ['parameters' => ['user' => $user2->id]];
        });

        $data = ['email' => 'user1@example.com'];
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_update_data()
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();

        $data = [
            'name' => 'Updated Name',
            'email' => 'new.email@example.com',
            'password' => 'newpassword123',
            'role_id' => $role->id,
            'address' => [
                'address_line1' => 'New Street',
                'city' => 'Leeds',
                'country' => 'UK',
                'postal_code' => 'XYZ111',
            ]
        ];

        $request = new UpdateUserRequest();
        $this->app->instance(UpdateUserRequest::class, $request);
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $this->app['request']->setRouteResolver(function () use ($user) {
            return (object) ['parameters' => ['user' => $user->id]];
        });

        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails());
    }

    public function test_validation_allows_partial_update()
    {
        $user = User::factory()->create();

        $data = ['name' => 'New Name Only'];

        $rules = (new UpdateUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails());
    }
}
