<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><style>@page{margin:36px} body{font-family:DejaVu Sans,sans-serif;font-size:11pt;color:#000} h2{text-align:center;margin:14px 0} table{width:100%;border-collapse:collapse} td,th{padding:5px;border-bottom:1px solid #999;text-align:left;vertical-align:top} .sig{margin-top:50px;text-align:right} .meta{margin-bottom:10px}</style></head><body>
@include('pdf.partials.entete', ['etab' => $etab, 'compact' => false])
<div class="meta"><strong>{{ $c->service->nom }}</strong> — Tlemcen, le {{ now()->format('d/m/Y') }}<br>
Patient : <strong>{{ strtoupper($c->patient->nom) }} {{ $c->patient->prenom }}</strong> (IPP {{ $c->patient->ipp }})@if($c->patient->date_naissance) — né(e) le {{ $c->patient->date_naissance->format('d/m/Y') }}@endif</div>
<h2>ORDONNANCE</h2>
<table>
<tr><th>Médicament</th><th>Posologie</th><th>Durée</th></tr>
@forelse($c->ordonnance_json ?? [] as $l)<tr><td>{{ $l['medicament'] ?? '' }}</td><td>{{ $l['posologie'] ?? '' }}</td><td>{{ $l['duree'] ?? '' }}</td></tr>
@empty<tr><td colspan="3">—</td></tr>@endforelse
</table>
<div class="sig">Dr {{ $c->medecin->user->name }}<br>Signature et cachet</div>
</body></html>
