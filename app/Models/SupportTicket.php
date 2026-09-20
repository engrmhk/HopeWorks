<?php

namespace App\Models;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportTicket extends Model
{
    protected $fillable = [
        'church_id',
        'subject',
        'status',
        'priority',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupportTicketStatus::class,
            'priority' => SupportTicketPriority::class,
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportTicketMessage::class)->latestOfMany();
    }

    /**
     * Open or in-progress tickets whose latest message is from the church.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAwaitingStaffReply(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [SupportTicketStatus::Open, SupportTicketStatus::InProgress])
            ->whereIn('id', function ($sub): void {
                $sub->select('support_ticket_id')
                    ->from('support_ticket_messages as latest')
                    ->where('is_staff', false)
                    ->whereRaw('latest.id = (select max(id) from support_ticket_messages where support_ticket_id = latest.support_ticket_id)');
            });
    }
}
