<?php

namespace Tests\Feature;

use App\Enums\Priorite;
use App\Enums\StatutRdv;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\Consultation;
use App\Models\DemandeAvis;
use App\Models\JourFerie;
use App\Models\Medecin;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\JourFerieSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

/**
 * Modèle de données, relations, SoftDeletes et audit (Étape 2).
 */
class DataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_tables_of_the_target_schema_exist(): void
    {
        foreach ([
            'users', 'services', 'medecins', 'boxes', 'plannings_types', 'exceptions_planning',
            'jours_feries', 'types_consultation', 'patients', 'rendez_vous',
            'constantes_preconsultation', 'consultations', 'demandes_avis', 'documents_patients', 'audit_logs',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table manquante : $table");
        }
    }

    public function test_main_relations_resolve(): void
    {
        $rdv = RendezVous::factory()->create();
        $medecin = Medecin::factory()->create(['service_id' => $rdv->service_id]);
        $box = Box::create(['service_id' => $rdv->service_id, 'numero_box' => 1, 'medecin_actuel_id' => $medecin->id]);
        $rdv->update(['medecin_id' => $medecin->id, 'box_id' => $box->id]);

        $this->assertTrue($rdv->service->is($medecin->service));
        $this->assertTrue($rdv->medecin->user->is($medecin->user));
        $this->assertTrue($box->medecinActuel->is($medecin));
        $this->assertTrue($rdv->typeConsultation->service->is($rdv->service));
        $this->assertCount(1, $rdv->patient->rendezVous);
        $this->assertCount(1, $box->rendezVous);
        $this->assertTrue($medecin->user->medecin->is($medecin));
    }

    public function test_consultation_history_is_scoped_by_service(): void
    {
        $patient = Patient::factory()->create();
        $cardio = Service::factory()->create();
        $pediatrie = Service::factory()->create();

        foreach ([$cardio, $pediatrie] as $service) {
            $medecin = Medecin::factory()->create(['service_id' => $service->id]);
            $rdv = RendezVous::factory()->create(['patient_id' => $patient->id, 'service_id' => $service->id, 'medecin_id' => $medecin->id]);
            Consultation::create([
                'rdv_id' => $rdv->id, 'patient_id' => $patient->id, 'service_id' => $service->id,
                'medecin_id' => $medecin->id, 'ordonnance_json' => [['medicament' => 'X']],
            ]);
        }

        $this->assertSame(2, $patient->consultations()->count());
        $this->assertSame(1, $patient->consultations()->forService($cardio)->count());
        $this->assertSame([['medicament' => 'X']], Consultation::first()->ordonnance_json);
    }

    public function test_medical_data_cannot_be_hard_deleted(): void
    {
        $patient = Patient::factory()->create();

        $patient->delete();
        $this->assertSoftDeleted($patient);

        $this->expectException(LogicException::class);
        $patient->forceDelete();
    }

    public function test_foreign_keys_block_physical_deletion_of_referenced_rows(): void
    {
        $rdv = RendezVous::factory()->create();

        $this->expectException(QueryException::class);
        \DB::table('patients')->where('id', $rdv->patient_id)->delete();
    }

    public function test_enum_values_are_cast(): void
    {
        $rdv = RendezVous::factory()->create();

        $this->assertSame(StatutRdv::Planifie, $rdv->fresh()->statut);
        $this->assertSame(Priorite::Normal, $rdv->fresh()->priorite);
    }

    public function test_creation_update_delete_and_restore_are_audited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $patient = Patient::factory()->create(['telephone' => '0550000000']);
        $patient->update(['telephone' => '0660000000']);
        $patient->delete();
        $patient->restore();

        $logs = AuditLog::where('auditable_type', $patient->getMorphClass())
            ->where('auditable_id', $patient->id)->orderBy('id')->get();

        $this->assertSame(['created', 'updated', 'deleted', 'restored'], $logs->pluck('action')->all());
        $this->assertSame($user->id, $logs[1]->user_id);
        $this->assertSame(['telephone' => '0550000000'], $logs[1]->old_values);
        $this->assertSame(['telephone' => '0660000000'], $logs[1]->new_values);
        $this->assertSame($patient->id, $patient->auditLogs()->first()->auditable_id);
    }

    public function test_priority_change_is_audited(): void
    {
        $this->actingAs(User::factory()->create());
        $rdv = RendezVous::factory()->create();

        $rdv->update(['priorite' => Priorite::PersonnelChu, 'motif_priorite' => 'Agent du CHU']);

        $log = $rdv->auditLogs()->where('action', 'updated')->firstOrFail();
        $this->assertSame('normal', $log->old_values['priorite']);
        $this->assertSame('personnel_chu', $log->new_values['priorite']);
    }

    public function test_secrets_never_reach_the_audit_log(): void
    {
        User::factory()->create(['password' => 'secret-value']);

        $log = AuditLog::where('auditable_type', (new User)->getMorphClass())->firstOrFail();
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertArrayNotHasKey('remember_token', $log->new_values);
    }

    public function test_audit_log_is_immutable(): void
    {
        $log = AuditLog::record('test', Patient::factory()->create());

        try {
            $log->update(['action' => 'autre']);
            $this->fail('La modification aurait dû être refusée.');
        } catch (LogicException) {
            // attendu
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_holiday_scope_matches_recurrent_and_exact_dates(): void
    {
        $this->seed(JourFerieSeeder::class);

        $this->assertTrue(JourFerie::atDate('2031-07-05')->exists()); // fête fixe récurrente
        $this->assertTrue(JourFerie::atDate('2027-03-09')->exists()); // Aïd el-Fitr 2027 (estimatif)
        $this->assertFalse(JourFerie::atDate('2027-03-15')->exists());
    }

    public function test_demande_avis_access_window(): void
    {
        $patient = Patient::factory()->create();
        $a = Medecin::factory()->create();
        $b = Medecin::factory()->create();
        $rdv = RendezVous::factory()->create(['patient_id' => $patient->id, 'service_id' => $a->service_id, 'medecin_id' => $a->id]);
        $consultation = Consultation::create([
            'rdv_id' => $rdv->id, 'patient_id' => $patient->id, 'service_id' => $a->service_id, 'medecin_id' => $a->id,
        ]);

        $base = [
            'consultation_id' => $consultation->id, 'patient_id' => $patient->id,
            'service_demandeur_id' => $a->service_id, 'medecin_demandeur_id' => $a->id,
            'service_cible_id' => $b->service_id, 'type_avis' => 'inter_specialite', 'motif' => 'Avis',
        ];
        DemandeAvis::create($base + ['expire_le' => now()->addDays(7)]);
        DemandeAvis::create($base + ['expire_le' => now()->subDay()]);

        $this->assertSame(1, DemandeAvis::active()->count());
    }
}
