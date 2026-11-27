<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exceptions_planning', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('medecin_id')->nullable()->constrained('medecins')->restrictOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('motif');
            $table->enum('action_appliquee', ['reaffecter_confrere', 'reporter_prochaine_vacation', 'a_replanifier'])->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_id', 'date_debut', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exceptions_planning');
    }
};
