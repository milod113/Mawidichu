<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><style>@page{margin:36px} body{font-family:DejaVu Sans,sans-serif;font-size:11pt;color:#000} h2{text-align:center;margin:14px 0} table{width:100%;border-collapse:collapse} td,th{padding:5px;border-bottom:1px solid #999;text-align:left;vertical-align:top} .sig{margin-top:50px;text-align:right} .meta{margin-bottom:10px}</style></head><body>
@include('pdf.partials.entete', ['etab' => $etab, 'compact' => false])
<h2>CERTIFICAT DE PRÉSENCE</h2>
<p>Nous soussignés certifions que <strong>{{ strtoupper($rdv->patient->nom) }} {{ $rdv->patient->prenom }}</strong> (IPP {{ $rdv->patient->ipp }}) s'est présenté(e) le <strong>{{ $rdv->date_rdv->format('d/m/Y') }}</strong>@if($rdv->heure_arrivee_checkin) à {{ $rdv->heure_arrivee_checkin->format('H:i') }}@endif en consultation au service <strong>{{ $rdv->service->nom }}</strong>.</p>
<p>Ce certificat est délivré pour servir et valoir ce que de droit.</p>
<div class="sig">Fait à Tlemcen, le {{ now()->format('d/m/Y') }}<br>Cachet et signature</div>
</body></html>
