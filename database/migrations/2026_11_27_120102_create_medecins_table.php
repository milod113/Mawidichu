<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medecins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->enum('grade', ['Pr', 'MCA', 'MCB', 'MA', 'Specialiste', 'Resident']);
            $table->string('specialite')->nullable();
            // Valeurs par défaut du médecin, surchargeables par vacation ; null = hérite du service / du type de consultation.
            $table->unsignedSmallInteger('duree_consult_defaut')->nullable();
            $table->unsignedSmallInteger('quota_max_defaut')->nullable();
            $table->boolean('tolere_surbooking')->default(false);
            $table->unsignedSmallInteger('valeur_surbooking')->nullable();
            // La valeur de surbooking est un nombre de places ou un pourcentage.
            $table->enum('surbooking_unite', ['nombre', 'pourcentage'])->default('nombre');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medecins');
    }
};
