# Comment recevoir du JSON avec MapRequestPayload et MapQueryString en Symfony

## Objectif

Recevoir des données d'un client d'API (un `fetch()` en React ou Next.js), les **transformer en objet PHP** (désérialisation) et les **valider**, sans `json_decode()` ni formulaire.

## Les trois attributs

| Attribut | Lit | Exemple |
|---|---|---|
| `#[MapRequestPayload]` | Le **corps** de la requête (JSON, ou formulaire) | `POST /api/recipes` avec `{"title": "..."}` |
| `#[MapQueryString]` | La **query string** entière, mise dans un DTO | `GET /api/recipes?page=2&sort=title` |
| `#[MapQueryParameter]` | **Un seul** paramètre de l'URL | `int $page = 1` |

Pour chacun, Symfony fait trois choses :
1. **désérialiser** (decode, puis denormalize) ;
2. **valider** les `#[Assert]` ;
3. en cas d'erreur, renvoyer la liste des violations en JSON, sans que le contrôleur s'exécute. Le code est **422** pour `MapRequestPayload`, mais **404** par défaut pour `MapQueryString` et `MapQueryParameter`. On le change avec l'option `validationFailedStatusCode`.

## Étapes

1. **Créer un DTO**, ou utiliser l'entité, avec des contraintes `#[Assert]`.
2. **Ajouter l'attribut sur l'argument du contrôleur.** Sans attribut, Symfony ne sait pas d'où viennent les données.
3. **Limiter les champs acceptés** avec des groupes de désérialisation : `#[Groups(['recipes.create'])]` sur les propriétés modifiables, et `serializationContext: ['groups' => ['recipes.create']]` dans l'attribut.
4. **Répondre 201** (Created) après une création.

## Code minimal

```php
final class PaginationDTO
{
    public function __construct(
        #[Assert\Positive]
        public readonly int $page = 1,
    ) {}
}

#[Route('/api/recipes', methods: ['GET'])]
// Type NON nullable : sans query string, Symfony dénormalise [] → new PaginationDTO() → page = 1
public function index(#[MapQueryString] PaginationDTO $pagination) {
    $page = $pagination->page;
}
// Avec ?PaginationDTO (ou une valeur par défaut), il passe null quand l'URL n'a pas de paramètre
// → il faut alors $pagination?->page ?? 1
```

```php
#[Route('/api/recipes', methods: ['POST'])]
public function create(
    #[MapRequestPayload(serializationContext: ['groups' => ['recipes.create']])]
    Recipe $recipe,
    EntityManagerInterface $em,
): JsonResponse {
    $em->persist($recipe);
    $em->flush();
    return $this->json($recipe, 201, context: ['groups' => ['recipes.index', 'recipes.show']]);
}
```

```php
// Entité : les champs que le client a le droit d'envoyer
#[Groups(['recipes.index', 'recipes.create'])]
private string $title = '';
```

## Pièges

- **Une route sans `methods:` accepte toutes les méthodes.** Si `GET /api/recipes` est déclarée **avant** `POST /api/recipes` sans `methods: ['GET']`, elle intercepte aussi le POST, et l'action `create` n'est jamais appelée. On le voit dans `debug:router`, colonne `ANY`.
- **Un argument sans attribut n'est pas rempli avec la requête.** Comme `services.yaml` charge tout `src/` en services, `src/DTO/PaginationDTO` est **aussi un service**. Sans `#[MapQueryString]`, le conteneur injecte donc ce service, construit avec les valeurs par défaut : `page` vaut toujours 1, quelle que soit l'URL. C'est un bug silencieux. Il faut ajouter l'attribut. Pour plus de propreté, on peut aussi **retirer le DTO du conteneur** : `#[Exclude]` (`Symfony\Component\DependencyInjection\Attribute\Exclude`) sur la classe, ou une clé `exclude: '../src/DTO/'` sous `App\:` dans `services.yaml`. Le `services.yaml` de Symfony 8 n'a plus de clé `exclude` par défaut.
- **`?->` renvoie `null`** : `$dto?->page` passé à une méthode qui attend `int` provoque une `TypeError`, donc une 500. Il faut prévoir une valeur par défaut avec `?? 1`.
- **Un groupe de désérialisation qu'aucune propriété ne porte** : le serializer n'écrit **rien** dans l'objet. On obtient un objet vide, refusé par la validation (422), ou pire, enregistré vide.
- **Sans groupes, le client peut écrire n'importe quel champ** qui a un setter, par exemple `id`, `createdAt` ou `slug`. C'est une faille d'**assignation de masse** : il faut **toujours** restreindre avec des groupes ou un DTO dédié.
- **Le JSON doit être envoyé avec `Content-Type: application/json`.** Sinon `MapRequestPayload` ne sait pas le décoder, et répond 415 Unsupported Media Type.
- **Ce qui a changé par rapport aux anciennes versions :** avant Symfony 6.3, on faisait tout à la main : `$serializer->deserialize($request->getContent(), Recipe::class, 'json')`, puis `$validator->validate()`, puis on construisait soi-même la réponse 400 ou 422. Aujourd'hui, un seul attribut fait tout cela.
