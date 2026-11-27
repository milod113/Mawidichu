<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">
<style>@page{margin:32px} body{font-family:DejaVu Sans,sans-serif;font-size:9.5pt;color:#000} h2{text-align:center;margin:10px 0 2px} h3{margin:14px 0 4px;font-size:10.5pt;border-bottom:1px solid #000}
table{width:100%;border-collapse:collapse} td,th{padding:3px 5px;border:1px solid #aaa;text-align:left} th{background:#eee} .k td:first-child{width:60%}</style></head><body>
@include('pdf.partials.entete', ['etab' => $etab, 'compact' => false])
<h2>STATISTIQUES DE CONSULTATION</h2>
<p style="text-align:center;margin:0 0 8px">Période du {{ \Illuminate\Support\Carbon::parse($s['filtres']['du'])->format('d/m/Y') }} au {{ \Illuminate\Support\Carbon::parse($s['filtres']['au'])->format('d/m/Y') }}</p>
<h3>Volume</h3>
<table class="k"><tr><td>RDV prévus</td><td>{{ $s['volume']['prevus'] }}</td></tr><tr><td>Réalisés</td><td>{{ $s['volume']['realises'] }}</td></tr>
<tr><td>Absents</td><td>{{ $s['volume']['absents'] }} ({{ $s['volume']['taux_absence'] }} %)</td></tr><tr><td>Reportés / à replanifier</td><td>{{ $s['volume']['reportes'] }}</td></tr></table>
<h3>Répartition</h3>
<table class="k"><tr><td>Premières consultations</td><td>{{ $s['repartition']['premiere_consultation'] }}</td></tr><tr><td>Contrôles</td><td>{{ $s['repartition']['controle'] }}</td></tr><tr><td>Retours de bilan</td><td>{{ $s['repartition']['retour_bilan'] }}</td></tr></table>
<h3>Temps d'attente et charge</h3>
<p>Temps d'attente moyen sur place : <strong>{{ $s['attente_moyenne'] !== null ? $s['attente_moyenne'].' min' : 'non mesuré' }}</strong> — Seniors : {{ $s['seniors_residents']['seniors'] }} consultations, résidents : {{ $s['seniors_residents']['residents'] }}.</p>
<table><tr><th>Praticien</th><th>Grade</th><th>Service</th><th>Total</th><th>Réalisés</th><th>Attente moy.</th></tr>
@foreach($s['charge'] as $c)<tr><td>{{ $c['medecin'] }}</td><td>{{ $c['grade'] }}</td><td>{{ $c['service'] }}</td><td>{{ $c['total'] }}</td><td>{{ $c['realises'] }}</td><td>{{ $c['attente_moyenne'] ?? '—' }}</td></tr>@endforeach</table>
<h3>Délai d'obtention d'un premier rendez-vous</h3>
<table><tr><th>Service</th><th>Prochaine date</th><th>Délai (jours)</th></tr>
@foreach($s['delais'] as $d)<tr><td>{{ $d['service'] }}</td><td>{{ $d['date'] ? \Illuminate\Support\Carbon::parse($d['date'])->format('d/m/Y') : '—' }}</td><td>{{ $d['jours'] ?? '—' }}</td></tr>@endforeach</table>
<p style="margin-top:16px;font-size:8pt;color:#555">Édité le {{ now()->format('d/m/Y H:i') }} par {{ $par }}.</p>
</body></html>
