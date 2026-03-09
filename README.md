# Carnet de Jeu

![Symfony](https://img.shields.io/badge/Symfony-7-black)
![PHP](https://img.shields.io/badge/PHP-8.5-blue)
![Tests](https://img.shields.io/badge/Tests-112-green)
![License](https://img.shields.io/badge/License-Not%20specified-lightgrey)

Application web Symfony pour gerer des groupes de joueurs, planifier des sessions et suivre des resultats (score simple ou match) avec isolation multi-tenant stricte.

## TL;DR (recruteur)
- C'est quoi: une app collaborative pour organiser des groupes de jeu, journaliser des sessions et consolider des stats fiables.
- Probleme resolu: eviter les donnees melangees entre groupes et fiabiliser les stats avec des liaisons utilisateurs optionnelles.
- Valeur tech: architecture en couches, handlers metier reutilises par UI web + API JSON, ACL strictes (`VIEW`/`MANAGE`) et validations cross-group.

## Contexte
Projet personnel realise pour approfondir l'architecture Symfony moderne
et demontrer la conception d'un backend multi-tenant securise avec API
JSON et tests automatises.

## Pourquoi ce projet
Carnet de Jeu centralise la vie d'un groupe de jeu:
- creation de groupes et gestion des roles (`OWNER`, `MEMBER`)
- creation d'activites et de sessions datees
- saisie de resultats avec liaison optionnelle a des membres
- partage public securise de session via token
- dashboard personnel avec indicateurs utiles

## Demo rapide
- Dashboard: `/dashboard`
- Groupes: `/groups`
- API V1: `docs/api-v1.md`
- Setup OAuth Google: `docs/OAuth-Google-Setup.md`
- Seed dataset de demo: `php bin/console app:seed-demo`

## Stack
- Backend: PHP 8.5, Symfony 7
- ORM/DB: Doctrine ORM, SQLite (dev/test)
- Front: Twig, CSS, Stimulus
- Tests: PHPUnit (tests web + applicatifs + API)
- Auth: login classique + OAuth Google (optionnel)

## Fonctionnalites cle
- Authentification classique (email/mot de passe)
- OAuth Google (optionnel)
- ACL coherentes par groupe:
  - `VIEW`: tous les membres
  - `MANAGE`: owner uniquement
- Entries prises en charge:
  - `score_simple` avec participants et score
  - `match` avec equipe domicile/exterieur
- Fiabilisation des stats:
  - liaison optionnelle `EntryScore.user`
  - liaison optionnelle `EntryMatch.homeUser` et `EntryMatch.awayUser`
  - validation stricte: utilisateur cible existant et membre du groupe
- Reponses API JSON standardisees (`code`, `message`) pour les erreurs

## Architecture
Organisation en couches pour separer metier, orchestration et transport:
- `src/Application`: commandes, requetes, handlers, result DTO
- `src/Domain`: enums/types metier
- `src/Entity`: modele Doctrine
- `src/Infrastructure/Repository` + `src/Repository`: acces donnees
- `src/UI/Http/Controller`: controles web + API

Mini schema:
```text
UI (Twig / Controllers)
  ↓
Application (Commands / Queries / Handlers)
  ↓
Domain (Entities / Rules)
  ↓
Infrastructure (Doctrine / OAuth / Repositories)
```

Patterns utilises:
- Command/Handler pour les actions d'ecriture
- Query/Handler pour la lecture de dashboard
- Reutilisation des handlers metier entre UI web et API

## Securite et isolation
- Verification explicite des droits via voter/policies
- Validation cross-group systematique (anti resource forgery)
- Un utilisateur ne peut pas:
  - lire un groupe dont il n'est pas membre
  - gerer des ressources sans role owner
  - lier des utilisateurs externes au groupe dans les entries

## API V1
Documentation complete: `docs/api-v1.md`

Exemples d'endpoints:
- `GET /api/groups`
- `POST /api/groups/{groupId}/activities`
- `POST /api/groups/{groupId}/sessions`
- `POST /api/groups/{groupId}/sessions/{sessionId}/entries/score-simple`
- `POST /api/groups/{groupId}/sessions/{sessionId}/entries/match`

## Installation locale
Prerequis:
- PHP 8.5+
- Composer
- Extension SQLite PDO (ou autre SGBD configure)

Installation:
```bash
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-demo
symfony server:start
```

Si vous n'utilisez pas Symfony CLI:
```bash
php -S 127.0.0.1:8000 -t public
```

## Seed de demonstration
Commande principale:
```bash
php bin/console app:seed-demo
```

Le seed cree (de facon idempotente):
- 2 utilisateurs de demo
- 2 groupes
- plusieurs activites
- plusieurs sessions
- des entries `score_simple` et `match`
- une invitation en attente

Comptes locaux crees:
- `demo@local.test` / `demo1234`
- `demo.member@local.test` / `demo1234`

## Parcours demo (entretien, 5-8 min)
Ordre recommande:
1. Homepage: positionnement produit + CTA.
2. Login puis dashboard: vue globale (groupes, sessions recentes, stats).
3. Groupe: navigation vers activites et sessions.
4. Session: creation d'entries `score_simple` puis `match`.
5. Profil membre: lecture des stats individuelles.
6. Partage public: activation du lien `/s/{token}` en lecture seule.
7. API JSON: montrer `GET /api/groups` puis un endpoint write.
8. OAuth Google (si configure): login alternatif.

Commandes utiles juste avant la demo:
```bash
php bin/console app:seed-demo
php bin/phpunit
```

## Variables d'environnement
Fichier principal: `.env` (et `.env.local` en local)

Variables frequentes:
- `APP_ENV`
- `APP_SECRET`
- `DATABASE_URL`
- `MAILER_DSN`
- `GOOGLE_CLIENT_ID` (si OAuth actif)
- `GOOGLE_CLIENT_SECRET` (si OAuth actif)

Bonnes pratiques:
- garder les secrets dans `.env.local` (jamais commits)
- utiliser une base locale dediee (SQLite ou DB de dev isolee)
- verifier que la doc d'installation fonctionne sur un clone neuf

Voir aussi: `docs/OAuth-Google-Setup.md`

## Tests
Lancer toute la suite:
```bash
php bin/phpunit
```

Etat actuel de reference:
- `112 tests`
- `355 assertions`

## Checklist release locale
- page d'accueil propre et lisible sans erreur visuelle
- navigation complete sans impasse (retours dashboard/groupe/session)
- seed demo execute et verifie (`app:seed-demo`)
- compte demo classique fonctionnel
- compte demo Google configure si OAuth disponible
- README relu en mode recruteur (contexte, valeur, setup, demo)
- variables d'environnement nettoyees (`.env`/`.env.local`)
- installation testee de zero (nouveau clone + migrations + seed + login)

## Captures ecran (portfolio)
Ajoutez vos visuels dans `docs/images/` puis referencez-les ici.

Visuels recommandes:
- homepage: `docs/images/homepage.png`
- dashboard: `docs/images/dashboard.png`
- groupe: `docs/images/group.png`
- session: `docs/images/session.png`
- API docs (optionnel): `docs/images/api-docs.png`

Exemple d'integration:
```md
![Homepage](docs/images/homepage.png)
![Dashboard](docs/images/dashboard.png)
![Groupe](docs/images/group.png)
![Session](docs/images/session.png)
```

## Points techniquement interessants
- Isolation multi-tenant stricte (controles d'appartenance groupe sur chaque action critique).
- ACL explicites via voter (`GroupVoter`) et roles de membership.
- Handlers metier uniques appeles par web controllers et API controllers.
- Reponses API d'erreur homogenes (`code` + `message`).
- Seed de demo idempotent pour onboard rapide et presentation stable.

## Feuille de route
- OpenAPI/Swagger
- Pagination API
- Filtres et tris avances
- Exports CSV/JSON des sessions
- Statistiques avancees par membre et activite
