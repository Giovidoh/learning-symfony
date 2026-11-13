# Comment transformer des objets en JSON en Symfony (Serializer)

## Objectif

Exposer les recettes en **JSON** pour un client d'API (React, Next.js, mobile), en choisissant **quels champs** sortent selon la route : liste ou détail.

## Comment ça marche

La sérialisation se fait en **deux étapes** :

```
Objet ──normalize──▶ tableau PHP ──encode──▶ JSON / XML / YAML / CSV
       (Normalizer)                (Encoder)
```

- Les **normalizers** transforment un objet en tableau. `ObjectNormalizer` lit les getters et les propriétés publiques, et d'autres normalizers gèrent `DateTime`, `Uuid`, les erreurs, etc.
- Les **encoders** transforment ce tableau en texte.
- La désérialisation fait le chemin inverse : `decode` puis `denormalize`. C'est le sujet de la vidéo suivante.

## Étapes

1. Installer le composant : `composer require symfony/serializer-pack`.
2. **Choisir les champs avec des groupes** : `#[Groups(['recipes.index'])]` sur chaque propriété à exposer. Une propriété **sans** groupe n'est pas exposée dès qu'on demande des groupes.
3. Dans le contrôleur : `return $this->json($data, 200, [], ['groups' => ['recipes.index']]);`. Pour le détail, on cumule les groupes : `['recipes.index', 'recipes.show']`.
4. Pour une **relation**, mettre le groupe sur la propriété de `Recipe` (`$category`) **et** sur les propriétés de `Category` à afficher (`$name`).
5. **Pour un objet que le serializer ne sait pas traiter**, comme la pagination de KnpPaginator : écrire un **normalizer personnalisé**, qui implémente `NormalizerInterface`. L'autoconfiguration l'enregistre automatiquement.

## Code minimal

```php
#[Groups(['recipes.index'])]
private string $title = '';

#[Groups(['recipes.show'])]
private ?Category $category = null;
```

```php
#[Route('/api/recipes', methods: ['GET'], format: 'json')]
public function index(...): JsonResponse
{
    return $this->json($recipes, context: ['groups' => ['recipes.index']]);
}
```

```php
// Normalizer personnalisé pour la pagination
final class PaginationNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait; // reçoit le serializer complet, sans #[Autowire]

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        return [
            'items'    => $this->normalizer->normalize($object->getItems(), $format, $context),
            'total'    => $object->getTotalItemCount(),
            'page'     => $object->getCurrentPageNumber(),
            'lastPage' => (int) ceil($object->getTotalItemCount() / $object->getItemNumberPerPage()),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof PaginationInterface;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [PaginationInterface::class => true];
    }
}
```

## Pièges

- **Les références circulaires.** Si `Category::$recipes` et `Recipe::$category` ont tous les deux un groupe exposé, le serializer tourne en boucle (`CircularReferenceException`). Les groupes servent justement à **couper** la relation d'un côté.
- **Sans groupes, tout est exposé**, y compris ce qui ne devrait pas l'être. Sur `User`, ce serait le hash du mot de passe. Il faut **toujours** passer des groupes, ou utiliser un DTO.
- **Les requêtes N+1** : sérialiser `category` sur une liste déclenche une requête par recette, sauf si le repository fait déjà la jointure.
- **Un normalizer qui « type » ses éléments** (`fn(Recipe $r) => ...`) ne marche que pour des recettes. En passant tout le tableau au serializer, le même normalizer sert pour toutes les paginations.
- **`ceil()` retourne un `float`.** Il faut le convertir en `(int)` pour que le JSON contienne un entier propre.
- **Les erreurs en HTML.** Sans `format: 'json'` sur la route, une 404 en dev renvoie une page HTML, et le client JavaScript ne peut pas lire l'erreur. Avec `format: 'json'`, Symfony renvoie une erreur JSON au format « problem details » (RFC 7807) : `{"type": ..., "title": ..., "status": 404, "detail": "Not Found"}`.
- **Ce qui a changé par rapport aux anciennes versions :**
  - `Symfony\Component\Serializer\Annotation\Groups` a été remplacé par `Symfony\Component\Serializer\Attribute\Groups` : l'espace de noms `Annotation` est **supprimé en Symfony 8**.
  - `getSupportedTypes()` est **obligatoire** depuis Symfony 7. Il remplace `CacheableSupportsMethodInterface`, qui a été supprimé.
