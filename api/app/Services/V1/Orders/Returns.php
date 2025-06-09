<?php

namespace App\Services\V1\Orders;

use App\Constants\ReturnStatuses;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnStatus;
use App\Resources\V1\OrderReturnResource;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;

class Returns
{
    use ApiResponses;

    public function __construct()
    {
    }

    public function all(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('manage_returns')) {
            $returns = OrderReturn::with([
                'orderItem.product',
                'orderItem.order.user',
                'orderItem.order.payments',
                'status'
            ])->latest()->paginate(20);

            return $this->ok('Order returns retrieved.', OrderReturnResource::collection($returns));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function createReturn(Request $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $orderItem = OrderItem::with('order')->findOrFail($data['order_item_id']);
        $order = $orderItem->order;

        if ($order->user_id !== $user->id) {
            return $this->error('You are not authorized to return this item.', 403);
        }

        $existingReturn = OrderReturn::where('order_item_id', $data['order_item_id'])->first();

        if ($existingReturn) {
            return $this->error('A return request has already been created for this item.', 409);
        }

        $orderReturnStatusId = OrderReturnStatus::where('name', ReturnStatuses::REQUESTED)->value('id');

        $orderReturn = OrderReturn::create([
            'order_item_id' => $data['order_item_id'],
            'reason' => $data['reason'],
            'order_return_status_id' => $orderReturnStatusId,
        ]);

        $orderReturn->load('status');

        return $this->ok('Orders return created.', [
            'id' => $orderReturn->id,
            'reason' => $orderReturn->reason,
            'status' => $orderReturn->status->name,
            'created_at' => $orderReturn->created_at,
            'updated_at' => $orderReturn->updated_at,
        ]);
    }

    public function reviewReturn(Request $request, int $returnId, string $action)
    {
        $user = $request->user();

        if ($user->hasPermission('manage_returns')) {
            $return = OrderReturn::findOrFail($returnId);

            $currentStatus = $return->status->name;

            if (!in_array($currentStatus, [ReturnStatuses::REQUESTED, ReturnStatuses::UNDER_REVIEW])) {
                return $this->error('Return is already processed.', 422);
            }

            $nextStatus = match (strtolower($action)) {
                'review' => ReturnStatuses::UNDER_REVIEW,
                'approve' => ReturnStatuses::APPROVED,
                'reject' => ReturnStatuses::REJECTED,
                default => null,
            };

            if (!$nextStatus) {
                return $this->error('Invalid action provided.', 422);
            }

            $statusId = OrderReturnStatus::where('name', $nextStatus)->value('id');

            $return->update([
                'order_return_status_id' => $statusId,
            ]);

            return $this->ok("Return status updated to {$nextStatus}.", $return);
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}
