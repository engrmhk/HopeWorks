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
        'pending_plan_id',
        'pending_plan_effective_at',
        'status',
        'grace_period_days',
        'enforcement_policy',
        'current_period_end',
        'grace_started_at',
        'dunning_started_at',
        'dunning_attempt_count',
        'dunning_exhausted_at',
        'payment_gateway_customer_id',
        'payment_gateway_subscription_id',
        'status_evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'enforcement_policy' => EnforcementPolicy::class,
            'current_period_end' => 'datetime',
            'grace_started_at' => 'datetime',
            'status_evaluated_at' => 'datetime',
            'dunning_started_at' => 'datetime',
            'dunning_exhausted_at' => 'datetime',
            'pending_plan_effective_at' => 'datetime',
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

    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
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
