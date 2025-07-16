<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'countries',
        'postcodes',
        'excluded_postcodes',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'countries' => 'array',
        'postcodes' => 'array',
        'excluded_postcodes' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function methods(): BelongsToMany
    {
        return $this->belongsToMany(ShippingMethod::class, 'shipping_zones_methods')
            ->withPivot('is_active', 'sort_order')
            ->withTimestamps();
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeForCountry($query, string $countryCode)
    {
        return $query->whereJsonContains('countries', $countryCode);
    }

    public function coversCountry(string $countryCode): bool
    {
        $countries = $this->countries ?? [];
        return in_array($countryCode, $countries) || in_array('*', $countries);
    }

    public function coversPostcode(string $postcode): bool
    {
        if (empty($this->postcodes)) {
            return true;
        }

        $postcode = strtoupper(str_replace(' ', '', $postcode));

        foreach ($this->postcodes as $pattern) {
            if ($this->matchesPostcodePattern($postcode, $pattern)) {
                if ($this->isPostcodeExcluded($postcode)) {
                    return false;
                }
                return true;
            }
        }

        return false;
    }

    public function coversAddress(string $countryCode, string $postcode): bool
    {
        if (!$this->coversCountry($countryCode)) {
            return false;
        }

        return $this->coversPostcode($postcode);
    }

    public function getAvailableMethods(): BelongsToMany
    {
        return $this->methods()
            ->wherePivot('is_active', true)
            ->where('shipping_methods.is_active', true)
            ->orderByPivot('sort_order')
            ->orderBy('shipping_methods.sort_order');
    }

    protected function matchesPostcodePattern(string $postcode, string $pattern): bool
    {
        $pattern = strtoupper(str_replace(' ', '', $pattern));

        if ($pattern === '*') {
            return true;
        }

        if (str_contains($pattern, '*')) {
            $pattern = str_replace('*', '.*', $pattern);
            return preg_match("/^{$pattern}$/", $postcode);
        }

        if (str_contains($pattern, '-')) {
            [$start, $end] = explode('-', $pattern, 2);
            return $postcode >= $start && $postcode <= $end;
        }

        return $postcode === $pattern;
    }

    protected function isPostcodeExcluded(string $postcode): bool
    {
        if (empty($this->excluded_postcodes)) {
            return false;
        }

        foreach ($this->excluded_postcodes as $pattern) {
            if ($this->matchesPostcodePattern($postcode, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
