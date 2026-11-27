<?php

namespace App\Models;

use App\Enums\StatutConfirmation;
use App\Enums\TypeJourFerie;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class JourFerie extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'jours_feries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_ferie' => 'date',
            'type' => TypeJourFerie::class,
            'est_recurrent' => 'boolean',
            'statut_confirmation' => StatutConfirmation::class,
        ];
    }

    /**
     * Jours fériés applicables à une date (fête exacte ou fête fixe récurrente).
     */
    public function scopeAtDate(Builder $query, \DateTimeInterface|string $date): Builder
    {
        $date = Carbon::parse($date);

        return $query->where(function (Builder $q) use ($date) {
            $q->whereDate('date_ferie', $date->toDateString())
                ->orWhere(function (Builder $r) use ($date) {
                    $r->where('est_recurrent', true)
                        ->whereMonth('date_ferie', $date->month)
                        ->whereDay('date_ferie', $date->day);
                });
        });
    }
}
