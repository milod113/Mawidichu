<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->string('numero_ticket', 20);
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('medecin_id')->nullable()->constrained('medecins')->restrictOnDelete();
            $table->foreignId('box_id')->nullable()->constrained('boxes')->restrictOnDelete();
            $table->foreignId('type_consultation_id')->constrained('types_consultation')->restrictOnDelete();
            $table->date('date_rdv');
            $table->time('heure_vague_prevue')->nullable();
            $table->dateTime('heure_arrivee_checkin')->nullable();
            $table->dateTime('heure_appel')->nullable();
            $table->enum('statut', [
                'planifie', 'arrive', 'en_preconsultation', 'pret_medecin', 'appele',
                'en_consultation', 'termine', 'absent', 'reporte', 'en_attente_retour', 'a_replanifier',
            ])->default('planifie');
            $table->enum('priorite', [
                'normal', 'personnel_chu', 'orientation_inter_services', 'hors_wilaya', 'personne_agee_enceinte',
            ])->default('normal');
            $table->text('motif_priorite')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['service_id', 'date_rdv', 'numero_ticket']);
            $table->index(['service_id', 'date_rdv']);
            $table->index(['medecin_id', 'date_rdv']);
            $table->index(['patient_id', 'date_rdv']);
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendez_vous');
    }
};
