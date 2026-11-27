<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regroupe les jours d'une même fête (ex. les 3 jours de l'Aïd) pour pouvoir
 * décaler ou confirmer toute la plage en un clic (cahier des charges §4.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jours_feries', function (Blueprint $table) {
            $table->string('groupe', 100)->nullable()->after('statut_confirmation')->index();
        });
    }

    public function down(): void
    {
        Schema::table('jours_feries', function (Blueprint $table) {
            $table->dropIndex(['groupe']);
            $table->dropColumn('groupe');
        });
    }
};
