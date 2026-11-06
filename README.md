# Application Laravel et PostgreSQL

Projet de départ du jour 2 pour le fil rouge de gestion de documents. Il contient une application Laravel 13 vierge et son environnement Docker. L'API et l'interface reliée à la base seront construites pendant la formation.

Pour suivre les étapes détaillées avec les vérifications et les solutions aux problèmes courants, consultez le [guide de travaux dirigés](TD-Demarrage-Docker.md).

## Prérequis

- Docker Desktop avec le moteur de conteneurs Linux et Docker Compose ; sous Windows, WSL 2 doit être fonctionnel.
- Git pour cloner le dépôt et VS Code pour modifier les fichiers.
- Le port **8088** disponible sur la machine.
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

## Base de données et migrations

Dans le réseau Docker, Laravel se connecte à PostgreSQL grâce au nom de service `db` sur le port interne 5432. PostgreSQL n'est pas exposé sur un port Windows. Le volume Docker `postgres_data` conserve ses données après un arrêt ou une recréation ordinaire des conteneurs.

Les migrations ne sont pas exécutées automatiquement pour permettre de les découvrir pendant le cours. Après leur présentation, lancez :

```bash
docker compose exec app php artisan migrate
```

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

`docker compose stop` arrête les conteneurs sans supprimer les données. `docker compose down` conserve normalement les volumes nommés ; l'option `-v` les supprime et efface donc la base de données de cette formation.

## Organisation

- `Dockerfile` : image PHP et Composer, avec l'extension PostgreSQL.
- `compose.yaml` : application, base PostgreSQL et volume de données.
- `.env.example` : modèle de configuration locale.
- `routes/`, `app/` et `database/migrations/` : routes, code PHP et migrations étudiés au jour 2.

La page affichée au départ est celle de Laravel. Il n'y a pas encore de CRUD, d'API de documents ni de téléversement de fichiers.
