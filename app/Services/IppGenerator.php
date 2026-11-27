<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Facades\DB;

/**
 * Génère l'IPP (identifiant permanent du patient) : {préfixe}-{année}-{séquence}.
 * Le format est paramétrable dans config/mawid.php.
 */
class IppGenerator
{
    public function next(): string
    {
        return DB::transaction(function () {
            $prefix = config('mawid.ipp_prefix').'-'.now()->year.'-';

            $dernier = Patient::withTrashed()
                ->where('ipp', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('ipp')
                ->value('ipp');

            $sequence = $dernier ? ((int) substr($dernier, strlen($prefix))) + 1 : 1;

            return $prefix.str_pad((string) $sequence, config('mawid.ipp_pad'), '0', STR_PAD_LEFT);
        });
    }
}
