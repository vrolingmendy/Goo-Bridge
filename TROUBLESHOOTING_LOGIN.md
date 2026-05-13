# Guide de dépannage connexion

## ✓ Tests effectués - Tout fonctionne côté serveur

1. **Base de données** : 2 comptes admin trouvés
   - vrolingmendy0@gmail.com (ID: 1) - Mot de passe: Passer123 ✓
   - vroling@gmail.com (ID: 2)

2. **Connexion HTTP** : 302 Found → /bridge/admin/dashboard.php ✓

3. **Session** : Cookie créé et dashboard accessible ✓

## 🔧 Solutions pour vous connecter

### Solution 1 : Vider le cache et les cookies (RECOMMANDÉ)
Dans votre navigateur :
1. Appuyez sur `Cmd + Shift + Delete` (Mac) ou `Ctrl + Shift + Delete` (Windows)
2. Cochez "Cookies" et "Cache"
3. Cliquez sur "Effacer"
4. Fermez complètement le navigateur et rouvrez-le
5. Allez sur http://localhost/bridge/login.php

### Solution 2 : Mode navigation privée
1. Ouvrez une fenêtre de navigation privée (Cmd+Shift+N sur Chrome/Edge)
2. Allez sur http://localhost/bridge/login.php
3. Connectez-vous avec:
   - Email: vrolingmendy0@gmail.com
   - Mot de passe: Passer123

### Solution 3 : Vérifier l'URL exacte
Assurez-vous d'utiliser EXACTEMENT cette URL :
```
http://localhost/bridge/login.php
```

Ne pas utiliser:
- http://127.0.0.1/bridge/login.php
- http://localhost:8080/bridge/login.php
- Sans le /bridge/ si votre projet est dans ce dossier

### Solution 4 : Tester avec curl (pour confirmer que ça marche)
Ouvrez le Terminal et exécutez:
```bash
curl -L -c /tmp/cookies.txt -b /tmp/cookies.txt \
  -d "email=vrolingmendy0@gmail.com" \
  -d "password=Passer123" \
  http://localhost/bridge/login.php
```

## 📋 Comptes disponibles

Vous avez 2 comptes admin :
1. vrolingmendy0@gmail.com / Passer123 (compte initial)
2. vroling@gmail.com / (le mot de passe que vous avez défini à l'inscription)

## ❓ Si ça ne marche toujours pas

Envoyez-moi une capture d'écran montrant :
1. L'URL dans la barre d'adresse
2. Le message d'erreur exact (en rouge) si vous en voyez un
3. La console JavaScript (F12 → Console)
