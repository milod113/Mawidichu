<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Rapport statistiques au format .xlsx : une feuille par famille d'indicateurs.
 */
class StatsExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $stats
     */
    public function __construct(private array $stats) {}

    public function sheets(): array
    {
        $s = $this->stats;
        $v = $s['volume'];

        return [
            new StatsSheet('Synthèse', ['Indicateur', 'Valeur'], [
                ['Période', $s['filtres']['du'].' → '.$s['filtres']['au']],
                ['RDV prévus', $v['prevus']], ['Réalisés', $v['realises']], ['Absents', $v['absents']], ['Reportés / à replanifier', $v['reportes']],
                ['Taux d\'absence (%)', $v['taux_absence']],
                ['Premières consultations', $s['repartition']['premiere_consultation']], ['Contrôles', $s['repartition']['controle']], ['Retours de bilan', $s['repartition']['retour_bilan']],
                ['Temps d\'attente moyen (min)', $s['attente_moyenne']],
                ['Consultations seniors', $s['seniors_residents']['seniors']], ['Consultations résidents', $s['seniors_residents']['residents']],
            ]),
            new StatsSheet('Par jour', ['Date', 'Prévus', 'Réalisés', 'Absents'], collect($s['par_jour'])->map(fn ($j) => [$j['date'], $j['prevus'], $j['realises'], $j['absents']])->all()),
            new StatsSheet('Praticiens', ['Praticien', 'Grade', 'Service', 'Total', 'Réalisés', 'Attente moy. (min)'],
                collect($s['charge'])->map(fn ($c) => [$c['medecin'], $c['grade'], $c['service'], $c['total'], $c['realises'], $c['attente_moyenne']])->all()),
            new StatsSheet('Délais 1er RDV', ['Service', 'Prochaine date', 'Délai (jours)'], collect($s['delais'])->map(fn ($d) => [$d['service'], $d['date'], $d['jours']])->all()),
        ];
    }
}
