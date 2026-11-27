<?php

namespace App\Models;

use App\Enums\TypeDocument;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PreventsForceDelete;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentPatient extends Model
{
    use Auditable, PreventsForceDelete, SoftDeletes;

    protected $table = 'documents_patients';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type_document' => TypeDocument::class,
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class, 'rdv_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
