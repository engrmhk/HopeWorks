<?php

namespace App\Models\Concerns;

use App\Models\Church;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToChurch
{
    public static function bootBelongsToChurch(): void
    {
        static::addGlobalScope('church', function (Builder $builder): void {
            $user = Auth::user();

            if ($user && ! $user->isSynodAdmin()) {
                $builder->where($builder->getModel()->getTable().'.church_id', $user->church_id);
            }
        });

        static::creating(function ($model): void {
            if (empty($model->church_id) && Auth::check() && ! Auth::user()->isSynodAdmin()) {
                $model->church_id = Auth::user()->church_id;
            }
        });
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
