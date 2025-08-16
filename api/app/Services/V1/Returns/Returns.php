<?php

namespace App\Services\V1\Returns;

use App\Constants\OrderStatuses;
use App\Constants\ReturnStatuses;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnStatus;
use App\Resources\V1\OrderReturnResource;
use App\Services\V1\Emails\Email;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Returns
{
    use ApiResponses;

    private Email $emailService;

    public function __construct(Email $emailService)
    {
        $this->emailService = $emailService;
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

            return OrderReturnResource::collection($returns)->additional([
                'message' => 'Order returns retrieved.',
                'status' => 200
            ]);
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

        if($order->status->name === OrderStatuses::PENDING_PAYMENT){
            return $this->error('Order has not been paid for.');
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

            if (in_array($nextStatus, [ReturnStatuses::APPROVED, ReturnStatuses::REJECTED])) {
                $this->sendReturnStatusEmail($return, $nextStatus);
            }

            return $this->ok("Return status updated to {$nextStatus}.", $return);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    private function sendReturnStatusEmail(OrderReturn $orderReturn, string $status): void
    {
        try {
            $returnData = $this->emailService->formatReturnData($orderReturn);
            $customerEmail = $orderReturn->orderItem->order->user->email;

            $this->emailService->sendReturnStatus($returnData, $customerEmail);
        } catch (\Exception $e) {
            Log::error('Failed to send return status email', [
                'return_id' => $orderReturn->id,
                'status' => $status,
                'customer_email' => $orderReturn->orderItem->order->user->email ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }
}
