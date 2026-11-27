<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes opérationnelles des Étapes 4 à 6 :
 * - users.affectation_service_id : service de rattachement d'une secrétaire / d'un infirmier
 *   (null = guichet central / Bureau des Entrées) ;
 * - services.aile : aile ou étage, pour filtrer l'écran TV ;
 * - consultations.controle_rdv_id : RDV de contrôle programmé à l'issue de la consultation ;
 * - medecins.medicaments_favoris_json : médicaments favoris du médecin (ordonnance rapide).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('affectation_service_id')->nullable()->after('locale')->constrained('services')->nullOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('aile', 100)->nullable()->after('code')->index();
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->foreignId('controle_rdv_id')->nullable()->after('delai_controle_jours')->constrained('rendez_vous')->restrictOnDelete();
        });

        Schema::table('medecins', function (Blueprint $table) {
            $table->json('medicaments_favoris_json')->nullable()->after('surbooking_unite');
        });
    }

    public function down(): void
    {
        Schema::table('medecins', fn (Blueprint $t) => $t->dropColumn('medicaments_favoris_json'));
        Schema::table('consultations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('controle_rdv_id');
        });
        Schema::table('services', function (Blueprint $t) {
            $t->dropIndex(['aile']);
            $t->dropColumn('aile');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('affectation_service_id');
        });
    }
};
