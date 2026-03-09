# Étape 14 — OAuth Google — Guide Setup

## Phase 2 Complète ✅

**Structure installée:** KnpU OAuth2 Client Bundle + League OAuth2 Google  
**Routes configurées:** `/connect/google` + `/connect/google/check`  
**Handler métier:** `AuthenticateWithGoogleCommand` → `AuthenticateWithGoogleHandler`  
**Authenticator:** `GoogleOAuthAuthenticator` intégré au firewall Symfony

---

## Pour brancher tes vraies credentials Google

### 1️⃣ Créer une application Google Cloud

1. Va sur https://console.cloud.google.com/
2. Crée un nouveau projet (ou utilise un existant)
3. Active l'API Google+ : Menu → APIs & Services → Enable APIs → Google+ API
4. Va dans "OAuth 2.0 Credentials" (Identifiants)
5. Crée une nouvelle credential de type "OAuth 2.0 Client ID"
   - Application type: Web application
   - Authorized JavaScript origins: `http://localhost:8000` (dev), `https://tondomaine.fr` (prod)
   - Authorized redirect URIs: `http://localhost:8000/connect/google/check` (et ta URL prod)
6. Note ton **Client ID** et **Client Secret**

---

### 2️⃣ Ajouter à `.env.local` (jamais commiter!)

```bash
# .env.local
GOOGLE_CLIENT_ID=ton-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=ton-client-secret
```

Tu peux garder les valeurs test dans `.env.test` comme c'est actuellement:
```yaml
# .env.test
GOOGLE_CLIENT_ID='test_client_id'
GOOGLE_CLIENT_SECRET='test_client_secret'
```

---

### 3️⃣ Vérifier la config

Route de redirection OAuth Google → `/connect/google/check` ✅ (déjà configurée)

Fichier: `config/packages/knpu_oauth2_client.yaml`

```yaml
knpu_oauth2_client:
    clients:
        google:
            type: google
            client_id: '%env(GOOGLE_CLIENT_ID)%'
            client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
            redirect_route: connect_google_check
```

---

### 4️⃣ Tester le flux

1. Va sur `/login`
2. Clique sur "Connect with Google" (bouton à ajouter dans template login)
3. Tu seras redirigé vers Google
4. Après approbation → utilisateur créé/connecté → redirect `/dashboard`

---

## Architecture Finale OAuth Google

```
GET /login
  ↓ (User clique "Connect with Google")
GET /connect/google (public)
  ↓ (Redirection vers Google OAuth)
https://accounts.google.com/o/oauth2/auth?client_id=...
  ↓ (User approuve)
GET /connect/google/check (callback Google)
  ↓ (Intercepté par GoogleOAuthAuthenticator)
GoogleOAuthAuthenticator::authenticate()
  ├─ Récupère token depuis Google
  ├─ Appelle authHandler avec email + googleId
  └─ Login user
  ↓
Dashboard redirect ✅
```

---

## Files changés Phase 2

**Packages installées:**
- `knpuniversity/oauth2-client-bundle:2.*`
- `league/oauth2-google:4.*`

**Fichiers créés/modifiés:**
- `config/packages/knpu_oauth2_client.yaml` ← Config OAuth
- `config/bundles.php` ← Enregistré le bundle
- `config/packages/security.yaml` ← Added GoogleOAuthAuthenticator
- `src/Security/GoogleOAuthAuthenticator.php` ← Nouveau authenticator
- `src/UI/Http/Controller/Auth/OAuthController.php` ← Rewrite vrai flow
- `.env` ← Template credentials
- `.env.test` ← Test credentials
- `tests/UI/Http/Controller/Auth/OAuthControllerTest.php` ← Adapted tests

**NOT modifié (réusable):**
- `src/Application/Auth/AuthenticateWithGoogle/` ← Core logic still perfect
- `src/Infrastructure/Security/OAuth/` ← PasswordGenerator still used

---

## Procédure dev local

```bash
# 1. Copier .env.local.example (s'il y a)
cp .env.local.example .env.local

# 2. Ajouter tes credentials
# GOOGLE_CLIENT_ID=...
# GOOGLE_CLIENT_SECRET=...

# 3. Clear cache
php bin/console cache:clear

# 4. Tester
php bin/phpunit

# 5. Lancer l'app
symfony server:start
```

---

## Procédure production

1. Dans ton `.env` distant (secrets management):
   ```
   GOOGLE_CLIENT_ID=prod-client-id
   GOOGLE_CLIENT_SECRET=prod-client-secret
   ```

2. Assure-toi que Google Console a ton domaine en "Authorized redirect URIs":
   ```
   https://tonapp.fr/connect/google/check
   ```

3. Déploie normalement

---

## Notes

- **Phase 1** testait mécaniquement la logique métier (handler unitaires) ✅
- **Phase 2** branche le vrai OAuth provider ✅
- **Tests:** Restent simples (pas de mock Google API) car KnpU gère pour nous
- **Sécurité:** Client Secret jamais exposé client-side (serverside only) ✅
- **Multi-provider:** Architecture extensible pour Facebook/GitHub plus tard

---

## Étapes suivantes recommandées

1. ✅ **Finir OAuth Google Phase 2** (tu l'as!)
2. 🚀 **Ajouter UI** — Bouton "Connect with Google" sur `/login`
3. 🛠 **Polishs produit** — Messages UX, navigation fluide
4. 📡 **API JSON V1** — Expose tes groupes/sessions pour SPA/mobile futur

---

Besoin d'aide pour configurer Google Cloud ou tester le flow?
