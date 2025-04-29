<?php

namespace Tests\Feature\App\Requests\V1;

use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Requests\V1\IndexOrderRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class IndexOrderRequestTest extends TestCase
{
    public function test_validation_passes_when_valid_fields_are_passed()
    {
        $user = User::factory()->create();
        $status = OrderStatus::factory()->create();

        $data = [
            'user_id' => $user->id,
            'status_id' => $status->id,
        ];
        $rules = (new IndexOrderRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails());
    }
}
