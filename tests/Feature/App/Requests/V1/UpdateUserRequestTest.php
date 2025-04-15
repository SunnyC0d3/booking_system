<?php

namespace Tests\Feature\App\Requests\V1;

use App\Models\Role;
use App\Models\User;
use App\Requests\V1\UpdateUserRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UpdateUserRequestTest extends TestCase
{
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

        $request = UpdateUserRequest::create("/dummy-route/{$user2->id}", 'GET', [
            'email' => 'user1@example.com',
        ]);
        $request->setRouteResolver(fn() => (object)['parameter' => fn() => $user2]);

        $rules = (new UpdateUserRequest())->setContainer(app())->setRedirector(app('redirect'))->setRouteResolver(fn() => $user2)->rules();
        $validator = Validator::make($request->all(), $rules);

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

        $request = UpdateUserRequest::create("/dummy-route/{$user->id}", 'GET', $data);
        $request->setRouteResolver(fn() => $user);

        $rules = (new UpdateUserRequest())->setContainer(app())->setRedirector(app('redirect'))->setRouteResolver(fn() => $user)->rules();
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
