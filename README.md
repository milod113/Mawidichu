# Mawid CHU

Gestion des rendez-vous et des consultations externes de la **Clinique Boudghène** (CHU Dr. Tidjani Damerdji, Tlemcen).

Application 100 % on-premise (intranet du CHU, aucune dépendance Internet à l'exécution) : Laravel 12, Inertia + React 18, Tailwind CSS (français / arabe RTL, thème clair / sombre), Laravel Reverb (WebSockets locaux) et `spatie/laravel-permission`.

## État d'avancement

Le projet suit la feuille de route du cahier des charges, étape par étape.

| Étape | Contenu | État |
|---|---|---|
| 1 | Socle technique : stack, layout (sidebar, FR/AR RTL, thème), 7 rôles, seeders | ✅ |
| 2 | Base de données et modèles métier, SoftDeletes, audit | ✅ |
| 3 | Paramétrage (services, box, types de consultation, médecins, plannings, jours fériés) | ✅ |
| 4 | Accueil, patients (IPP) et prise de RDV | à venir |
| 5 | File d'attente, check-in et pré-consultation | à venir |
| 6 | Poste médecin, appel en salle et écran TV | à venir |
| 7 | Tableaux de bord, statistiques et exports | à venir |

## Prérequis

- PHP 8.3+ (extensions : pdo_mysql, mbstring, intl, xml, zip, gd)
- Composer 2
- Node.js 20+ et npm
- MySQL 8+ ou MariaDB 10.6+

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate

# Créer la base (utf8mb4) puis renseigner DB_* dans .env
php artisan migrate --seed

# WebSockets locaux : génère les clés Reverb et passe BROADCAST_CONNECTION à "reverb"
php artisan reverb:install

npm install
npm run build
```

En développement, `composer dev` lance en parallèle le serveur PHP, la file de jobs, Reverb et Vite.

## Comptes de test

Créés uniquement en environnement `local` / `testing` (mot de passe : `MAWID_SEED_PASSWORD`, `password` par défaut).

| E-mail | Rôle(s) |
|---|---|
| admin@mawid.local | super_admin |
| direction@mawid.local | direction |
| chef.service@mawid.local | chef_service + medecin |
| medecin@mawid.local | medecin |
| resident@mawid.local | medecin |
| infirmier@mawid.local | infirmier |
| secretaire@mawid.local | secretaire |
| ecran.tv@mawid.local | ecran_tv |

## Principes

- **Zéro paramètre figé dans le code** : l'établissement, la langue par défaut, le thème, etc. passent par `config/mawid.php` et `.env` ; les paramètres métier (durées, quotas, vacations…) seront stockés en base.
- **Jours fériés** : la table `jours_feries` est amorcée depuis `database/data/jours_feries_algerie.php`. Les fêtes religieuses sont en statut `estimatif` et doivent être confirmées ou décalées après l'annonce officielle.
- **Pas de suppression physique** : `SoftDeletes` sur toutes les tables métier ; les données patients / médicales refusent en plus `forceDelete()` (trait `PreventsForceDelete`) et leurs clés étrangères sont en `RESTRICT`.
- **Audit** : le trait `Auditable` journalise création, modification, suppression, restauration (qui, anciennes / nouvelles valeurs, IP) dans `audit_logs`, table immuable. Les attributs masqués (mot de passe…) n'y figurent jamais. `AuditLog::record()` permet de tracer une action métier explicite.
- **Cloisonnement** : `Consultation::scopeForService()` limite l'historique médical à un service ; les dérogations par demande d'avis (`DemandeAvis::scopeActive()`) seront branchées à l'Étape 6.
- **Menu latéral** : déclaré dans `config/navigation.php` (route, libellé, icône, rôles autorisés).
- **Traductions d'interface** : `lang/ui/fr.json` et `lang/ui/ar.json`.

## Paramétrage (Étape 3)

Menu **Paramétrage** : `Services` (super_admin et chef_service) et `Jours fériés` (super_admin).

- Fiche service à onglets : général (mode de planification, attribution des RDV, pré-consultation, affichage TV, documents à ramener), box, types de consultation (durée, couleur, bilans requis), médecins (grade, durée / quota par défaut, surbooking en places ou en %), plannings types (affectation nominative **ou** par box / équipe ; quota et vagues selon le mode).
- Un chef de service ne voit et ne modifie que son service (`ServicePolicy`) ; code, nom et activation du service restent réservés au super admin.
- Jours fériés : filtre par année, ajout / correction, **décalage de toute la plage d'une fête (±1 jour) et confirmation en un clic** (les jours d'une même fête partagent un `groupe`).
- Les comptes de démonstration (local / testing) sont accompagnés d'un service Cardiologie complet et d'un service Pédiatrie vide (`DemoDataSeeder`).

## Tests

```bash
php artisan test
```
