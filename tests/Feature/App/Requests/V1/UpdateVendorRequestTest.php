<?php

namespace Tests\Feature\App\Requests\V1;

use App\Models\User;
use App\Models\Vendor;
use App\Requests\V1\UpdateVendorRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateVendorRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('dummy-route/{vendor}', fn(UpdateVendorRequest $request) => 'OK')
            ->middleware('web');
    }

    public function test_validation_passes_when_name_is_changed()
    {
        $vendor1 = Vendor::factory()->create(['name' => 'Vendor Alpha']);
        $vendor2 = Vendor::factory()->create(['name' => 'Vendor Beta']);

        $request = new UpdateVendorRequest();
        $this->app->instance(UpdateVendorRequest::class, $request);
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $this->app['request']->setRouteResolver(function () use ($vendor2) {
            return (object) ['parameters' => ['vendor' => $vendor2->id]];
        });

        $data = ['name' => 'Vendor Alpha'];
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails());
    }

    public function test_validation_passes_with_valid_update_data()
    {
        $user = User::factory()->create();
        $vendor = Vendor::factory()->create();

        $data = [
            'name' => 'Updated Vendor',
            'description' => 'New description for vendor.',
            'user_id' => $user->id,
            'address' => [
                'address_line1' => 'Updated Street',
                'city' => 'Bristol',
                'country' => 'UK',
                'postal_code' => 'BS1234',
            ],
        ];

        $request = new UpdateVendorRequest();
        $this->app->instance(UpdateVendorRequest::class, $request);
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $this->app['request']->setRouteResolver(function () use ($vendor) {
            return (object) ['parameters' => ['vendor' => $vendor->id]];
        });

        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails());
    }

    public function test_validation_allows_partial_update()
    {
        $vendor = Vendor::factory()->create();

        $data = ['description' => 'Just updating the description.'];

        $rules = (new UpdateVendorRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails());
    }
}
