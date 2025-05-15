<?php

namespace App\Services\V1\Orders;

use App\Constants\ReturnStatuses;
use App\Models\OrderReturn;
use App\Models\OrderReturnStatus;
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
                'orderReturnStatus'
            ])->latest()->paginate(20);

            return $this->ok('Order returns retrieved.', $returns);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function createReturn(Request $request)
    {
        $data = $request->validated();

        $orderReturnStatusId = OrderReturnStatus::where('name', ReturnStatuses::REQUESTED)->value('id');

        $orderReturn = OrderReturn::create([
            'order_item_id' => $data['order_item_id'],
            'reason' => $data['reason'],
            'order_return_status_id' => $orderReturnStatusId,
        ]);

        return $this->ok('Orders returned created.', $orderReturn);
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
