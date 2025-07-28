<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_file_id',
        'version',
        'title',
        'description',
        'changelog',
        'update_type',
        'priority',
        'is_security_update',
        'force_update',
        'notify_users',
        'released_at',
        'compatible_versions',
        'system_requirements'
    ];

    protected $casts = [
        'is_security_update' => 'boolean',
        'force_update' => 'boolean',
        'notify_users' => 'boolean',
        'released_at' => 'datetime',
        'compatible_versions' => 'array',
        'system_requirements' => 'array'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productFile(): BelongsTo
    {
        return $this->belongsTo(ProductFile::class);
    }

    public function scopeReleased($query)
    {
        return $query->where('released_at', '<=', now());
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('update_type', $type);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeSecurityUpdates($query)
    {
        return $query->where('is_security_update', true);
    }

    public function isReleased(): bool
    {
        return $this->released_at->isPast();
    }

    public function isCompatibleWith(string $version): bool
    {
        if (empty($this->compatible_versions)) {
            return true;
        }

        return in_array($version, $this->compatible_versions);
    }

    public function getUpdateTypeLabelAttribute(): string
    {
        return match($this->update_type) {
            'major' => 'Major Release',
            'minor' => 'Minor Update',
            'patch' => 'Patch',
            'hotfix' => 'Hotfix',
            default => 'Update'
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match($this->priority) {
            'low' => 'Low Priority',
            'medium' => 'Medium Priority',
            'high' => 'High Priority',
            'critical' => 'Critical',
            default => 'Unknown'
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray'
        };
    }
}
