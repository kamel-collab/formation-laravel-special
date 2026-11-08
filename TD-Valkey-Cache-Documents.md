# TD progressif : Valkey, cache et invalidation

**Durée : 100 minutes** en trois parties : connexion à Valkey (30 min), mise en cache d’une lecture (40 min), puis invalidation (30 min). Avancez étape par étape et vérifiez le résultat avant de continuer. La branche `jour4-valkey-cache-documents` contient la correction complète.

## Avant de commencer

Dans VS Code, ouvrez le dossier **Laravel** qui contient `compose.yaml` et `artisan`. Dans **Terminal > Nouveau terminal**, choisissez PowerShell :

```powershell
git branch --show-current
git status
docker compose ps
```

**À vérifier :** partez de `jour3-securite-documents` ou de votre travail terminé du jour 3. Docker Desktop est démarré. PostgreSQL et Laravel sont déjà configurés, les migrations de sécurité sont réalisées et les comptes fictifs sont prêts. Utilisez uniquement la base locale de formation. Sauvegardez vos modifications avant de changer de branche ; si Git refuse, demandez au formateur et ne supprimez pas de fichiers.

Dans ce TD, ne relancez pas les migrations ni les seeders : ils ne sont pas nécessaires si le jour 3 est prêt. Ne recréez pas `.env`, ne régénérez pas `APP_KEY` et ne supprimez pas les volumes Docker. Les essais utilisent des documents et comptes fictifs.

Le projet interface peut rester sur `jour4-interface-api-securisee`. Pour voir précisément les statuts et les données, nous utilisons ici **Postman**, comme au jour 3.

---

# Partie 1 : démarrer Valkey et le connecter à Laravel — 30 min

## 1. Ajouter le service Docker

Ouvrez `compose.yaml`. Sous `services:`, repérez le bloc `pgadmin:` et collez ce nouveau service **juste avant**, au même niveau d’indentation que `app:`, `db:` et `pgadmin:` :

```yaml
  valkey:
    image: valkey/valkey:9.0.7-alpine
    # Cache temporaire : les données sont reconstruites depuis PostgreSQL.
    command: ["valkey-server", "--save", "", "--appendonly", "no"]
    healthcheck:
      test: ["CMD", "valkey-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 12
```

Dans le bloc `app:`, trouvez `depends_on:` et ajoutez les deux lignes `valkey:` et `condition:` ci-dessous. **Conservez aussi la dépendance `db` existante** :

```yaml
    depends_on:
      db:
        condition: service_healthy
      valkey:
        condition: service_healthy
```

Enregistrez. Ce service ne publie aucun port sur Windows : Laravel le contacte sur le réseau Docker par le nom `valkey`. Ici Valkey est un cache temporaire sans persistance configurée ; après recréation du conteneur, le cache sera reconstruit. PostgreSQL et les PDF privés restent les données de référence.

**À vérifier :** demandez à Docker de vérifier le fichier, puis démarrez les services :

```powershell
docker compose config --quiet
docker compose up -d
docker compose ps
docker compose exec valkey valkey-cli ping
```

La première commande ne doit pas afficher d’erreur. `valkey` doit apparaître démarré et la dernière commande doit répondre **PONG**. Le premier démarrage peut télécharger l’image.

### À vous

Expliquez à votre voisin : pourquoi `valkey` est-il un service séparé de `db` ? Quelle commande a montré que Valkey répond ?

## 2. Installer le client PHP

Laravel utilise un client compatible Redis pour communiquer avec Valkey. Installez Predis dans le conteneur `app` :

```powershell
docker compose exec app composer require predis/predis:^3.2 --no-interaction --no-progress
```

Composer met à jour `composer.json`, `composer.lock` et les dépendances du volume Composer de ce projet. Cette étape peut nécessiter Internet. Si elle échoue, lisez l’erreur avec le formateur avant de continuer.

**À vérifier :**

```powershell
docker compose exec app composer show predis/predis
```

Le nom et une version de Predis doivent apparaître. La correction a été préparée avec la version verrouillée par son fichier `composer.lock`.

## 3. Configurer Laravel

Dans VS Code, ouvrez votre fichier **`.env` existant**. Modifiez seulement les lignes suivantes, sans toucher aux autres paramètres :

```dotenv
CACHE_STORE=redis
REDIS_CLIENT=predis
REDIS_HOST=valkey
REDIS_PASSWORD=null
REDIS_PORT=6379
```

`valkey` est le nom du service Docker. `redis` est le nom du pilote prévu par Laravel ; il peut parler à Valkey grâce à leur compatibilité. Le mot « Redis » dans la configuration ne signifie pas qu’un second serveur doit être installé. Si Valkey est arrêté, cette correction ne prévoit pas de bascule automatique : les opérations utilisant le cache peuvent échouer jusqu’à son redémarrage. Une écriture PostgreSQL peut avoir réussi avant une erreur d’invalidation ; vérifiez le document avant de réessayer pour éviter un doublon.

Pour qu’une **nouvelle** installation de formation ait les mêmes valeurs par défaut, appliquez les mêmes changements dans `.env.example` : remplacez `CACHE_STORE=file`, `REDIS_CLIENT=phpredis` et `REDIS_HOST=127.0.0.1` par les trois premières lignes ci-dessus. Ne copiez pas `.env` dans Git : il contient la configuration propre au poste.

Si Laravel avait une configuration mise en cache, cette commande retire seulement le fichier de configuration compilée ; elle ne vide ni PostgreSQL ni les données Valkey :

```powershell
docker compose exec app php artisan config:clear
docker compose restart app
docker compose exec app php artisan config:show cache.default
```

**À vérifier :** la dernière commande affiche `redis`. Le redémarrage de `app` lui fait relire la configuration. La limitation de connexion du jour 3 utilise maintenant ce cache par défaut : ses anciens compteurs enregistrés avec le pilote précédent ne sont pas transférés.

## 4. Tester un aller-retour Laravel / Valkey

Ouvrez Tinker :

```powershell
docker compose exec app php artisan tinker
```

Dans l’invite Tinker `>`, exécutez les lignes suivantes une par une :

```php
\Illuminate\Support\Facades\Cache::put('td4:bonjour', 'Bonjour Valkey', 60);
\Illuminate\Support\Facades\Cache::get('td4:bonjour');
\Illuminate\Support\Facades\Cache::forget('td4:bonjour');
exit
```

**À vérifier :** la deuxième ligne affiche `"Bonjour Valkey"`. La troisième supprime seulement cette clé de démonstration. Si vous obtenez une erreur de connexion, vérifiez le service, `REDIS_HOST`, `REDIS_CLIENT` et le redémarrage de `app`.

### À vous

Arrêtez-vous ici : que représentent `td4:bonjour`, `Bonjour Valkey` et `60` ? Quel composant a conservé la valeur pendant l’essai ?

---

# Partie 2 : mettre en cache la liste personnelle — 40 min

## 5. Préparer un document dans Postman

Connectez Amina avec `POST http://localhost:8088/api/login`, en choisissant **Body > raw > JSON** et en ajoutant `Accept: application/json`. Utilisez les comptes de formation du jour 3. Dans une requête `POST http://localhost:8088/api/documents`, sélectionnez **Authorization > Bearer Token**, collez le token d’Amina et envoyez :

```json
{ "title": "Document cache Amina", "category": "Formation" }
```

**À vérifier :** réponse **201**. Notez l’`id` du document et son `user_id` ; cet identifiant utilisateur permettra de reconnaître sa clé de cache. Les identifiants peuvent être différents de ceux montrés en cours.

## 6. Adapter la lecture

Ouvrez `app/Http/Controllers/DocumentController.php`. Ajoutez cette ligne parmi les autres `use` :

```php
use Illuminate\Support\Facades\Cache;
```

Remplacez uniquement la méthode `index` par :

```php
    public function index(Request $request): JsonResponse
    {
        $key = 'documents:user:'.$request->user()->id;
        $documents = Cache::remember($key, now()->addMinutes(5),
            fn () => $request->user()->documents()->get()->toArray());

        return response()->json($documents);
    }
```

Lisez le code : la clé contient l’identifiant de l’utilisateur connecté. `remember` cherche cette clé ; si elle manque, la fonction lit PostgreSQL et conserve le **tableau** obtenu pendant cinq minutes. `toArray()` conserve un format de données simple pour la sérialisation du cache. Laravel renvoie ensuite la réponse JSON.

## 7. Observer le cache

1. Dans Postman, envoyez `GET http://localhost:8088/api/documents` avec le token d’Amina.
2. Dans PowerShell, listez les clés de l’exercice :

```powershell
docker compose exec valkey valkey-cli -n 1 --scan --pattern '*documents:user:*'
```

**À vérifier :** une clé se termine par `documents:user:` suivi du `user_id` noté précédemment. Laravel ajoute un préfixe avant cette partie. `-n 1` interroge la base logique utilisée par défaut pour le cache dans ce projet ; si `REDIS_CACHE_DB` a été personnalisé, demandez au formateur de vérifier sa valeur.

3. Copiez **la clé complète affichée**, préfixe compris, dans la commande suivante à la place du texte entre apostrophes :

```powershell
docker compose exec valkey valkey-cli -n 1 TTL 'COLLER_LA_CLE_COMPLETE_ICI'
```

**À vérifier :** le nombre de secondes est positif et inférieur ou égal à 300. `-2` signifie que la clé n’existe plus : renvoyez le GET. `-1` signifie qu’aucune expiration n’est définie : vérifiez le code.

4. Renvoyez le même GET avec le token d’Amina. La liste reste identique et la clé existe toujours. Le temps restant ne repart pas automatiquement à 300 : la copie existante est réutilisée.
5. Connectez Karim dans Postman, puis envoyez la même requête avec **son** token. **À vérifier :** sa liste ne contient pas le document d’Amina. La commande `--scan` affiche une autre clé, se terminant par l’identifiant de Karim. Une liste vide est possible.

### À vous

Avant de passer à la suite, répondez : pourquoi une seule clé `documents` pour tout le monde serait-elle dangereuse ? Si la clé expire, que fera la prochaine lecture ?

---

# Partie 3 : invalider la liste après une écriture — 30 min

## 8. Ajouter l’invalidation dans le contrôleur

Après chaque modification réussie de la liste, nous supprimons **uniquement la clé de l’utilisateur concerné**. La prochaine lecture la reconstruira.

Dans `DocumentController.php`, ajoutez cette ligne **juste après** la création du document dans `store` :

```php
        Cache::forget('documents:user:'.$request->user()->id);
```

Ajoutez la même ligne juste après `$document->update($data);` dans `update`, puis juste après la fin de `DB::transaction(...)` dans `destroy`, avant `return response()->noContent();`. Elle ne doit pas se trouver dans la transaction de suppression.

L’ajout d’un PDF modifie également le document. Dans `app/Http/Controllers/DocumentFileController.php`, ajoutez :

```php
use Illuminate\Support\Facades\Cache;
```

Remplacez uniquement la méthode `store` de ce contrôleur par le code ci-dessous ; conservez la méthode `download` existante :

```php
    public function store(DocumentFileRequest $request, string $document): JsonResponse
    {
        $path = null;

        try {
            $response = DB::transaction(function () use ($request, $document, &$path): JsonResponse {
                $record = $request->user()->documents()->lockForUpdate()->findOrFail($document);

                if ($record->file_path !== null) {
                    return response()->json(['message' => 'Ce document possède déjà un fichier.'], 409);
                }

                $path = $request->file('file')->store('documents', 'local');
                if ($path === false) {
                    abort(500, 'Impossible de stocker le fichier.');
                }

                $record->file_path = $path;
                $record->save();

                return response()->json(['message' => 'Fichier enregistré.'], 201);
            });
        } catch (Throwable $exception) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        if ($response->getStatusCode() === 201) {
            Cache::forget('documents:user:'.$request->user()->id);
        }

        return $response;
    }
```

La clé n’est supprimée qu’après un envoi réussi : un conflit **409** ne modifie pas le document. La transaction de suppression du document et son nettoyage du fichier privé restent en place.

## 9. Tester modification et suppression

1. Dans Postman, utilisez à nouveau le token d’Amina. Envoyez `GET /api/documents`, puis la commande `--scan` de l’étape 7. **À vérifier :** sa clé est présente.
2. Choisissez **PUT** pour `http://localhost:8088/api/documents/ID`, en remplaçant `ID` par celui du document créé. Dans **Body > raw > JSON**, envoyez :

```json
{ "title": "Document Amina modifié", "category": "Projet" }
```

**À vérifier :** réponse **200**. Relancez `--scan` : la clé d’Amina a disparu. La clé de Karim, si elle n’a pas expiré, est conservée.

3. Renvoyez le GET avec le token d’Amina. **À vérifier :** le titre modifié apparaît et la clé d’Amina est recréée. Son TTL est à nouveau proche de 300 secondes.
4. Avec le même token, envoyez **DELETE** sur `http://localhost:8088/api/documents/ID`. **À vérifier :** réponse **204** et clé d’Amina absente. Renvoyez le GET : le document supprimé n’apparaît plus et la liste est de nouveau mise en cache.
5. Pour vérifier la création, avec une liste déjà en cache, créez un autre document par POST : la clé doit disparaître ; le GET suivant affiche le nouveau document.

Attention : ces opérations modifient réellement les documents de la **base de formation**. Ne supprimez que les documents fictifs que vous venez de créer. N’utilisez pas `FLUSHALL`, `FLUSHDB` ni `docker compose down -v`.

### À vous

Expliquez pourquoi on supprime la copie en cache **après** la réussite de l’écriture dans PostgreSQL. Que se passerait-il si l’on oubliait l’invalidation et que la durée de vie était de cinq minutes ?

## Vérifications finales et difficultés

| Situation | Vérification |
| --- | --- |
| Pas de `PONG` | `docker compose ps`, nom du service et indentation YAML. |
| `Class Predis... not found` | Vérifiez `composer show predis/predis` dans `app` et l’installation dans son volume. |
| Connexion refusée à Valkey | Vérifiez `REDIS_HOST=valkey`, l’état de `valkey`, `config:clear` et le redémarrage de `app`. |
| Aucune clé | Vérifiez un GET authentifié, `CACHE_STORE=redis`, la base logique `-n 1` et le `user_id`. |
| Liste d’un autre utilisateur | Arrêtez l’essai et vérifiez immédiatement le token et la clé contenant `user_id`. |
| Ancien titre après PUT | Vérifiez `Cache::forget` après `update` et utilisez le même utilisateur pour les requêtes. |
| 401 ou 429 | Reconnectez-vous si le token a expiré ; après trop de connexions, patientez une minute. |

La durée de vie limite une copie ancienne mais ne remplace pas l’invalidation. Cet exemple explique le principe dans une application locale ; des écritures et lectures simultanées peuvent nécessiter une stratégie de cohérence plus élaborée en production.

## Comparer avec la correction

Après avoir enregistré votre travail et récupéré la branche si nécessaire, comparez sans changer de branche :

```powershell
git diff jour4-valkey-cache-documents -- compose.yaml .env.example composer.json app/Http/Controllers/DocumentController.php app/Http/Controllers/DocumentFileController.php
```

La branche de correction contient aussi des tests automatiques. Ne changez pas de branche sans avoir d’abord sauvegardé vos fichiers et vérifié `git status`.
