<?php

namespace Tests\Feature\App\Requests\V1;

use App\Models\User;
use App\Models\Vendor;
use App\Requests\V1\StoreVendorRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreVendorRequestTest extends TestCase
{
    public function test_validation_fails_when_required_fields_are_missing()
    {
        $data = [];
        $rules = (new StoreVendorRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertArrayHasKey('user_id', $validator->errors()->toArray());
    }

    public function test_validation_passes_when_name_is_changed()
    {
        $existingVendor = Vendor::factory()->create(['name' => 'Vendor X']);
        $user = User::factory()->create();

        $data = [
            'name' => 'Vendor X',
            'user_id' => $user->id,
            'description' => 'Some vendor description'
        ];

        $rules = (new StoreVendorRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails());
    }

    public function test_validation_passes_with_valid_data()
    {
        $user = User::factory()->create();

        $data = [
            'name' => 'Fresh Vendor',
            'user_id' => $user->id,
            'description' => 'A brand new vendor on the platform.',
        ];

        $rules = (new StoreVendorRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails());
    }
}
