<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plannings_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            // Affectation nominative (medecin_id) ou par box / équipe (box_id).
            $table->foreignId('medecin_id')->nullable()->constrained('medecins')->restrictOnDelete();
            $table->foreignId('box_id')->nullable()->constrained('boxes')->restrictOnDelete();
            $table->unsignedTinyInteger('jour_semaine'); // 0 = dimanche … 6 = samedi
            $table->time('heure_debut');
            $table->time('heure_fin');
            // null = hérite du mode du service.
            $table->enum('mode_planification', ['quota_vague', 'creneaux_dynamiques', 'mixte'])->nullable();
            $table->unsignedSmallInteger('quota_rdv')->nullable();
            $table->unsignedSmallInteger('quota_sans_rdv')->nullable();
            $table->unsignedSmallInteger('taille_vague')->nullable();
            $table->unsignedSmallInteger('intervalle_vague_min')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_id', 'jour_semaine']);
            $table->index(['medecin_id', 'jour_semaine']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plannings_types');
    }
};
