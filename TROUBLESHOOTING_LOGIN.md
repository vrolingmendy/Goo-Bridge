# Guide de dépannage connexion

## ✓ Tests effectués - Tout fonctionne côté serveur

1. **Base de données** : comptes admin vérifiés
   - Utilisez les identifiants administrateur définis dans la base.

2. **Connexion HTTP** : 302 Found → /admin/dashboard.php ✓

3. **Session** : Cookie créé et dashboard accessible ✓

## 🔧 Solutions pour vous connecter

### Solution 1 : Vider le cache et les cookies (RECOMMANDÉ)
Dans votre navigateur :
1. Appuyez sur `Cmd + Shift + Delete` (Mac) ou `Ctrl + Shift + Delete` (Windows)
2. Cochez "Cookies" et "Cache"
3. Cliquez sur "Effacer"
4. Fermez complètement le navigateur et rouvrez-le
5. Allez sur https://goo-bridge.com/login.php

### Solution 2 : Mode navigation privée
1. Ouvrez une fenêtre de navigation privée (Cmd+Shift+N sur Chrome/Edge)
2. Allez sur https://goo-bridge.com/login.php
3. Connectez-vous avec vos identifiants administrateur.

### Solution 3 : Vérifier l'URL exacte
Assurez-vous d'utiliser EXACTEMENT cette URL :
```
https://goo-bridge.com/login.php
```

Ne pas utiliser les anciennes URLs locales :
- http://127.0.0.1/bridge/login.php
- http://localhost:8080/bridge/login.php
- http://localhost/bridge/login.php

### Solution 4 : Tester avec curl (pour confirmer que ça marche)
Ouvrez le Terminal et exécutez:
```bash
curl -L -c /tmp/cookies.txt -b /tmp/cookies.txt \
  -d "email=votre-email-admin" \
  -d "password=votre-mot-de-passe" \
  https://goo-bridge.com/login.php
```

## 📋 Comptes disponibles

Utilisez uniquement les comptes administrateur créés dans la base de données.
Ne conservez jamais de mot de passe en clair dans ce fichier.

## ❓ Si ça ne marche toujours pas

Envoyez-moi une capture d'écran montrant :
1. L'URL dans la barre d'adresse
2. Le message d'erreur exact (en rouge) si vous en voyez un
3. La console JavaScript (F12 → Console)
