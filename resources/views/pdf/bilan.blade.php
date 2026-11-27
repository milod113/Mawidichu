<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><style>@page{margin:36px} body{font-family:DejaVu Sans,sans-serif;font-size:11pt;color:#000} h2{text-align:center;margin:14px 0} table{width:100%;border-collapse:collapse} td,th{padding:5px;border-bottom:1px solid #999;text-align:left;vertical-align:top} .sig{margin-top:50px;text-align:right} .meta{margin-bottom:10px}</style></head><body>
@include('pdf.partials.entete', ['etab' => $etab, 'compact' => false])
<div class="meta"><strong>{{ $c->service->nom }}</strong> — le {{ now()->format('d/m/Y') }}<br>
Patient : <strong>{{ strtoupper($c->patient->nom) }} {{ $c->patient->prenom }}</strong> (IPP {{ $c->patient->ipp }})</div>
<h2>DEMANDE DE BILAN</h2>
<ul>@forelse($c->bilans_demandes_json ?? [] as $b)<li style="margin:4px 0">☐ {{ $b }}</li>@empty<li>—</li>@endforelse</ul>
@if($c->delai_controle_jours)<p>À rapporter lors du contrôle dans {{ $c->delai_controle_jours }} jour(s).</p>@endif
<div class="sig">Dr {{ $c->medecin->user->name }}<br>Signature et cachet</div>
</body></html>
