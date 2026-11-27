<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('ipp', 30)->unique();
            $table->string('nom');
            $table->string('prenom');
            $table->string('nom_ar')->nullable();
            $table->string('prenom_ar')->nullable();
            $table->date('date_naissance')->nullable();
            $table->enum('sexe', ['M', 'F'])->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('nin', 30)->nullable();
            $table->string('numero_chifa', 30)->nullable();
            $table->string('wilaya')->nullable();
            $table->string('commune')->nullable();
            $table->string('adresse')->nullable();
            $table->unsignedSmallInteger('nb_absences')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['nom', 'prenom']);
            $table->index('telephone');
            $table->index('nin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
