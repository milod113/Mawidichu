<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('rdv_id')->nullable()->constrained('rendez_vous')->restrictOnDelete();
            $table->enum('type_document', ['orientation', 'bilan', 'imagerie', 'autre']);
            $table->string('titre');
            $table->string('fichier_path');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_patients');
    }
};
