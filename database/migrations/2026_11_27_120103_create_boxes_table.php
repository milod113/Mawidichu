<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('numero_box');
            $table->string('nom_affichage')->nullable();
            // Médecin connecté au box aujourd'hui (affectation par box / équipe).
            $table->foreignId('medecin_actuel_id')->nullable()->constrained('medecins')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['service_id', 'numero_box']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boxes');
    }
};
