# Application Laravel et PostgreSQL

Correction du jour 3 pour le fil rouge de gestion de documents. L'API Laravel utilise Sanctum, limite les documents à leur propriétaire et permet d'associer un PDF privé à un document.

Pour suivre les étapes détaillées avec les vérifications et les solutions aux problèmes courants, consultez le [guide de travaux dirigés](TD-Demarrage-Docker.md).

Après la découverte de Laravel, suivez le [TD sur les documents, les migrations et le modèle](TD-Documents-Migration-Modele.md) depuis la branche `jour2-decouverte-laravel`.

Pour construire ensuite les routes et le contrôleur de l'API, suivez le [TD API CRUD des documents](TD-API-CRUD-Documents.md) sur la branche `jour2-postgresql-migrations-modeles`. Il explique également la validation côté serveur, les réponses HTTP et la différence avec les vérifications du navigateur. La branche `jour2-api-crud-documents` contient la correction de cette étape.

Au jour 3, le [TD Form Request des documents](TD-Form-Request-Documents.md) part de `jour2-api-crud-documents` et utilise `jour3-form-requests` comme correction.

Le [TD sécurité des documents et fichiers](TD-Securite-Documents.md) part ensuite de `jour3-form-requests`. Sa correction se trouve sur `jour3-securite-documents` et protège les routes des documents ; l'interface Fetch du jour 2 devra être adaptée pour utiliser cette API protégée.

## Prérequis

- Docker Desktop avec le moteur de conteneurs Linux et Docker Compose ; sous Windows, WSL 2 doit être fonctionnel.
- Git pour cloner le dépôt et VS Code pour modifier les fichiers.
- Les ports **8088** et **5050** disponibles sur la machine.
- Un accès aux images Docker et aux dépendances Composer lors du premier démarrage, ou un environnement hors ligne préparé et testé à l'avance.

PHP, Composer et PostgreSQL sont exécutés dans Docker. Ils n'ont pas besoin d'être installés sur Windows. Dans WSL 2, placer le projet dans le système de fichiers Linux peut améliorer les performances ; VS Code peut l'ouvrir avec l'intégration WSL.

Laravel Boost est inclus comme dépendance de développement conformément aux instructions `AGENTS.md` générées par Laravel. Il n'est pas nécessaire pour les exercices du jour 2.

## Cloner le projet

Dans le dossier où vous souhaitez placer le projet :

```bash
git clone --branch starter https://github.com/kamel-collab/formation-laravel-special.git app-laravel-postgresql
cd app-laravel-postgresql
```

Le dépôt est public et la branche `starter` est le point de départ de la formation. Si le projet est déjà présent sur votre poste, ouvrez son dossier au lieu de le cloner à nouveau.

## Premier démarrage après clonage

Dans un terminal ouvert dans ce dossier :

```bash
docker compose up --build -d
docker compose exec app composer install
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
```

Ces étapes de copie du fichier `.env` et de génération de clé sont à effectuer **une seule fois sur un nouveau projet**. Ne les répétez pas sur une installation existante : elles remplaceraient sa configuration et sa clé. Le fichier `.env` est local et ignoré par Git. Les identifiants présents dans `.env.example` et `compose.yaml` servent uniquement à cette formation en local.

Ouvrez ensuite <http://localhost:8088>. Le premier lancement peut prendre plusieurs minutes pour télécharger les images et les dépendances.

Le conteneur `app` attend que `vendor/autoload.php` existe avant de démarrer le serveur Laravel. Pendant cette attente, `docker compose exec app composer install` reste utilisable. Composer utilise le fichier `composer.lock` fourni pour installer les versions prévues.

Les dépendances `vendor` sont conservées dans le volume Docker `composer_vendor`, accessible dans le conteneur à `/var/www/html/vendor`. Le code du projet reste dans le dossier ouvert avec VS Code. Cette organisation réduit les accès aux nombreux fichiers de dépendances sur le disque Windows. Le dossier `vendor` de Windows n'est pas utilisé par le conteneur et ne reflète pas nécessairement le contenu du volume.

Après un changement de branche qui modifie `composer.lock`, synchronisez les dépendances avec `docker compose exec app composer install`. Si le volume est nouveau ou vide, lancez également cette commande après le démarrage de `app`.

## Première route JSON

L'exemple de la séquence « Découverte de Laravel » est accessible dans le navigateur à <http://localhost:8088/api/exemples/document> :

```json
{"id":12,"title":"Contrat de formation","category":"Administratif"}
```

La route `GET` est définie dans `routes/api.php` et appelle la méthode `show` de `DocumentExempleController`. Le fichier `bootstrap/app.php` charge les routes API ; Laravel leur ajoute automatiquement le préfixe `/api`.

Les informations sont fictives et écrites dans le contrôleur. Cette route ne lit ni n'enregistre de données dans PostgreSQL et ne téléverse pas de fichier. La configuration API de cet exercice ne met pas en place d'authentification.

Pour vérifier son enregistrement :

```bash
docker compose exec app php artisan route:list --path=api
```

## Base de données et migrations

Dans le réseau Docker, Laravel se connecte à PostgreSQL grâce au nom de service `db` sur le port interne 5432. PostgreSQL n'est pas exposé sur un port Windows. Le volume Docker `postgres_data` conserve ses données après un arrêt ou une recréation ordinaire des conteneurs.

pgAdmin permet de consulter la base dans le navigateur à <http://localhost:5050>. Le compte pgAdmin de démonstration est `formation@example.com`, avec le mot de passe `formation_local`. Lors de la première connexion au serveur PostgreSQL dans pgAdmin, utilisez l'hôte `db`, le port `5432`, la base `formation_documents`, l'utilisateur `formation` et le mot de passe `formation_local`. Le [guide de démarrage](TD-Demarrage-Docker.md) détaille les étapes. Ces identifiants sont réservés à la formation locale.

La migration `create_documents_table` décrit une table avec :

- `id` : identifiant du document ;
- `title` : titre, 100 caractères maximum ;
- `category` : catégorie, 50 caractères maximum ;
- `created_at` et `updated_at` : dates gérées par Eloquent.

Les migrations du jour 3 ajoutent `user_id`, qui peut être vide pour conserver les documents antérieurs, et `file_path`, qui peut être vide lorsqu'aucun fichier n'a encore été associé. Les anciens documents sans propriétaire ne figurent dans aucune liste privée.

Le modèle `App\Models\Document` représente cette table et autorise l'affectation de `title` et `category`. Le chemin du fichier reste interne et n'est pas exposé dans les réponses JSON.

Les migrations ne sont pas exécutées automatiquement pour permettre de les découvrir pendant le cours. Après leur présentation, lancez :

```bash
docker compose exec app php artisan migrate
```

Cette commande exécute toutes les migrations en attente, y compris celles fournies par défaut avec Laravel. Elle ne doit être lancée qu'au moment prévu par le formateur. Un retour arrière peut supprimer des tables et leurs données : ne lancez pas `migrate:rollback` sans en comprendre les effets.

Le test `DocumentPersistenceTest` vérifie la migration et le modèle dans une base SQLite temporaire en mémoire. Il ne crée pas les tables dans la base PostgreSQL de formation.

## API des documents

Après l'exécution des migrations, les routes `/api/documents` permettent de lister et créer ses documents et les routes `/api/documents/{document}` permettent de les consulter, modifier et supprimer. Elles nécessitent une authentification Sanctum. La création et la modification valident `title` et `category`. Le fichier PDF de 1 Mo maximum est envoyé et téléchargé par les routes `/api/documents/{document}/file`. Le [TD sécurité](TD-Securite-Documents.md) explique les requêtes et leurs résultats.

Après avoir rejoint cette branche, synchronisez les dépendances dans Docker avec `docker compose exec app composer install`. Les migrations et la création des comptes fictifs sont à effectuer au moment prévu dans le TD, uniquement dans la base locale de formation.

## Commandes utiles

```bash
docker compose ps
docker compose logs -f app
docker compose exec app php artisan --version
docker compose exec app php artisan route:list
docker compose exec app php artisan test
docker compose stop
docker compose up -d
```

`docker compose stop` arrête les conteneurs sans supprimer les données. `docker compose down` conserve normalement les volumes nommés ; l'option `-v` les supprime, y compris la base, les dépendances et la configuration de pgAdmin.

## Organisation

- `Dockerfile` : image PHP et Composer, avec l'extension PostgreSQL.
- `compose.yaml` : application, PostgreSQL, pgAdmin et leurs volumes.
- `.env.example` : modèle de configuration locale.
- `routes/`, `app/` et `database/migrations/` : routes, code PHP et migrations étudiés au jour 2.

La page affichée au départ est celle de Laravel. La route `/api/exemples/document` fournit un exemple fictif. L'interface Fetch du jour 2 ne transmet pas encore de token et doit être adaptée pour appeler les routes protégées.
