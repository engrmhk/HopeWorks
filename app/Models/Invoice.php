<?php

namespace App\Models;

use App\Enums\InvoiceSource;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'subscription_id',
        'gateway_invoice_id',
        'amount',
        'currency',
        'status',
        'source',
        'payment_method',
        'reference_note',
        'recorded_by',
        'due_date',
        'paid_at',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'source' => InvoiceSource::class,
            'payment_method' => PaymentMethod::class,
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
