# TutoSymfony : application de recettes

Projet d'apprentissage réalisé en suivant la formation Symfony de **Grafikart**, adaptée à **Symfony 8.1**.

L'application gère des recettes de cuisine, avec catégories et ingrédients. Elle propose :

- une **interface HTML** (Twig + Turbo), avec une partie admin ;
- une **API JSON** protégée par un jeton.

> 📚 **Le résumé de la formation** se trouve dans [`docs/notes/00-resume-de-la-formation.md`](docs/notes/00-resume-de-la-formation.md).
> Chaque notion a sa fiche « Comment faire X en Symfony » dans [`docs/notes/`](docs/notes/).

---

## Stack

| Brique | Outil |
|---|---|
| Langage / framework | PHP 8.4, Symfony 8.1 |
| Base de données | MariaDB 10.11 (XAMPP), Doctrine ORM 3.7 + migrations |
| Front | Twig, Bootstrap, AssetMapper (sans Node), Symfony UX (Turbo, Stimulus, Autocomplete) |
| E-mails | Symfony Mailer, Mailpit en dev |
| Tâches asynchrones | Messenger (transport Doctrine) |
| PDF | Gotenberg 9 (Docker) |
| Bundles | VichUploader (images), KnpPaginator, Gedmo Translatable (StofDoctrineExtensions), DoctrineFixtures + Faker, VerifyEmail |

---

## Installation

### Prérequis

- PHP 8.4 et Composer
- [Symfony CLI](https://symfony.com/download)
- MariaDB ou MySQL (ici, celui de XAMPP)
- [Mailpit](https://github.com/axllent/mailpit/releases) : le binaire Windows se place dans `bin/` (`bin/mailpit.exe`)
- Docker, uniquement pour Gotenberg (génération des PDF)

### Étapes

```bash
composer install
```

Crée un fichier `.env.local`, qui n'est pas versionné. **Ne modifie pas `.env`**, qui ne contient que les valeurs par défaut.

```dotenv
# .env.local
DATABASE_URL="mysql://root:@127.0.0.1:3306/app?serverVersion=10.11.16-MariaDB&charset=utf8mb4"

# URL que Gotenberg (dans Docker) utilise pour joindre le serveur Symfony de ta machine
DEFAULT_URI=http://host.docker.internal:8000
```

Ensuite, crée la base, applique les migrations et charge les fixtures :

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n
```

> Le schéma de la base ne se modifie **que par des migrations** :
> `make:entity`, puis `make:migration`, puis `doctrine:migrations:migrate`.
> Jamais de `doctrine:schema:update`, jamais de SQL à la main.

---

## Lancer le projet

Il faut **4 processus** en parallèle :

```bash
# 1. MariaDB : depuis le panneau XAMPP (Start sur MySQL)

# 2. Serveur web : http://127.0.0.1:8000
symfony serve -d

# 3. Mailpit (e-mails de dev), à lancer depuis la racine du projet
#    SMTP sur localhost:1025, interface web sur http://localhost:8025
./bin/mailpit

# 4. Gotenberg (génération des PDF)
docker run -d --rm -p 3000:3000 gotenberg/gotenberg:9
```

Enfin, le **worker Messenger**. Sans lui, **aucun e-mail ni aucun PDF n'est traité** :

```bash
php bin/console messenger:consume async -vv
```

⚠️ Le worker ne recharge pas le code : relance-le après chaque modification d'un handler.

### Comptes de développement (créés par les fixtures)

| Identifiant | Mot de passe | Rôle |
|---|---|---|
| `admin` | `admin` | `ROLE_ADMIN` : voit et modifie toutes les recettes |
| `user1` … `user10` | `0000` | `ROLE_USER` : ne modifie que ses propres recettes |

---

## Fonctionnalités

### Site (HTML)

Les URL sont préfixées par la langue : `/fr/...` ou `/en/...`. La racine `/` redirige vers `/fr/`.

| URL | Description |
|---|---|
| `/{_locale}/` | Accueil |
| `/{_locale}/recettes/{id}` | Page publique d'une recette, qui sert aussi de source au PDF |
| `/{_locale}/contact` | Formulaire de contact : envoie un e-mail au service choisi |
| `/{_locale}/register`, `/{_locale}/login` | Inscription (avec vérification de l'e-mail), connexion |
| `/{_locale}/admin/recettes/` | CRUD des recettes : ingrédients imbriqués, image, suppression en Turbo Stream |
| `/{_locale}/admin/categories/` | CRUD des catégories, avec le nombre de recettes |

Quand on modifie une recette dans l'admin, un message Messenger part en file d'attente. Le worker génère alors `public/pdfs/{id}.pdf` avec Gotenberg.

### API (JSON)

Les routes de l'API n'ont pas de préfixe de langue. Chaque appel doit envoyer le jeton de l'utilisateur (colonne `user.api_token`) :

```
Authorization: Bearer <api_token>
```

| Méthode | URL | Description |
|---|---|---|
| `GET` | `/api/me` | Utilisateur connecté |
| `GET` | `/api/recipes?page=1` | Liste paginée : toutes les recettes pour un admin, seulement les siennes pour un utilisateur |
| `GET` | `/api/recipes/{id}` | Détail d'une recette |
| `POST` | `/api/recipes` | Création (`#[MapRequestPayload]`) |
| `PUT` | `/api/recipes/{id}` | Modification : resolver maison `#[MapHydratedEntity]` + Voter |

Codes de réponse :

| Code | Cas |
|---|---|
| `401` | Pas de jeton, ou jeton invalide |
| `403` | Pas propriétaire de la recette |
| `404` | La recette n'existe pas |
| `400` | JSON invalide |
| `422` | Erreur de validation |

Exemple :

```bash
curl -X PUT http://127.0.0.1:8000/api/recipes/127 \
  -H "Authorization: Bearer <api_token>" \
  -H "Content-Type: application/json" \
  -d '{"title":"Nouveau titre"}'
```

Le dossier [`api test/`](api%20test/) contient une collection de requêtes à ouvrir dans le client HTTP Bruno.

---

## Organisation du code

```
src/
├── Controller/          # Pages HTML (préfixées par {_locale})
│   ├── Admin/           # Back-office des recettes et catégories
│   └── API/             # API JSON (sans préfixe de langue)
├── Entity/              # Recipe, Category, Quantity, Ingredient, User
├── Repository/          # Requêtes (pagination, DTO avec COUNT, TranslationWalker)
├── Form/                # RecipeType (+ CollectionType de QuantityType), CategoryType, ContactType...
├── DTO/                 # ContactDTO, PaginationDTO, CategoryWithCountDTO
├── Security/            # AppAuthenticator (formulaire), APIAuthenticator (jeton)
│   └── Voter/           # RecipeVoter (propriétaire), AdminVoter (tout pour ROLE_ADMIN)
├── Event/ EventSubscriber/ EventListener/   # ContactRequestEvent, MailingSubscriber, UserLocaleListener
├── Message/ MessageHandler/                 # RecipePDFMessage → génération du PDF via Gotenberg
├── ValueResolver/       # EntityHydratorValueResolver + attribut #[MapHydratedEntity]
└── DataFixtures/        # Utilisateurs et recettes générés avec Faker
```

---

## Commandes utiles

```bash
php bin/console debug:router                  # toutes les routes
php bin/console debug:autowiring <mot>        # services injectables
php bin/console debug:translation fr          # traductions manquantes
php bin/console doctrine:schema:validate      # mapping et base synchronisés ?
php bin/console messenger:failed:show         # messages en échec
php bin/console messenger:failed:retry        # les relancer
php bin/console cache:clear
```

En cas d'erreur, consulte `var/log/dev.log` et le profiler (`/_profiler`).

---

## Limites connues / reste à faire

- 🔴 `/api/me` sérialise l'utilisateur **sans groupes** : la réponse contient le hash du mot de passe. Correction prévue : un groupe `user.me`.
- Pas de jeton CSRF sur les actions `DELETE` (recettes, catégories).
- Refactorisation prévue : un service `FormListenerFactory` (slug + dates automatiques) partagé par `RecipeType` et `CategoryType`.
- Petits détails de l'API :
  - `POST /api/recipes` renvoie `200` au lieu de `201` ;
  - pas de tri dans la pagination.
- Pas encore de tests (`WebTestCase`).
- `compose.yaml` et `compose.override.yaml` (PostgreSQL + Mailpit) ont été générés à l'installation avec l'option Docker, mais **ne sont pas utilisés**. La base tourne sur MariaDB (XAMPP) et Mailpit sur `bin/mailpit`. Seul Gotenberg passe par Docker, avec un simple `docker run`.
- Le déploiement (chapitre 4 de la formation) n'a pas été traité.
