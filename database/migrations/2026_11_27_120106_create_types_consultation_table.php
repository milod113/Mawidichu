<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_consultation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->enum('code', ['premiere_consultation', 'controle', 'retour_bilan']);
            $table->string('libelle');
            $table->unsignedSmallInteger('duree_minutes');
            $table->string('couleur', 9)->nullable();
            $table->text('bilans_requis_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['service_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types_consultation');
    }
};
