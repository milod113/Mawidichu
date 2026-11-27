<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jours fériés (cahier des charges §4.3 et §7).
 *
 * Seule table métier créée à l'Étape 1, car le seeder des jours fériés
 * d'Algérie fait partie de cette étape. Le modèle Eloquent, l'audit et
 * l'interface d'administration (décalage en 1 clic) viendront aux
 * Étapes 2 et 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jours_feries', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->string('libelle_ar')->nullable();
            $table->date('date_ferie');
            $table->enum('type', ['fixe', 'mobile']);
            // Fête fixe qui revient chaque année au même jour/mois.
            $table->boolean('est_recurrent')->default(false);
            // Les fêtes lunaires restent "estimatif" jusqu'à l'annonce officielle.
            $table->enum('statut_confirmation', ['confirme', 'estimatif'])->default('estimatif');
            $table->timestamps();
            $table->softDeletes();

            $table->index('date_ferie');
            $table->unique(['libelle', 'date_ferie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jours_feries');
    }
};
