import { useTrans } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Écran kiosque de la salle d'attente : plein écran, grandes polices, signal sonore et
 * annonce vocale (synthèse du navigateur, hors-ligne) à chaque nouvel appel.
 */
export default function Display({ ailes, aile, appels: initial, refresh }) {
    const { t, locale } = useTrans();
    const [appels, setAppels] = useState(initial);
    const [son, setSon] = useState(false);
    const [heure, setHeure] = useState(new Date());
    const vus = useRef(new Map(initial.map((a) => [a.id, a.at])));
    const sonRef = useRef(false);
    sonRef.current = son;

    const annoncer = useCallback((a) => {
        if (!sonRef.current) return;
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [880, 660].forEach((f, i) => {
                const o = ctx.createOscillator(); const g = ctx.createGain();
                o.frequency.value = f; o.connect(g); g.connect(ctx.destination);
                g.gain.setValueAtTime(0.25, ctx.currentTime + i * 0.35); g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.35 + 0.3);
                o.start(ctx.currentTime + i * 0.35); o.stop(ctx.currentTime + i * 0.35 + 0.32);
            });
            const texte = locale === 'ar'
                ? `التذكرة ${a.ticket}${a.box ? `، ${a.box}` : ''}`
                : `Ticket ${a.ticket.replaceAll('-', ' ')}${a.box ? `, ${a.box}` : ''}`;
            const u = new SpeechSynthesisUtterance(a.nom ? `${a.nom}${a.box ? `, ${a.box}` : ''}` : texte);
            u.lang = locale === 'ar' ? 'ar-DZ' : 'fr-FR';
            setTimeout(() => window.speechSynthesis?.speak(u), 800);
        } catch { /* son indisponible : l'affichage reste utilisable */ }
    }, [locale]);

    const charger = useCallback(() => {
        fetch(route('tv.donnees', aile ? { aile } : {}), { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((liste) => {
                liste.forEach((a) => { if (vus.current.get(a.id) !== a.at) { vus.current.set(a.id, a.at); annoncer(a); } });
                setAppels(liste);
            })
            .catch(() => {});
    }, [aile, annoncer]);

    useEffect(() => {
        const timer = setInterval(charger, refresh * 1000);
        const clock = setInterval(() => setHeure(new Date()), 1000);
        const channel = window.Echo ? window.Echo.private('tv').listen('.patient.called', charger) : null;
        return () => { clearInterval(timer); clearInterval(clock); if (channel) window.Echo.leave('tv'); };
    }, [charger, refresh]);

    const [premier, ...autres] = appels;

    return (
        <div className="flex min-h-screen flex-col bg-gray-950 p-8 text-white">
            <Head title={t('tv.title')} />
            <header className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-3xl font-bold">{t('tv.title')}{aile ? ` — ${aile}` : ''}</h1>
                <div className="flex items-center gap-4">
                    {ailes.length > 0 && (
                        <select value={aile ?? ''} onChange={(e) => router.get(route('tv'), e.target.value ? { aile: e.target.value } : {})} className="rounded-md border-gray-700 bg-gray-900 text-sm">
                            <option value="">{t('tv.toutes_ailes')}</option>{ailes.map((a) => <option key={a} value={a}>{a}</option>)}
                        </select>
                    )}
                    <button type="button" onClick={() => setSon(!son)} className="rounded-md border border-gray-700 px-3 py-1.5 text-sm">{son ? t('tv.son_on') : t('tv.son_off')}</button>
                    <span className="font-mono text-3xl" dir="ltr">{heure.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}</span>
                </div>
            </header>

            {!premier ? <p className="m-auto text-3xl text-gray-400">{t('tv.attente')}</p> : (
                <>
                    <section className="mb-8 rounded-3xl bg-primary-600 p-10 text-center shadow-2xl">
                        <p className="text-2xl uppercase tracking-widest text-primary-100">{t('tv.appel')}</p>
                        <p className="my-4 text-8xl font-extrabold tracking-wide" dir="ltr">{premier.nom ?? premier.ticket}</p>
                        <p className="text-4xl font-semibold">{premier.box ?? premier.service_nom}</p>
                    </section>
                    <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {autres.map((a) => (
                            <li key={a.id} className="rounded-2xl bg-gray-900 p-6 text-center ring-1 ring-gray-800">
                                <p className="text-5xl font-bold" dir="ltr">{a.nom ?? a.ticket}</p>
                                <p className="mt-2 text-2xl text-gray-300">{a.box ?? a.service_nom}</p>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </div>
    );
}
