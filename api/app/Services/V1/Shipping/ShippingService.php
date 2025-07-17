<?php

namespace App\Services\V1\Shipping;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\ShippingAddress;
use App\Services\V1\Shipping\Providers\ShippoProvider;
use Illuminate\Support\Facades\Log;
use Exception;

class ShippingService
{
    protected ShippoProvider $shippoProvider;
    protected ShippingCalculator $calculator;

    public function __construct(ShippoProvider $shippoProvider, ShippingCalculator $calculator)
    {
        $this->shippoProvider = $shippoProvider;
        $this->calculator = $calculator;
    }

    public function createShipment(Order $order, array $options = []): Shipment
    {
        if (!$order->canShip()) {
            throw new Exception('Order cannot be shipped');
        }

        try {
            $shipment = $order->createShipment([
                'status' => 'processing',
                'notes' => $options['notes'] ?? null,
            ]);

            if ($options['auto_purchase_label'] ?? false) {
                $this->purchaseLabel($shipment);
            }

            Log::info('Shipment created successfully', [
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
            ]);

            return $shipment;

        } catch (Exception $e) {
            Log::error('Failed to create shipment', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to create shipment: ' . $e->getMessage());
        }
    }

    public function purchaseLabel(Shipment $shipment): array
    {
        try {
            $order = $shipment->order;
            $shippingAddress = $order->shippingAddress;
            $shippingMethod = $order->shippingMethod;

            if (!$shippingAddress || !$shippingMethod) {
                throw new Exception('Missing shipping address or method');
            }

            $labelData = $this->shippoProvider->createShipment([
                'address_from' => $this->getFromAddress($order),
                'address_to' => $shippingAddress->toShippoFormat(),
                'parcels' => $this->buildParcels($order),
                'shipment_method' => $shippingMethod->service_code ?? $shippingMethod->name,
                'carrier' => $shippingMethod->carrier,
                'metadata' => [
                    'order_id' => $order->id,
                    'shipment_id' => $shipment->id,
                ],
            ]);

            $shipment->update([
                'tracking_number' => $labelData['tracking_number'],
                'label_url' => $labelData['label_url'],
                'tracking_url' => $labelData['tracking_url'],
                'carrier_data' => $labelData,
                'status' => 'ready_to_ship',
            ]);

            Log::info('Shipping label purchased successfully', [
                'shipment_id' => $shipment->id,
                'tracking_number' => $labelData['tracking_number'],
            ]);

            return $labelData;

        } catch (Exception $e) {
            $shipment->update(['status' => 'failed']);

            Log::error('Failed to purchase shipping label', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to purchase label: ' . $e->getMessage());
        }
    }

    public function shipOrder(Order $order, array $options = []): Shipment
    {
        $shipment = $this->createShipment($order, $options);

        if ($options['purchase_label'] ?? true) {
            $this->purchaseLabel($shipment);
        }

        $this->markAsShipped($shipment, $options);

        return $shipment;
    }

    public function markAsShipped(Shipment $shipment, array $options = []): void
    {
        $trackingNumber = $options['tracking_number'] ?? $shipment->tracking_number;

        if (!$trackingNumber) {
            throw new Exception('Tracking number is required to mark as shipped');
        }

        $shipment->markAsShipped($trackingNumber, $options['label_url'] ?? null);

        if ($options['send_notification'] ?? true) {
            $this->sendShippingNotification($shipment);
        }

        Log::info('Order marked as shipped', [
            'order_id' => $shipment->order_id,
            'shipment_id' => $shipment->id,
            'tracking_number' => $trackingNumber,
        ]);
    }

    public function updateTrackingStatus(Shipment $shipment): array
    {
        try {
            if (!$shipment->tracking_number) {
                throw new Exception('No tracking number available');
            }

            $trackingData = $this->shippoProvider->getTrackingInfo($shipment->tracking_number);

            $statusMapping = [
                'UNKNOWN' => 'pending',
                'PRE_TRANSIT' => 'processing',
                'TRANSIT' => 'in_transit',
                'DELIVERED' => 'delivered',
                'RETURNED' => 'returned',
                'FAILURE' => 'failed',
            ];

            $newStatus = $statusMapping[$trackingData['status']] ?? $shipment->status;

            $updateData = [
                'status' => $newStatus,
                'carrier_data' => array_merge($shipment->carrier_data ?? [], [
                    'tracking_history' => $trackingData['tracking_history'] ?? [],
                    'last_updated' => now()->toISOString(),
                ]),
            ];

            if ($newStatus === 'delivered' && !$shipment->delivered_at) {
                $updateData['delivered_at'] = $trackingData['delivered_at'] ?? now();
            }

            $shipment->update($updateData);

            Log::info('Tracking status updated', [
                'shipment_id' => $shipment->id,
                'old_status' => $shipment->status,
                'new_status' => $newStatus,
            ]);

            return $trackingData;

        } catch (Exception $e) {
            Log::error('Failed to update tracking status', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to update tracking: ' . $e->getMessage());
        }
    }

    public function cancelShipment(Shipment $shipment, string $reason = ''): void
    {
        try {
            if ($shipment->isShipped()) {
                throw new Exception('Cannot cancel shipped order');
            }

            if ($shipment->hasLabel()) {
                $this->shippoProvider->cancelShipment($shipment->carrier_data['shipment_id'] ?? null);
            }

            $shipment->update([
                'status' => 'cancelled',
                'notes' => $shipment->notes . "\nCancelled: " . $reason,
            ]);

            $shipment->order->update(['fulfillment_status' => 'unfulfilled']);

            Log::info('Shipment cancelled', [
                'shipment_id' => $shipment->id,
                'reason' => $reason,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to cancel shipment', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to cancel shipment: ' . $e->getMessage());
        }
    }

    public function getRealTimeRates(ShippingAddress $fromAddress, ShippingAddress $toAddress, array $parcels): array
    {
        try {
            return $this->shippoProvider->getRates([
                'address_from' => $fromAddress->toShippoFormat(),
                'address_to' => $toAddress->toShippoFormat(),
                'parcels' => $parcels,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get real-time rates', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function validateAddress(ShippingAddress $address): array
    {
        try {
            return $this->shippoProvider->validateAddress($address->toShippoFormat());

        } catch (Exception $e) {
            Log::error('Failed to validate address', [
                'address_id' => $address->id,
                'error' => $e->getMessage(),
            ]);

            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    protected function getFromAddress(Order $order): array
    {
        $vendor = $order->orderItems->first()?->product?->vendor;

        return [
            'name' => $vendor?->name ?? config('app.name'),
            'company' => $vendor?->name ?? config('app.name'),
            'street1' => config('shipping.from_address.line1'),
            'street2' => config('shipping.from_address.line2', ''),
            'city' => config('shipping.from_address.city'),
            'state' => config('shipping.from_address.county', ''),
            'zip' => config('shipping.from_address.postcode'),
            'country' => config('shipping.from_address.country', 'GB'),
            'phone' => config('shipping.from_address.phone', ''),
            'email' => config('shipping.from_address.email', config('mail.from.address')),
        ];
    }

    protected function buildParcels(Order $order): array
    {
        $totalWeight = $order->getShippingWeight();
        $dimensions = $this->calculateOrderDimensions($order);

        return [[
            'length' => $dimensions['length'],
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'distance_unit' => 'cm',
            'weight' => $totalWeight,
            'mass_unit' => 'kg',
        ]];
    }

    protected function calculateOrderDimensions(Order $order): array
    {
        $maxLength = 0;
        $maxWidth = 0;
        $totalHeight = 0;

        foreach ($order->orderItems as $item) {
            $product = $item->product;
            $quantity = $item->quantity;

            $maxLength = max($maxLength, $product->length ?? 0);
            $maxWidth = max($maxWidth, $product->width ?? 0);
            $totalHeight += ($product->height ?? 0) * $quantity;
        }

        return [
            'length' => max($maxLength, 10),
            'width' => max($maxWidth, 10),
            'height' => max($totalHeight, 5),
        ];
    }

    protected function sendShippingNotification(Shipment $shipment): void
    {
        try {
            $order = $shipment->order;
            $user = $order->user;

            if ($user && $user->wantsShippingUpdates()) {
                Log::info('Shipping notification would be sent', [
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'tracking_number' => $shipment->tracking_number,
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to send shipping notification', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
