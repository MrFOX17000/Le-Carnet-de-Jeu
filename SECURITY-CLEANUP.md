# Plan de nettoyage du secret expose sur GitHub

## ✅ Etape 1: Proteger .gitignore (FAIT)
.env et .env.test sont maintenant dans .gitignore.

## 🔑 Etape 2: Regenerer APP_SECRET (URGENT)
Nouvelle cle generee: `52f68bdb8e3c3ee0fb4a31922248fa9282494c1c38a30dbf212a443819b4892c`

### Action immediate:
1. Ouvre `.env.local`
2. Remplace la ligne APP_SECRET par:
   ```
   APP_SECRET=52f68bdb8e3c3ee0fb4a31922248fa9282494c1c38a30dbf212a443819b4892c
   ```
3. Redémarre le serveur Symfony

## 🧹 Etape 3: Nettoyer l'historique Git

### Option A: Nettoyage rapide (RECOMMANDE pour ce projet)
Vu que tu n'as que 2 commits et que le projet est nouveau, le plus simple:

```bash
cd c:\dev\carnet-de-jeu

# Sauvegarde (optionnel)
git branch backup-avant-nettoyage

# Supprime .git et repars propre
Remove-Item -Recurse -Force .git
git init
git add .
git commit -m "Initial commit - clean history"
git branch -M master
git remote add origin git@github.com:MrFOX17000/Le-Carnet-de-Jeu.git
git push -u --force origin master
```

### Option B: BFG Repo-Cleaner (si tu veux garder l'historique)
1. Telecharge BFG: https://rtyley.github.io/bfg-repo-cleaner/
2. Execute:
```bash
git clone --mirror git@github.com:MrFOX17000/Le-Carnet-de-Jeu.git repo-backup.git
java -jar bfg.jar --delete-files .env repo-backup.git
java -jar bfg.jar --delete-files .env.dev repo-backup.git
java -jar bfg.jar --delete-files .env.test repo-backup.git
cd repo-backup.git
git reflog expire --expire=now --all
git gc --prune=now --aggressive
git push --force
```

## ✅ Etape 4: Verification
- Va sur https://github.com/MrFOX17000/Le-Carnet-de-Jeu/commits/master
- Verifie que `.env` n'apparait plus dans aucun commit
- GitGuardian devrait arreter les alertes sous 24-48h

## 📝 Pour l'avenir
- **TOUJOURS** ajouter .env dans .gitignore AVANT le premier commit
- Utiliser .env.local pour les secrets locaux (jamais commite)
- En prod: utiliser symfony/secrets ou variables d'environnement serveur

