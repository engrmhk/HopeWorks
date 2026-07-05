<?php

namespace App\Models;

use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'church_id',
        'synod_id',
        'plan_id',
        'status',
        'grace_period_days',
        'enforcement_policy',
        'current_period_end',
        'grace_started_at',
        'payment_gateway_customer_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'enforcement_policy' => EnforcementPolicy::class,
            'current_period_end' => 'datetime',
            'grace_started_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function synod(): BelongsTo
    {
        return $this->belongsTo(Synod::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function paymentEvents(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }
}
