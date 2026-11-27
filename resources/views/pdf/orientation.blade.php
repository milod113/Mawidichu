<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><style>@page{margin:36px} body{font-family:DejaVu Sans,sans-serif;font-size:11pt;color:#000} h2{text-align:center;margin:14px 0} table{width:100%;border-collapse:collapse} td,th{padding:5px;border-bottom:1px solid #999;text-align:left;vertical-align:top} .sig{margin-top:50px;text-align:right} .meta{margin-bottom:10px}</style></head><body>
@include('pdf.partials.entete', ['etab' => $etab, 'compact' => false])
<div class="meta" style="text-align:right">Tlemcen, le {{ now()->format('d/m/Y') }}</div>
<p>À l'attention du service de <strong>{{ $vers->nom }}</strong></p>
<h2>LETTRE D'ORIENTATION</h2>
<p>Cher confrère,</p>
<p>Je vous adresse <strong>{{ strtoupper($c->patient->nom) }} {{ $c->patient->prenom }}</strong> (IPP {{ $c->patient->ipp }}), suivi(e) en {{ $c->service->nom }}.</p>
@if($motif)<p><strong>Motif :</strong> {{ $motif }}</p>@endif
@if($c->diagnostic)<p><strong>Diagnostic :</strong> {{ $c->diagnostic }}</p>@endif
@if($c->conduite_a_tenir)<p><strong>Conduite tenue :</strong> {{ $c->conduite_a_tenir }}</p>@endif
<p>Je vous remercie de votre prise en charge et de vos conclusions. Confraternellement.</p>
<div class="sig">Dr {{ $c->medecin->user->name }}<br>Signature et cachet</div>
</body></html>
