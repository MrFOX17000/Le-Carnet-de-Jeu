# API V1 Documentation

## Vue d'ensemble

L'API JSON V1 du Carnet de Jeu permet de gérer des groupes de joueurs, leurs activités ludiques, sessions de jeu et résultats.

**Base URL** : `/api`

**Format** : JSON

**Authentification** : Session cookie (utilisateur connecté requis)

---

## Architecture & Principes

### Multi-tenant strict
Chaque endpoint vérifie l'appartenance au groupe. Un utilisateur ne peut jamais accéder aux données d'un autre groupe.

### ACL cohérentes
- **VIEW** : Tous les membres (owner + member) peuvent lire les données du groupe
- **MANAGE** : Seul l'owner peut créer/modifier les données du groupe

### Codes HTTP
- **200** OK - Lecture réussie
- **201** Created - Création réussie
- **400** Bad Request - JSON invalide ou données illisibles
- **401** Unauthorized - Utilisateur non authentifié
- **403** Forbidden - Utilisateur authentifié mais sans droits suffisants
- **404** Not Found - Ressource inexistante
- **422** Unprocessable Entity - Validation métier échouée
- **429** Too Many Requests - Trop de requêtes d'écriture API dans une courte fenêtre

### Format de réponse

**Succès (lecture)** :
```json
{
  "data": { ... }
}
```

**Succès (création)** :
```json
{
  "data": {
    "id": 42,
    "groupId": 3
  }
}
```

**Erreur** :
```json
{
  "error": {
    "code": "error_code",
    "message": "Human readable message"
  }
}
```

Pour les endpoints d'écriture API (`POST`, `PUT`, `PATCH`, `DELETE` sous `/api/*`), un rate limiting est appliqué. En cas de dépassement, l'API retourne :
- `429` avec `error.code = "too_many_requests"`
- un header `Retry-After` indiquant le nombre de secondes avant un nouvel essai

---

## Endpoints

### Groupes

#### GET /api/groups
Liste les groupes de l'utilisateur connecté.

**Auth** : Requise

**Réponse 200** :
```json
{
  "data": [
    {
      "id": 3,
      "name": "Les Vikings",
      "createdAt": "2026-03-06T10:00:00+00:00",
      "role": "OWNER"
    },
    {
      "id": 7,
      "name": "Soirées Jeux",
      "createdAt": "2026-03-05T15:30:00+00:00",
      "role": "MEMBER"
    }
  ]
}
```

**Erreurs** :
- `401` : `unauthorized`

---

#### GET /api/groups/{groupId}
Détails d'un groupe.

**Auth** : Requise + membre du groupe

**Réponse 200** :
```json
{
  "data": {
    "id": 3,
    "name": "Les Vikings",
    "createdAt": "2026-03-06T10:00:00+00:00",
    "role": "OWNER"
  }
}
```

**Erreurs** :
- `401` : `unauthorized`
- `403` : `forbidden` (non-membre)
- `404` : `not_found`

---

#### POST /api/groups
Crée un nouveau groupe.

**Auth** : Requise

**Payload** :
```json
{
  "name": "Les Vikings"
}
```

**Validation** :
- `name` : requis, non vide

**Réponse 201** :
```json
{
  "data": {
    "id": 3,
    "name": "Les Vikings"
  }
}
```

**Erreurs** :
- `401` : `unauthorized`
- `400` : `invalid_json`
- `422` : `name_required`

---

### Activités

#### POST /api/groups/{groupId}/activities
Crée une activité dans un groupe.

**Auth** : Requise + owner du groupe

**Payload** :
```json
{
  "name": "Rocket League"
}
```

**Validation** :
- `name` : requis, non vide

**Réponse 201** :
```json
{
  "data": {
    "id": 12,
    "groupId": 3,
    "name": "Rocket League"
  }
}
```

**Erreurs** :
- `401` : `unauthorized`
- `403` : `forbidden` (member non-owner)
- `404` : `not_found` (groupe inexistant)
- `400` : `invalid_json`
- `422` : `name_required`

---

### Sessions

#### GET /api/groups/{groupId}/sessions
Liste les sessions d'un groupe (triées par date décroissante).

**Auth** : Requise + membre du groupe

**Réponse 200** :
```json
{
  "data": [
    {
      "id": 8,
      "groupId": 3,
      "activityId": 12,
      "activityName": "Rocket League",
      "title": "Soirée ranked",
      "playedAt": "2026-03-06T20:00:00+00:00",
      "entriesCount": 2
    }
  ]
}
```

**Erreurs** :
- `401` : `unauthorized`
- `403` : `forbidden` (non-membre)
- `404` : `not_found` (groupe inexistant)

---

#### GET /api/groups/{groupId}/sessions/{sessionId}
Détail d'une session avec ses entries.

**Auth** : Requise + membre du groupe

**Réponse 200** :
```json
{
  "data": {
    "id": 8,
    "groupId": 3,
    "activityId": 12,
    "activityName": "Rocket League",
    "title": "Soirée ranked",
    "playedAt": "2026-03-06T20:00:00+00:00",
    "createdAt": "2026-03-06T19:45:00+00:00",
    "createdBy": {
      "id": 5,
      "email": "mathias@example.com"
    },
    "entries": [
      {
        "id": 21,
        "type": "score_simple",
        "label": "Manche 1",
        "details": {
          "scores": [
            {
              "participantName": "Mathias",
              "score": 42
            },
            {
              "participantName": "Lucas",
              "score": 38
            }
          ]
        }
      },
      {
        "id": 22,
        "type": "match",
        "label": "Finale BO1",
        "details": {
          "homeName": "Team Blue",
          "awayName": "Team Orange",
          "homeScore": 3,
          "awayScore": 1
        }
      }
    ]
  }
}
```

**Erreurs** :
- `401` : `unauthorized`
- `403` : `forbidden` (non-membre)
- `404` : `not_found` (groupe ou session inexistant)

---

#### POST /api/groups/{groupId}/sessions
Crée une session de jeu.

**Auth** : Requise + owner du groupe

**Payload** :
```json
{
  "activityId": 12,
  "playedAt": "2026-03-06T20:00:00+00:00",
  "title": "Soirée ranked"
}
```

**Validation** :
- `activityId` : requis, entier
- `playedAt` : requis, ISO 8601 valide
- `title` : optionnel
- **Règle critique** : l'activité doit appartenir au groupe

**Réponse 201** :
```json
{
  "data": {
    "id": 8,
    "groupId": 3
  }
}
```

**Erreurs** :
- `401` : `unauthorized`
- `403` : `forbidden` (member non-owner)
- `404` : `not_found` (groupe inexistant)
- `400` : `invalid_json` | `invalid_played_at`
- `422` : `activity_id_required` | `played_at_required` | `activity_not_in_group`

---

### Entries (Score Simple)

#### POST /api/groups/{groupId}/sessions/{sessionId}/entries/score-simple
Crée une entry de type score simple.

**Auth** : Requise + owner du groupe

**Payload** :
```json
{
  "label": "Manche 1",
  "scores": [
    {
      "participantName": "Mathias",
      "score": 42,
      "userId": 5
    },
    {
      "participantName": "Lucas",
      "score": 38,
      "userId": 9
    }
  ]
}
```

**Validation** :
- `scores` : requis, array non vide
- `participantName` : requis, string
- `score` : requis, numérique
- `userId` : optionnel, entier positif, membre du groupe
- `label` : optionnel
- **Règle critique** : la session doit appartenir au groupe

**Réponse 201** :
```json
{
  "data": {
    "id": 21,
    "sessionId": 8,
    "groupId": 3,
    "type": "score_simple"
  }
}
```

**Erreurs** :
- `401` : `unauthorized`
- `403` : `forbidden` (member non-owner)
- `404` : `not_found` (groupe ou session inexistant)
- `400` : `invalid_json`
- `422` : `scores_required` | `scores_empty` | `participant_name_required` | `score_value_required` | `score_must_be_numeric` | `participant_user_id_invalid` | `participant_user_not_found` | `participant_user_not_in_group` | `session_not_in_group`

---

### Entries (Match)

#### POST /api/groups/{groupId}/sessions/{sessionId}/entries/match
Crée une entry de type match (équipe vs équipe).

**Auth** : Requise + owner du groupe

**Payload** :
```json
{
  "label": "Finale BO1",
  "homeName": "Team Blue",
  "awayName": "Team Orange",
  "homeScore": 3,
  "awayScore": 1,
  "homeUserId": 5,
  "awayUserId": 9
}
```

**Validation** :
- `homeName` : requis, non vide
- `awayName` : requis, non vide
- `homeName` ≠ `awayName` (case-insensitive)
- `homeScore` : requis, numérique, >= 0
- `awayScore` : requis, numérique, >= 0
- `homeUserId` : optionnel, entier positif, membre du groupe
- `awayUserId` : optionnel, entier positif, membre du groupe
- `homeUserId` et `awayUserId` doivent être différents
- `label` : optionnel
- **Règle critique** : la session doit appartenir au groupe

**Réponse 201** :
```json
{
  "data": {
    "id": 22,
    "sessionId": 8,
    "groupId": 3,
    "type": "match"
  }
}
```

**Erreurs** :
- `401` : `unauthorized`
- `403` : `forbidden` (member non-owner)
- `404` : `not_found` (groupe ou session inexistant)
- `400` : `invalid_json`
- `422` : `home_name_required` | `away_name_required` | `teams_must_be_different` | `home_score_required` | `away_score_required` | `home_score_must_be_numeric` | `away_score_must_be_numeric` | `home_score_must_be_positive` | `away_score_must_be_positive` | `home_user_id_invalid` | `away_user_id_invalid` | `home_user_not_found` | `away_user_not_found` | `home_user_not_in_group` | `away_user_not_in_group` | `home_away_users_must_be_different` | `session_not_in_group`

---

## Codes d'erreur

### Authentification
- `unauthorized` : Utilisateur non connecté (401)
- `forbidden` : Utilisateur connecté mais droits insuffisants (403)

### Ressources
- `not_found` : Ressource inexistante (404)

### Validation JSON
- `invalid_json` : JSON malformé ou illisible (400)

### Format d'erreur API
- Les erreurs API suivent le format : `{ "error": { "code": "...", "message": "..." } }`

### Validation métier (422)

**Groupes** :
- `name_required` : Le nom du groupe est requis

**Activités** :
- `name_required` : Le nom de l'activité est requis

**Sessions** :
- `activity_id_required` : L'activityId est requis
- `played_at_required` : La date playedAt est requise
- `invalid_played_at` : La date playedAt n'est pas valide
- `activity_not_in_group` : L'activité n'appartient pas au groupe

**Entries Score Simple** :
- `scores_required` : Le champ scores est requis
- `scores_empty` : Le tableau scores ne peut pas être vide
- `scores_must_be_array` : Le champ scores doit être un tableau
- `score_must_be_object` : Chaque score doit être un objet
- `participant_name_required` : Le nom du participant est requis
- `score_value_required` : La valeur du score est requise
- `score_must_be_numeric` : Le score doit être numérique
- `participant_user_id_invalid` : Le userId participant doit etre un entier positif
- `participant_user_not_found` : Le user lie au participant est introuvable
- `participant_user_not_in_group` : Le user lie au participant doit etre membre du groupe
- `session_not_in_group` : La session n'appartient pas au groupe

**Entries Match** :
- `home_name_required` : Le nom de l'équipe home est requis
- `away_name_required` : Le nom de l'équipe away est requis
- `teams_must_be_different` : Les équipes doivent être différentes
- `home_score_required` : Le score home est requis
- `away_score_required` : Le score away est requis
- `home_score_must_be_numeric` : Le score home doit être numérique
- `away_score_must_be_numeric` : Le score away doit être numérique
- `home_score_must_be_positive` : Le score home doit être >= 0
- `away_score_must_be_positive` : Le score away doit être >= 0
- `home_user_id_invalid` : Le homeUserId doit etre un entier positif
- `away_user_id_invalid` : Le awayUserId doit etre un entier positif
- `home_user_not_found` : Le user home lie est introuvable
- `away_user_not_found` : Le user away lie est introuvable
- `home_user_not_in_group` : Le user home lie doit etre membre du groupe
- `away_user_not_in_group` : Le user away lie doit etre membre du groupe
- `home_away_users_must_be_different` : Les users home/away lies doivent etre differents
- `session_not_in_group` : La session n'appartient pas au groupe

---

## Exemples d'utilisation

### Flux complet : Créer un groupe et y ajouter des données

```bash
# 1. Créer un groupe
POST /api/groups
{
  "name": "Les Vikings"
}
→ 201 { "data": { "id": 3, "name": "Les Vikings" } }

# 2. Créer une activité
POST /api/groups/3/activities
{
  "name": "Rocket League"
}
→ 201 { "data": { "id": 12, "groupId": 3, "name": "Rocket League" } }

# 3. Créer une session
POST /api/groups/3/sessions
{
  "activityId": 12,
  "playedAt": "2026-03-06T20:00:00+00:00",
  "title": "Soirée ranked"
}
→ 201 { "data": { "id": 8, "groupId": 3 } }

# 4. Ajouter un résultat score simple
POST /api/groups/3/sessions/8/entries/score-simple
{
  "label": "Manche 1",
  "scores": [
    { "participantName": "Mathias", "score": 42, "userId": 5 },
    { "participantName": "Lucas", "score": 38, "userId": 9 }
  ]
}
→ 201 { "data": { "id": 21, "sessionId": 8, "groupId": 3, "type": "score_simple" } }

# 5. Ajouter un résultat match
POST /api/groups/3/sessions/8/entries/match
{
  "label": "Finale",
  "homeName": "Team Blue",
  "awayName": "Team Orange",
  "homeScore": 3,
  "awayScore": 1,
  "homeUserId": 5,
  "awayUserId": 9
}
→ 201 { "data": { "id": 22, "sessionId": 8, "groupId": 3, "type": "match" } }

# 6. Lire les sessions du groupe
GET /api/groups/3/sessions
→ 200 { "data": [ { "id": 8, "title": "Soirée ranked", ... } ] }

# 7. Lire le détail d'une session
GET /api/groups/3/sessions/8
→ 200 { "data": { "id": 8, "entries": [ ... ] } }
```

---

## Règles de sécurité

### Multi-tenant strict
Un utilisateur ne peut **jamais** :
- Lire les données d'un groupe auquel il n'appartient pas
- Créer/modifier des ressources dans un groupe dont il n'est pas owner
- Référencer une activité/session d'un autre groupe (validations cross-group)

### Validations cross-group
Les endpoints vérifient systématiquement que :
- Une activité appartient au groupe lors de la création d'une session
- Une session appartient au groupe lors de la création d'une entry

Ces validations empêchent les attaques de type "resource forgery" où un attaquant tenterait d'utiliser l'ID d'une ressource d'un autre tenant.

### Tests de sécurité
Tous les endpoints sont testés pour :
- Anonyme → 401
- Non-membre → 403
- Member (non-owner) sur endpoints MANAGE → 403
- Ressources d'autres groupes → isolation garantie

---

## Notes d'implémentation

### Architecture
- **Application Layer** : Query/Command/Handler/Result pattern
- **DTOs** : Objets dédiés pour les réponses API
- **Réutilisation** : Les handlers sont partagés entre web et API (zéro duplication métier)
- **Tests** : 111 tests fonctionnels couvrant les endpoints et cas d'erreur principaux

### Performance
- Les listes utilisent des requêtes filtrées (pas de chargement global puis filtrage)
- Les relations Doctrine sont chargées efficacement
- Pas de N+1 queries sur les endpoints de lecture

### Évolutions futures possibles
- Pagination sur les listes
- Filtres/tri avancés
- GET /api/groups/{groupId}/activities
- OpenAPI/Swagger specification
- Rate limiting
- API tokens (alternative aux cookies session)
