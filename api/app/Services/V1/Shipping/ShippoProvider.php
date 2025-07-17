<?php

namespace App\Services\V1\Shipping;

use Shippo\Shippo;
use Shippo\Address;
use Shippo\Shipment;
use Shippo\Transaction;
use Shippo\Track;
use App\Constants\ShippingStatuses;
use Illuminate\Support\Facades\Log;
use Exception;

class ShippoProvider
{
    protected string $apiKey;
    protected string $environment;

    public function __construct()
    {
        $this->apiKey = config('services.shippo.api_key');
        $this->environment = config('services.shippo.environment', 'test');

        if (!$this->apiKey) {
            throw new Exception('Shippo API key not configured');
        }

        Shippo::setApiKey($this->apiKey);
    }

    public function validateAddress(array $addressData): array
    {
        try {
            $address = Address::create([
                'name' => $addressData['name'] ?? '',
                'company' => $addressData['company'] ?? '',
                'street1' => $addressData['street1'] ?? $addressData['line1'],
                'street2' => $addressData['street2'] ?? $addressData['line2'] ?? '',
                'city' => $addressData['city'],
                'state' => $addressData['state'] ?? $addressData['county'] ?? '',
                'zip' => $addressData['zip'] ?? $addressData['postcode'],
                'country' => $addressData['country'],
                'phone' => $addressData['phone'] ?? '',
                'email' => $addressData['email'] ?? '',
                'validate' => true,
            ]);

            if ($address['validation_results']['is_valid']) {
                return [
                    'valid' => true,
                    'normalized' => [
                        'name' => $address['name'],
                        'company' => $address['company'],
                        'line1' => $address['street1'],
                        'line2' => $address['street2'],
                        'city' => $address['city'],
                        'county' => $address['state'],
                        'postcode' => $address['zip'],
                        'country' => $address['country'],
                        'phone' => $address['phone'],
                    ],
                    'suggestions' => [],
                    'shippo_id' => $address['object_id'],
                ];
            }

            return [
                'valid' => false,
                'errors' => $address['validation_results']['messages'] ?? ['Address validation failed'],
                'suggestions' => [],
            ];

        } catch (Exception $e) {
            Log::error('Shippo address validation failed', [
                'address' => $addressData,
                'error' => $e->getMessage()
            ]);

            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getRates(array $shipmentData): array
    {
        try {
            $shipment = Shipment::create([
                'address_from' => $shipmentData['address_from'],
                'address_to' => $shipmentData['address_to'],
                'parcels' => $shipmentData['parcels'],
                'async' => false,
            ]);

            if ($shipment['status'] !== 'SUCCESS') {
                throw new Exception('Failed to get shipping rates: ' . json_encode($shipment['messages']));
            }

            $rates = [];
            foreach ($shipment['rates'] as $rate) {
                if ($rate['available']) {
                    $rates[] = [
                        'id' => $rate['object_id'],
                        'provider' => $rate['provider'],
                        'service' => $rate['servicelevel']['name'],
                        'service_token' => $rate['servicelevel']['token'],
                        'amount' => (int) ($rate['amount'] * 100), // Convert to pennies
                        'amount_formatted' => '£' . number_format($rate['amount'], 2),
                        'currency' => $rate['currency'],
                        'estimated_days' => $rate['estimated_days'],
                        'duration_terms' => $rate['duration_terms'],
                        'carrier_account' => $rate['carrier_account'],
                        'test' => $rate['test'],
                    ];
                }
            }

            return $rates;

        } catch (Exception $e) {
            Log::error('Shippo rate calculation failed', [
                'shipment_data' => $shipmentData,
                'error' => $e->getMessage()
            ]);

            throw new Exception('Failed to calculate shipping rates: ' . $e->getMessage());
        }
    }

    public function createShipment(array $shipmentData): array
    {
        try {
            $shipment = Shipment::create([
                'address_from' => $shipmentData['address_from'],
                'address_to' => $shipmentData['address_to'],
                'parcels' => $shipmentData['parcels'],
                'async' => false,
            ]);

            if ($shipment['status'] !== 'SUCCESS') {
                throw new Exception('Failed to create shipment: ' . json_encode($shipment['messages']));
            }

            $selectedRate = null;
            $carrier = $shipmentData['carrier'] ?? null;
            $serviceCode = $shipmentData['shipment_method'] ?? null;

            foreach ($shipment['rates'] as $rate) {
                if ($rate['available']) {
                    if ($carrier && $serviceCode) {
                        if (strtolower($rate['provider']) === strtolower($carrier) &&
                            strpos(strtolower($rate['servicelevel']['name']), strtolower($serviceCode)) !== false) {
                            $selectedRate = $rate;
                            break;
                        }
                    } else {
                        $selectedRate = $rate;
                        break;
                    }
                }
            }

            if (!$selectedRate) {
                throw new Exception('No suitable shipping rate found');
            }

            $transaction = Transaction::create([
                'rate' => $selectedRate['object_id'],
                'label_file_type' => 'PDF',
                'async' => false,
            ]);

            if ($transaction['status'] !== 'SUCCESS') {
                throw new Exception('Failed to purchase label: ' . json_encode($transaction['messages']));
            }

            return [
                'shipment_id' => $shipment['object_id'],
                'transaction_id' => $transaction['object_id'],
                'tracking_number' => $transaction['tracking_number'],
                'tracking_url' => $transaction['tracking_url_provider'],
                'label_url' => $transaction['label_url'],
                'rate' => [
                    'amount' => $selectedRate['amount'],
                    'currency' => $selectedRate['currency'],
                    'provider' => $selectedRate['provider'],
                    'service' => $selectedRate['servicelevel']['name'],
                ],
                'metadata' => $shipmentData['metadata'] ?? [],
            ];

        } catch (Exception $e) {
            Log::error('Shippo shipment creation failed', [
                'shipment_data' => $shipmentData,
                'error' => $e->getMessage()
            ]);

            throw new Exception('Failed to create shipment: ' . $e->getMessage());
        }
    }

    public function getTrackingInfo(string $trackingNumber, string $carrier = null): array
    {
        try {
            $trackingData = Track::get_status([
                'carrier' => $carrier ?: 'usps',
                'tracking_number' => $trackingNumber,
            ]);

            $statusMapping = [
                'UNKNOWN' => ShippingStatuses::UNKNOWN,
                'PRE_TRANSIT' => ShippingStatuses::PROCESSING,
                'TRANSIT' => ShippingStatuses::IN_TRANSIT,
                'DELIVERED' => ShippingStatuses::DELIVERED,
                'RETURNED' => ShippingStatuses::RETURNED,
                'FAILURE' => ShippingStatuses::FAILED,
                'EXCEPTION' => ShippingStatuses::EXCEPTION,
            ];

            $status = $statusMapping[$trackingData['tracking_status']] ?? ShippingStatuses::UNKNOWN;

            $trackingHistory = [];
            if (isset($trackingData['tracking_history'])) {
                foreach ($trackingData['tracking_history'] as $event) {
                    $trackingHistory[] = [
                        'status' => $event['status'],
                        'status_details' => $event['status_details'] ?? '',
                        'location' => $event['location'] ?? '',
                        'datetime' => $event['status_date'] ?? null,
                    ];
                }
            }

            return [
                'tracking_number' => $trackingNumber,
                'carrier' => $trackingData['carrier'] ?? $carrier,
                'status' => $status,
                'tracking_status' => $trackingData['tracking_status'],
                'eta' => $trackingData['eta'] ?? null,
                'delivered_at' => $status === ShippingStatuses::DELIVERED ? ($trackingData['status_date'] ?? null) : null,
                'tracking_history' => $trackingHistory,
                'tracking_url' => $trackingData['public_url'] ?? null,
            ];

        } catch (Exception $e) {
            Log::error('Shippo tracking failed', [
                'tracking_number' => $trackingNumber,
                'carrier' => $carrier,
                'error' => $e->getMessage()
            ]);

            throw new Exception('Failed to get tracking information: ' . $e->getMessage());
        }
    }

    public function cancelShipment(string $shipmentId): bool
    {
        try {
            $shipment = Shipment::retrieve($shipmentId);

            if ($shipment) {
                Log::info('Shippo shipment cancellation requested', [
                    'shipment_id' => $shipmentId,
                    'status' => $shipment['status'] ?? 'unknown'
                ]);

                return true;
            }

            return false;

        } catch (Exception $e) {
            Log::error('Shippo shipment cancellation failed', [
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function getCarriers(): array
    {
        try {
            return [
                'usps' => 'USPS',
                'ups' => 'UPS',
                'fedex' => 'FedEx',
                'dhl_express' => 'DHL Express',
                'royal_mail' => 'Royal Mail',
                'dpd' => 'DPD',
                'hermes' => 'Hermes',
                'parcelforce' => 'Parcelforce',
            ];

        } catch (Exception $e) {
            Log::error('Failed to get carriers', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function formatAddressForShippo(array $address): array
    {
        return [
            'name' => $address['name'] ?? '',
            'company' => $address['company'] ?? '',
            'street1' => $address['line1'] ?? $address['street1'],
            'street2' => $address['line2'] ?? $address['street2'] ?? '',
            'city' => $address['city'],
            'state' => $address['county'] ?? $address['state'] ?? '',
            'zip' => $address['postcode'] ?? $address['zip'],
            'country' => $address['country'],
            'phone' => $address['phone'] ?? '',
            'email' => $address['email'] ?? '',
        ];
    }

    public function isTestMode(): bool
    {
        return $this->environment === 'test';
    }
}
