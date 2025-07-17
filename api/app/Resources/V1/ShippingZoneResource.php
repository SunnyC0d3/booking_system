<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'countries' => $this->countries,
            'country_names' => $this->getCountryNames(),
            'postcodes' => $this->postcodes,
            'excluded_postcodes' => $this->excluded_postcodes,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'methods_count' => $this->whenCounted('methods'),
            'rates_count' => $this->whenCounted('rates'),
            'available_methods' => $this->when(
                $this->relationLoaded('methods'),
                function () {
                    return $this->methods->map(function ($method) {
                        return [
                            'id' => $method->id,
                            'name' => $method->name,
                            'carrier' => $method->carrier,
                            'is_active' => $method->pivot->is_active,
                            'sort_order' => $method->pivot->sort_order,
                        ];
                    });
                }
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function getCountryNames(): array
    {
        if (!$this->countries) {
            return [];
        }

        $countryMap = [
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'IE' => 'Ireland',
            'AT' => 'Austria',
            'CH' => 'Switzerland',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'PT' => 'Portugal',
            'GR' => 'Greece',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'HU' => 'Hungary',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'HR' => 'Croatia',
            'BG' => 'Bulgaria',
            'RO' => 'Romania',
            'LT' => 'Lithuania',
            'LV' => 'Latvia',
            'EE' => 'Estonia',
            'MT' => 'Malta',
            'CY' => 'Cyprus',
            'LU' => 'Luxembourg',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'PE' => 'Peru',
            'ZA' => 'South Africa',
            'EG' => 'Egypt',
            'MA' => 'Morocco',
            'NG' => 'Nigeria',
            'KE' => 'Kenya',
            'GH' => 'Ghana',
            'TZ' => 'Tanzania',
            'UG' => 'Uganda',
            'ZW' => 'Zimbabwe',
            'NZ' => 'New Zealand',
            'MY' => 'Malaysia',
            'SG' => 'Singapore',
            'TH' => 'Thailand',
            'VN' => 'Vietnam',
            'PH' => 'Philippines',
            'ID' => 'Indonesia',
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'IL' => 'Israel',
            'TR' => 'Turkey',
            'RU' => 'Russia',
            'UA' => 'Ukraine',
        ];

        return array_map(function ($code) use ($countryMap) {
            return $countryMap[$code] ?? $code;
        }, $this->countries);
    }
}
