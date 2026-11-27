<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: {{ $compact ? '8px' : '28px' }}; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $compact ? '8' : '10' }}pt; color: #000; }
        .ticket { text-align:center; font-size: {{ $compact ? '22' : '34' }}pt; font-weight:bold; margin: 8px 0; letter-spacing: 1px; }
        table { width:100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        td.l { color:#444; width: 38%; }
        h4 { margin: 10px 0 3px; font-size: {{ $compact ? '9' : '11' }}pt; }
        ul { margin: 0; padding-left: 14px; }
        .small { font-size: 7pt; color:#555; margin-top:10px; text-align:center; }
    </style>
</head>
<body>
    @include('pdf.partials.entete', ['etab' => $etab, 'compact' => $compact])

    <div style="text-align:center">TICKET DE RENDEZ-VOUS</div>
    <div class="ticket">{{ $rdv->numero_ticket }}</div>

    <table>
        <tr><td class="l">Patient</td><td><strong>{{ strtoupper($rdv->patient->nom) }} {{ $rdv->patient->prenom }}</strong></td></tr>
        <tr><td class="l">IPP</td><td>{{ $rdv->patient->ipp }}</td></tr>
        <tr><td class="l">Service</td><td>{{ $rdv->service->nom }}</td></tr>
        <tr><td class="l">Consultation</td><td>{{ $rdv->typeConsultation->libelle }}</td></tr>
        @if($rdv->medecin)<tr><td class="l">Médecin</td><td>{{ $rdv->medecin->user->name }}</td></tr>@endif
        @if($rdv->box)<tr><td class="l">Box</td><td>{{ $rdv->box->nom_affichage ?? 'Box '.$rdv->box->numero_box }}</td></tr>@endif
        <tr><td class="l">Date</td><td><strong>{{ $rdv->date_rdv->format('d/m/Y') }}</strong></td></tr>
        @if($rdv->heure_vague_prevue)<tr><td class="l">Heure de convocation</td><td><strong>{{ substr($rdv->heure_vague_prevue, 0, 5) }}</strong></td></tr>@endif
    </table>

    @if($apporter->isNotEmpty())
        <h4>À apporter le jour de la consultation</h4>
        <ul>@foreach($apporter as $ligne)<li>{{ $ligne }}</li>@endforeach</ul>
    @endif

    <div class="small">Présentez ce ticket à l'accueil pour le pointage. Édité le {{ now()->format('d/m/Y H:i') }}.</div>
</body>
</html>
