# Jour 5 — Tester l’API Laravel avec k6

**Départ :** branche Laravel `jour4-rate-limiting-documents`. **Correction :** `jour5-k6-performance-documents`.

Ce TD utilise Docker Desktop, l’API protégée, PostgreSQL et Valkey. Toutes les commandes ci-dessous sont à exécuter dans PowerShell, à la racine du projet Laravel. Travaillez sur votre application locale de formation. Le script utilise un compte de formation et n’enregistre pas de documents.

## 1. Démarrer k6 et vérifier son fonctionnement — 20 min

Vérifiez que Docker Desktop fonctionne, puis démarrez l’application :

```powershell
docker compose up -d
docker compose exec app composer install
```

Si le projet n’a pas encore été configuré, suivez d’abord `TD-Demarrage-Docker.md`, puis `TD-Valkey-Cache-Documents.md` : `.env`, clé Laravel, migrations et Valkey doivent être prêts. Les comptes de formation sont créés localement avec :

```powershell
docker compose exec app php artisan db:seed --class=FormationUsersSeeder
docker compose exec valkey valkey-cli ping
docker run --rm grafana/k6:1.4.2 version
```

Le dernier résultat doit afficher une version de k6 et Valkey doit répondre `PONG`. Docker téléchargera l’image officielle k6 si nécessaire. On n’installe pas de paquet Composer pour k6 : c’est un outil distinct de Laravel.

## 2. Cours : utilisateurs, durée, scénario et HTTP — 15 min

Un utilisateur virtuel (VU) exécute le scénario en boucle pendant la durée choisie. Le scénario indique les requêtes, leurs données et les vérifications. Dix VU ne signifient pas dix requêtes par seconde. `GET` lit une ressource ; `POST` en crée une ou envoie un formulaire. Pour notre liste protégée, `GET /api/documents` exige un token Sanctum. Une réponse `200` indique une lecture réussie, `401` un accès non authentifié et `429` le dépassement de la limite.

## 3. Premier scénario pour l’API protégée — 35 min

Connectez-vous avec le compte local créé par le seeder, sans afficher son token. Il reste dans la session PowerShell ; ne le mettez ni dans un fichier suivi par Git, ni dans une capture d’écran.

```powershell
$body = @{ email = 'amina@example.test'; password = 'Formation123!' } | ConvertTo-Json
$login = Invoke-RestMethod -Method Post -Uri 'http://localhost:8088/api/login' -ContentType 'application/json' -Body $body
$env:K6_TOKEN = $login.token
```

Créez le dossier `k6` et le fichier `k6/documents.js`. Commencez avec ce scénario de lecture :

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = { vus: 1, duration: '3s' };

export default function () {
  const response = http.get('http://app:8080/api/documents', {
    headers: { Authorization: `Bearer ${__ENV.K6_TOKEN}`, Accept: 'application/json' },
  });
  check(response, { 'lecture réussie (200)': (r) => r.status === 200 });
  sleep(1);
}
```

Lancez k6 dans un conteneur connecté au réseau du projet :

```powershell
docker run --rm --network formation-laravel_default -v "${PWD}:/scripts" -e K6_TOKEN grafana/k6:1.4.2 run /scripts/k6/documents.js
```

`app:8080` est le nom Docker du service Laravel, accessible depuis le conteneur k6. Depuis le navigateur de Windows, l’adresse est `localhost:8088`. Le réseau ci-dessus vient du nom `formation-laravel` dans `compose.yaml`. Si votre session PowerShell est fermée, reconnectez-vous pour recréer la variable. Si vous dépassez la limite de connexion, attendez son expiration.

## 4. Lire les résultats — 20 min

- `http_req_duration` : durée de réponse, en millisecondes. `avg` est la moyenne et `p(95)` le temps sous lequel se situent 95 % des mesures.
- `http_reqs` : nombre total de requêtes et débit moyen par seconde.
- `http_req_failed` : proportion de requêtes considérées comme échouées par k6. Par défaut, une réponse 429 compte comme échec HTTP, même si elle est attendue dans notre TD.
- `checks` : résultats des vérifications écrites dans le scénario. Un check raté seul ne provoque pas nécessairement un code de sortie non nul ; un seuil (`threshold`) permet de définir une condition d’échec du test.

Relevez ces valeurs dans un tableau. Ne comparez les temps de lecture réussie qu’avec les réponses `200` : une réponse `429` n’a pas effectué la même opération.

### Voir le tableau de bord local

Pour avoir le temps d’ouvrir les graphiques, réglez temporairement `duration: '20s'` dans votre scénario, puis lancez :

```powershell
docker run --rm --network formation-laravel_default -p 127.0.0.1:5665:5665 -v "${PWD}:/scripts" -e K6_TOKEN -e K6_WEB_DASHBOARD=true -e K6_WEB_DASHBOARD_HOST=0.0.0.0 -e K6_WEB_DASHBOARD_PERIOD=1s grafana/k6:1.4.2 run /scripts/k6/documents.js
```

Pendant l’exécution, ouvrez `http://localhost:5665` dans le navigateur. Le tableau de bord s’arrête avec le test. Comme il dure 20 secondes et utilise un seul compte, de nombreuses réponses `429` sont attendues. Remettez ensuite `duration: '3s'` pour les comparaisons suivantes.

## 5. Faire varier les utilisateurs — 45 min

Après expiration de la limite de la liste, relancez avec 1 utilisateur et 3 secondes, puis 2 utilisateurs et 3 secondes, puis 5 utilisateurs et 3 secondes. Modifiez `vus` et `duration` dans le fichier. Notez le débit, la durée et le nombre de réponses `429`. Attendez au moins 60 secondes entre les essais pour laisser expirer la fenêtre commencée par la première requête.

Tous les VU utilisent ici le **même token** : Laravel les reconnaît donc comme le même compte et ils partagent sa limite de cinq lectures par minute. Cela simule plusieurs clients utilisant ce compte, pas cinq comptes différents. Les cinq utilisateurs démarrent en parallèle, sans garantie d’un envoi strictement simultané.

## 6. Comparer sans cache et avec cache — 40 min

La correction propose un interrupteur pédagogique `DOCUMENTS_CACHE_ENABLED`, séparé de `CACHE_STORE`. Réalisez d’abord le TD :

1. Dans `config/cache.php`, ajoutez une option `documents_enabled` lue depuis `DOCUMENTS_CACHE_ENABLED`, vraie par défaut.
2. Dans `DocumentController::index`, si cette option est fausse, lisez directement les documents de l’utilisateur ; sinon, conservez le `Cache::remember` existant.
3. Dans `.env`, mettez `DOCUMENTS_CACHE_ENABLED=false`, puis lancez `docker compose exec app php artisan config:clear`. Ne changez pas `CACHE_STORE=redis` : Valkey continue à stocker la limitation.
4. Après expiration du quota, lancez le scénario à 1 VU pendant 3 secondes et relevez les mesures des lectures réussies.
5. Dans `.env`, mettez `DOCUMENTS_CACHE_ENABLED=true`, relancez `config:clear`, attendez la fin du quota et lancez un premier essai pour remplir le cache. Après expiration du quota, refaites la même mesure à chaud.

Gardez les mêmes données, la même charge et la même machine. La première requête à froid remplit le cache ; les suivantes peuvent le réutiliser. Trois requêtes sont trop peu pour prouver un gain : l’exercice illustre le mécanisme, les temps locaux varient. La limite à cinq lectures réduit volontairement la taille de l’échantillon.

## 7. Observer la limitation HTTP 429 — 25 min

Après au moins 60 secondes sans lecture avec ce compte, remplacez temporairement `options` par `{ vus: 1, iterations: 6 }` et retirez `sleep(1)`. Ajoutez `console.log(response.status)` dans la fonction pour observer chaque code. Relancez : on attend cinq `200`, puis un `429`, si aucun autre client n’a consommé le quota. Une réponse 429 indique que la protection fonctionne. Après environ une minute, une nouvelle lecture redevient possible.

La correction contient un script paramétrable pour rejouer les lectures et le test de limite sans modifier le fichier à chaque fois. Pour la consulter, sauvegardez d’abord votre travail puis changez de branche :

```powershell
git status
git switch jour5-k6-performance-documents
```

Pour un serveur ou un VPS, demandez d’abord l’autorisation et adaptez l’adresse et la charge à l’environnement prévu. Ne lancez pas ce TD contre un service public ou des données réelles.
