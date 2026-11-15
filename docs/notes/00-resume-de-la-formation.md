# Résumé de la formation Symfony (Grafikart), en Symfony 8

Le projet fil rouge est une application de **recettes** (`TutoSymfony`). Elle a deux faces :

- une interface HTML en Twig, avec une partie admin ;
- une API JSON protégée par un jeton.

Pour chaque notion, la fiche détaillée correspondante est indiquée par 📄.

---

## La vue d'ensemble en une image

```
Requête HTTP
  → Routeur (#[Route], préfixe {_locale})
  → Firewall / Security (authenticator, session ou jeton)
  → ValueResolvers : fabriquent les arguments (Recipe $recipe, #[MapRequestPayload]...)
  → #[IsGranted] → Voters
  → Contrôleur (mince) → Services (Repository, Mailer, MessageBus...)
  → Doctrine (entités, relations, migrations)
  → Réponse : Twig (HTML), Serializer (JSON) ou Turbo Stream
  ↘ Évènements à chaque étape (kernel.request, login, évènements maison)
  ↘ Messenger : tâches lentes envoyées à un worker (e-mails, PDF)
```

Le fil conducteur de toute la formation : **Symfony fournit une brique pour chaque besoin, et on la branche avec un attribut PHP** (`#[Route]`, `#[Assert]`, `#[IsGranted]`, `#[AsEventListener]`, `#[AsMessageHandler]`, `#[MapRequestPayload]`…). On écrit très peu de configuration à la main.

---

## Chapitre 1 : Découverte

### Installation et premières pages
- `symfony new projet --webapp`, puis `symfony serve -d`.
- Structure du projet :
  - `src/` : le code PHP (contrôleurs, entités, services) ;
  - `templates/` : Twig ;
  - `config/` : YAML ;
  - `migrations/` : l'évolution du schéma de la base ;
  - `public/` : seul dossier exposé au web ;
  - `var/` : cache et logs.
- **Contrôleur** : une classe qui étend `AbstractController`, avec des méthodes marquées `#[Route('/chemin', name: 'nom')]` qui renvoient une `Response`.
- Paramètres de route : `{id}`, avec `requirements: ['id' => Requirement::DIGITS]` et `methods: ['GET']`.
- `debug:router` liste toutes les routes.
- Le `.env` est versionné et ne contient que des valeurs par défaut. **La configuration perso va dans `.env.local`**, qui n'est pas versionné.

### Twig
- `{{ variable }}` affiche une valeur (échappée automatiquement), `{% if/for %}` sert à la logique, `{# commentaire #}`.
- Héritage : `{% extends 'base.html.twig' %}` + `{% block body %}`.
- Liens : `path('nom_route', {id: 1})`, et **jamais d'URL écrite en dur**.
- Filtres utiles : `|date`, `|nl2br`, `|trans`, `|u.truncate`.
- `app.user`, `app.request`, `app.flashes` et `is_granted()` sont disponibles partout.

### Doctrine
- **Entité** : une classe PHP avec `#[ORM\Entity]` et `#[ORM\Column]`. Le **Repository** contient les requêtes.
- Commandes : `make:entity`, puis `make:migration`, puis `doctrine:migrations:migrate`.
- **Règle d'or : on ne modifie jamais la base à la main**, on passe toujours par une migration.
- `EntityManagerInterface` : `persist()` pour un nouvel objet, `flush()` pour écrire en base, `remove()` pour supprimer.
- `find()`, `findBy()`, `findOneBy()` ou une méthode maison avec le `QueryBuilder`.

### Formulaires et CRUD 📄 `comment-faire-un-crud-avec-les-formulaires.md`
- `make:form RecipeType` crée une classe qui décrit les champs.
- Dans le contrôleur :
  1. `createForm()` ;
  2. `handleRequest($request)` ;
  3. `if ($form->isSubmitted() && $form->isValid())` ;
  4. `flush()`, puis `addFlash()`, puis `redirectToRoute()`.
- Twig : `{{ form(form) }}`, ou bien `form_start`/`form_row`/`form_end`.
- Le thème Bootstrap se configure dans `twig.yaml`.
- `empty_data` permet d'éviter une erreur 500 quand un setter n'accepte pas `null`.
- Les évènements de formulaire (`PRE_SUBMIT`, `POST_SUBMIT`) servent à remplir le slug ou les dates automatiquement.

### Valider les données 📄 `comment-valider-des-donnees.md`
- Des contraintes en attributs sur l'entité ou le DTO : `#[Assert\NotBlank]`, `Length`, `Positive`, `Regex`, `#[UniqueEntity]`.
- Elles sont vérifiées automatiquement par `isValid()`, par `#[MapRequestPayload]` ou par `ValidatorInterface::validate()`.
- On peut aussi écrire des contraintes personnalisées et utiliser des **groupes de validation**.

### Services 📄 `comment-creer-et-configurer-un-service.md`
- Toute classe de `src/` est un **service**. On la reçoit en la **typant dans un constructeur** ou un argument d'action : c'est l'**autowiring**.
- Pour un paramètre ou une variable d'environnement : `#[Autowire('%param%')]` ou `#[Autowire(env: 'X')]`.
- `debug:autowiring` liste ce qu'on peut injecter.
- Un contrôleur doit rester **mince** : il délègue aux services.

### TP Formulaire de contact 📄 `comment-envoyer-un-email-depuis-un-formulaire.md`
- Un **DTO** (`ContactDTO`) avec des contraintes, et un formulaire qui n'est pas lié à une entité.
- `MailerInterface` + `TemplatedEmail` (le corps de l'e-mail en Twig). En dev, Mailpit attrape les e-mails.
- À retenir : `from` = ton adresse, `replyTo` = l'adresse du visiteur.

### TP Catégorie + relation ManyToOne 📄 `comment-faire-une-relation-manytoone.md`
- Une `Recipe` a une `Category` : `#[ORM\ManyToOne]` d'un côté, `#[ORM\OneToMany(mappedBy)]` de l'autre.
- Le côté **propriétaire** (celui qui a la clé étrangère) est le ManyToOne.
- Dans le formulaire : `EntityType` avec `choice_label`.
- La suppression d'une catégorie encore utilisée se gère avec `onDelete`, `cascade` ou un message d'erreur.

### Envoi de fichiers 📄 `comment-envoyer-des-fichiers.md`
- VichUploaderBundle : `#[Vich\Uploadable]` sur la classe, `#[Vich\UploadableField]` sur une propriété `File`, et un champ `thumbnail` en base.
- **Piège :** il faut modifier un champ mappé (`updatedAt`), sinon Doctrine ne voit aucun changement et l'upload n'est pas déclenché.

### Le composant Sécurité 📄 `comment-gerer-l-authentification.md`
- `make:user`, `make:security:form-login`, `make:registration-form` (avec la vérification de l'e-mail).
- `security.yaml` contient :
  - les **providers** (où trouver l'utilisateur) ;
  - les **firewalls** (comment il s'authentifie) ;
  - les **password_hashers**.
- Les mots de passe sont hachés avec `UserPasswordHasherInterface`.
- Protection : `#[IsGranted('ROLE_ADMIN')]`, `denyAccessUnlessGranted()`, et `is_granted()` dans Twig.
- Pour le login : CSRF, `remember_me`, et la redirection après connexion **par nom de route**.

### Paginer 📄 `comment-paginer-des-donnees.md`
- KnpPaginator : `$paginator->paginate($queryBuilder, $page, 20)`, et `knp_pagination_render()` dans Twig.
- On peut aussi utiliser le `Paginator` de Doctrine à la main.
- Attention aux tris autorisés (`sortFieldAllowList`).

### Doctrine avancé 📄 `comment-faire-des-requetes-doctrine-avancees.md`
- **Problème N+1** : une boucle Twig qui déclenche une requête par ligne. La solution : `leftJoin` + `addSelect`.
- **Select partiel vers DTO** : `SELECT NEW App\DTO\CategoryWithCountDTO(c.id, c.name, COUNT(r.id))`.
- DQL brut avec `createQuery()`. Utiliser `debug:` et le **profiler** (onglet Doctrine) pour compter les requêtes.

### AssetMapper 📄 `comment-gerer-les-assets-avec-asset-mapper.md`
- Pas de Webpack ni de Node : `assets/` + `importmap.php`.
- `importmap:require bootstrap` installe une bibliothèque, `asset()` donne son URL dans Twig.
- En production : `asset-map:compile`.

---

## Chapitre 2 : Créer une API

### Le Serializer 📄 `comment-serialiser-en-json.md`
- Objet → tableau (normalizer) → JSON (encoder) : `$this->json($data, 200, [], ['groups' => [...]])`.
- **Groupes** : `#[Groups(['recipes.index'])]` sur les propriétés. C'est une **liste blanche** de ce qui sort.
- **Ne jamais sérialiser une entité sans groupes.** Sinon, tout sort : hash du mot de passe, jeton, références circulaires.
- Un normalizer personnalisé (`PaginationNormalizer`) permet de mettre en forme une pagination.

### Désérialisation et MapRequestPayload 📄 `comment-recevoir-du-json-avec-maprequestpayload.md`
- `#[MapRequestPayload] Recipe $recipe` : JSON → objet → **validation** automatique. En cas d'échec : **422** avec la liste des erreurs.
- `#[MapQueryString] PaginationDTO $dto` : `?page=2` → DTO validé.
- `serializationContext: ['groups' => ['recipes.create']]` limite les champs que le client a le droit d'envoyer.
- Pour modifier un objet existant : `AbstractNormalizer::OBJECT_TO_POPULATE`.

### Authenticator stateless 📄 `comment-proteger-une-api-avec-un-token.md`
- Un firewall `api` séparé avec `stateless: true` (pas de session) et un `custom_authenticator`.
- L'authenticator :
  - `supports()` regarde s'il y a un en-tête `Authorization: Bearer xxx` ;
  - `authenticate()` renvoie un `Passport` avec un `UserBadge`, qui cherche l'utilisateur par son `apiToken` ;
  - `onAuthenticationFailure()` renvoie une **401 en JSON**, pas une redirection.
- `LoginSuccessEvent` est déclenché **à chaque requête** de l'API, puisqu'il n'y a pas de session.

---

## Chapitre 3 : Notions avancées

### Fixtures 📄 `comment-remplir-la-base-avec-des-fixtures.md`
- `DoctrineFixturesBundle` + Faker : `doctrine:fixtures:load` vide la base et la remplit avec des données réalistes.
- `addReference()` / `getReference()` relient les fixtures entre elles. `DependentFixtureInterface` fixe leur ordre.
- **Uniquement en dev et en test.**

### Voter 📄 `comment-gerer-les-permissions-avec-un-voter.md`
- Il répond à la question « cet utilisateur peut-il faire X **sur cet objet** ? ». Exemple : modifier **sa** recette.
- `supports($attribute, $subject)` + `voteOnAttribute()`. Utilisation : `#[IsGranted('RECIPE_EDIT', subject: 'recipe')]`.
- Stratégie `affirmative` : un seul « oui » suffit. Cela permet un `AdminVoter` qui accorde tout aux admins.
- Comparer les propriétaires avec `===`, sans `getId()` : il peut ne pas y avoir de propriétaire.
- `?Vote $vote` permet d'expliquer un refus (Symfony 7.3+).

### Évènements 📄 `comment-utiliser-les-evenements.md`
- Deux façons d'écouter :
  - le **listener** (`#[AsEventListener]`, une méthode par évènement) ;
  - le **subscriber** (`getSubscribedEvents()`, la liste des évènements est dans la classe).
- On peut aussi créer ses propres évènements (`ContactRequestEvent`) et les envoyer avec `dispatch()`.
- Les évènements de sécurité passent par le dispatcher **du firewall** : voir `debug:event-dispatcher --dispatcher=security.event_dispatcher.main`.
- Une exception dans un listener remonte jusqu'à l'appelant. Pour un envoi d'e-mail, utiliser `try/catch` + un logger.

### Symfony UX 📄 `comment-ajouter-de-l-interactivite-avec-symfony-ux.md`
- **Turbo Drive** : la navigation se fait sans rechargement complet de la page.
- **Turbo Frame** : on ne remplace qu'une zone de la page. **Les `id` des deux pages doivent correspondre.**
- **Turbo Stream** : le serveur envoie des ordres comme `remove` ou `append`. On le détecte avec `getPreferredFormat() === TurboBundle::STREAM_FORMAT`.
- **Stimulus** : de petits contrôleurs JS (`data-controller`).
- Les composants UX prêts à l'emploi : Autocomplete, TogglePassword.
- Un formulaire invalide passé à `render()` renvoie automatiquement une **422**, ce dont Turbo a besoin.

### Formulaires imbriqués 📄 `comment-faire-des-formulaires-imbriques.md`
- `CollectionType` de `QuantityType` dans `RecipeType`, avec `allow_add`, `allow_delete` et **`by_reference: false`**.
- Côté entité : `cascade: ['persist']` + `orphanRemoval: true`.
- Côté JS : un contrôleur Stimulus qui clone le `prototype`. **Son index de départ doit être l'index maximum + 1.**
- Les sous-formulaires qui ont un `data_class` sont validés automatiquement.

### Messenger 📄 `comment-traiter-des-taches-en-asynchrone-avec-messenger.md`
- Le **message** (`RecipePDFMessage`) contient des données simples, comme **un id, pas une entité**.
- Le **handler** est marqué `#[AsMessageHandler]`. Le message est routé vers le transport `async` (Doctrine) dans `messenger.yaml`.
- Le **worker** se lance avec `messenger:consume async`. **Sans worker, rien ne part**, et le worker ne recharge pas le code modifié.
- En cas d'échec :
  - `retry_strategy` réessaie ;
  - puis le message part dans `failure_transport` ;
  - `messenger:failed:show` et `messenger:failed:retry` permettent de les traiter.
- Le projet génère des PDF avec Gotenberg (Docker). Deux pièges :
  - utiliser `host.docker.internal` pour que le conteneur atteigne ta machine ;
  - publier le port du conteneur avec `-p 3000:3000`.
- Les URL absolues générées en ligne de commande ont besoin de `DEFAULT_URI`.

### Internationalisation 📄 `comment-internationaliser-une-application.md`
- Fichiers `translations/messages.{fr,en}.yaml`. Format **ICU** (`+intl-icu`) pour les pluriels et les variables.
- `|trans` dans Twig, `TranslatorInterface` en PHP. Les labels de formulaire sont traduits automatiquement.
- La locale dans l'URL : préfixe `/{_locale}` dans `routes.yaml`, **sauf pour l'API**, qui utilise `Accept-Language`.
- `enabled_locales` et `fallbacks`. `LocaleSwitcher` permet de prendre la langue de l'utilisateur.
- Contenu traduit en base : Gedmo Translatable + table `ext_translations` (créée par migration) + `TranslationWalker` pour les requêtes DQL.
- **Piège vu dans le projet :** le préfixe a cassé tout ce qui reposait sur un chemin écrit en dur (le firewall `^/api/`, `RedirectResponse('/')`).

### ValueResolver 📄 `comment-creer-un-value-resolver.md`
- Ce sont eux qui **fabriquent les arguments des contrôleurs**. Tu en utilises depuis le début : `Recipe $recipe`, `Request`, `#[MapRequestPayload]`, `#[CurrentUser]`.
- Un resolver maison : `ValueResolverInterface::resolve()` + `#[AsTargetedValueResolver]` + un attribut qui étend `HttpKernel\Attribute\ValueResolver`.
- L'exemple du projet est `#[MapHydratedEntity]` : charger, appliquer le JSON, valider, pour faire un PUT en une ligne.
- Les arguments sont résolus **avant** `#[IsGranted]`.

---

## Ce qui a changé par rapport aux anciens tutoriels (Symfony 4/5)

| Avant | Symfony 8 |
|---|---|
| Annotations `@Route`, `@ORM\Column`, `@Assert` | **Attributs PHP** `#[Route]`, `#[ORM\Column]`, `#[Assert\...]` |
| `@ParamConverter` (SensioFrameworkExtraBundle) | `#[MapEntity]`, `#[MapRequestPayload]`, `#[MapQueryString]` |
| Guard authenticators | **Authenticator** + `Passport` / `Badge` |
| `MessageHandlerInterface`, `EventSubscriberInterface` obligatoire | `#[AsMessageHandler]`, `#[AsEventListener]` |
| `ArgumentValueResolverInterface` (`supports` + `resolve`) | `ValueResolverInterface` (seulement `resolve`) |
| Webpack Encore + Node | **AssetMapper** + importmap (sans Node) |
| `transchoice` | Format **ICU** |
| `services.yaml` rempli à la main | Autowiring + `#[Autowire]`, `#[AsAlias]`, `#[Target]` |
| Validation des JSON à la main | `#[MapRequestPayload]`, qui renvoie 422 automatiquement |

---

## Les pièges rencontrés dans le projet (à retenir)

1. **Une route sans `methods` accepte toutes les méthodes (`ANY`).** Elle peut capter les PUT et DELETE destinés à une autre route.
2. **Une URL écrite en dur casse dès que les routes changent.** Il faut toujours passer par `path()` ou `generate('nom')`.
3. **Sérialiser une entité sans groupes fait fuir des données** : `/api/me` renvoie le hash du mot de passe, ce qui n'est pas encore corrigé.
4. **Une entité Doctrine ne passe pas dans un message Messenger.** On y met un id, et on recharge l'entité dans le handler.
5. **Pour Docker, `localhost` désigne le conteneur lui-même.** Il faut utiliser `host.docker.internal` et publier le port avec `-p`.
6. **Un setter non nullable + un champ vide donnent une 500.** La solution : `empty_data` + `NotBlank`.
7. **Turbo Frame :** des `id` différents entre les deux pages donnent « Content missing ».
8. **Les évènements de sécurité passent par le dispatcher du firewall**, pas par le dispatcher global.
9. **Le worker et le serveur ne se relancent pas tout seuls** après un redémarrage de la machine, MySQL non plus.

## Reste à faire dans le projet

- Corriger la fuite du hash du mot de passe dans `/api/me` (groupe `user.me`).
- Ajouter le CSRF sur les actions DELETE (recettes, catégories).
- Refactoriser avec un service `FormListenerFactory` (slug + dates), avec `SluggerInterface` injecté.
- Petits détails de l'API :
  - `create` renvoie 200 au lieu de **201** ;
  - pas de tri (`orderBy`) dans la pagination.
- Écrire des tests (`WebTestCase`) : aucun pour l'instant.

## (API Symfony + front React/Next.js)

À maîtriser en priorité, dans cet ordre :

1. **Serializer et groupes** (ce qui sort) + **MapRequestPayload / MapQueryString** (ce qui entre).
2. **Authenticator stateless** (jeton ou JWT avec LexikJWTAuthenticationBundle) + **Voters**.
3. **Validation** et format des erreurs (422 et 400 en JSON).
4. **Doctrine** : relations, migrations, requêtes sans N+1.
5. **Messenger** pour tout ce qui est lent.
6. Points à découvrir ensuite, hors formation :
   - **API Platform**, très courant pour les API Symfony ;
   - **CORS** (NelmioCorsBundle) pour qu'un front Next.js sur un autre domaine puisse appeler l'API ;
   - **les tests fonctionnels** avec `WebTestCase`.

La partie Twig, Turbo et formulaires HTML te servira surtout pour un back-office.
