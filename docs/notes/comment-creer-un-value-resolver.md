# Comment créer un ValueResolver en Symfony

## Objectif

Laisser Symfony **fabriquer les arguments d'un contrôleur** à ta place. Tu demandes `Recipe $recipe`, et l'objet arrive tout prêt, sans code de préparation dans l'action.

Tu en utilises déjà sans le savoir :

| Ce que tu écris dans l'action | Le resolver qui le fournit |
|---|---|
| `Request $request` | `RequestValueResolver` |
| `Recipe $recipe` + `{id}` dans l'URL | `EntityValueResolver` (Doctrine) : un `find($id)`, ou une 404 |
| `#[MapRequestPayload] RecipeDTO $dto` | `RequestPayloadValueResolver` : JSON → objet → validation |
| `#[MapQueryString] PaginationDTO $p` | Le même resolver, à partir de `?page=2` |
| `#[CurrentUser] User $user` | `UserValueResolver` |
| `int $id` | `RequestAttributeValueResolver` (paramètre de route) |

**Un resolver personnalisé** sert quand une préparation revient dans plusieurs actions. Exemple : « charger l'entité, appliquer le JSON reçu dessus, valider ». Le résultat est réutilisable avec un simple attribut.

## Comment ça marche

Pour chaque argument de l'action, le `ArgumentResolver` demande à chaque resolver, par ordre de priorité : « sais-tu fournir celui-là ? ».

- Un resolver qui **ne sait pas** renvoie `[]`, et on passe au suivant.
- Un resolver qui **sait** renvoie `[$valeur]`, et c'est terminé.
- Un resolver **ciblé** par `#[ValueResolver(MonResolver::class)]` est le **seul** appelé pour cet argument.

## Étapes

1. Créer une classe qui implémente `ValueResolverInterface` et sa méthode `resolve(Request, ArgumentMetadata): iterable`.
2. La marquer `#[AsTargetedValueResolver]`. Elle n'est alors appelée **que** quand un argument la demande explicitement, pas pour tous les arguments de toutes les actions.
3. (Option) Créer un attribut à toi qui étend `Symfony\Component\HttpKernel\Attribute\ValueResolver` et pointe vers le resolver. Cet attribut peut porter des options, comme les groupes.
4. L'utiliser : `#[MapHydratedEntity(groups: ['recipes.create'])] Recipe $recipe`.

## Code minimal

```php
namespace App\ValueResolver\Attribute;

use App\ValueResolver\EntityHydratorValueResolver;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;   // HttpKernel, pas Console !

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class MapHydratedEntity extends ValueResolver
{
    public function __construct(public readonly array $groups = [])
    {
        parent::__construct(EntityHydratorValueResolver::class);
    }
}
```

```php
namespace App\ValueResolver;

#[AsTargetedValueResolver]
final readonly class EntityHydratorValueResolver implements ValueResolverInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.entity_value_resolver')]
        private ValueResolverInterface $entityValueResolver,  // on réutilise celui de Doctrine
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
    ) {}

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attribute = $argument->getAttributesOfType(MapHydratedEntity::class)[0] ?? null;
        $entity = $this->entityValueResolver->resolve($request, $argument)[0] ?? null;
        if (!$attribute || !\is_object($entity)) {
            return [];
        }

        $this->serializer->deserialize($request->getContent(), $entity::class, 'json', [
            'groups' => $attribute->groups,
            AbstractNormalizer::OBJECT_TO_POPULATE => $entity,   // on modifie l'entité existante
        ]);

        $violations = $this->validator->validate($entity);
        if (\count($violations) > 0) {
            throw new UnprocessableEntityHttpException((string) $violations, new ValidationFailedException($entity, $violations));
        }

        return [$entity];
    }
}
```

```php
#[Route('/api/recipes/{id}', methods: ['PUT'], requirements: ['id' => Requirement::DIGITS])]
#[IsGranted(RecipeVoter::EDIT, subject: 'recipe')]
public function update(#[MapHydratedEntity(groups: ['recipes.create'])] Recipe $recipe, EntityManagerInterface $em): JsonResponse
{
    $em->flush();
    return $this->json($recipe, context: ['groups' => ['recipes.index', 'recipes.show']]);
}
```

## Pièges

- **Les routes sans `methods` acceptent tout (`ANY`).** Une route `show` sans `methods: ['GET']`, déclarée avant `update`, intercepte aussi les `PUT`. L'action `update` n'est alors jamais appelée, et rien ne signale d'erreur.
- **`use` du mauvais composant.** Il existe un `ValueResolver` dans **Console** (pour les arguments de commande) et un dans **HttpKernel** (pour les contrôleurs). Il faut celui de HttpKernel. Même chose pour `ValidationFailedException` : prendre celle du **Validator**, pas celle de Messenger.
- **Namespace = dossier (PSR-4).** Un fichier `src/ValueResolver/X.php` doit avoir le namespace `App\ValueResolver`. Sinon : « class not found ».
- **Les groupes de sérialisation doivent exister sur l'entité.** `groups: ['write']`, alors qu'aucune propriété n'a le groupe `write`, donne un PUT qui ne modifie **rien**, sans aucune erreur.
- **Le resolver ne fait pas de contrôle d'accès.** N'importe quel utilisateur connecté pourrait modifier la recette d'un autre. Il faut le Voter sur l'action : `#[IsGranted(..., subject: 'recipe')]`.
- **Ordre d'exécution :** les arguments sont résolus **avant** la vérification de `#[IsGranted]`. Le Voter a en effet besoin du `$recipe` déjà construit. Un non-propriétaire qui envoie un JSON invalide reçoit donc une 422 (validation), et non une 403. Avec un JSON valide, il reçoit bien une 403. La base n'est pas modifiée, parce qu'il n'y a pas de `flush()`. Seul l'objet en mémoire a été modifié.
- **Le nom du paramètre de route doit correspondre aux `requirements`** : `{recipe}` avec `requirements: ['id' => ...]` signifie que la contrainte ne s'applique à rien.
- **Sans `#[AsTargetedValueResolver]`**, le resolver est interrogé pour **chaque** argument de **chaque** action. Ça marche s'il renvoie `[]`, mais c'est du travail pour rien.
- **Faut-il un resolver personnalisé ?** Souvent non. `#[MapEntity]`, `#[MapRequestPayload]` et `#[MapQueryString]` couvrent 90 % des besoins. On n'en écrit un que quand la même logique se répète.
- **Ce qui a changé par rapport aux anciennes versions :**
  - `ValueResolverInterface` (6.2+) remplace `ArgumentValueResolverInterface` et ses deux méthodes `supports()` + `resolve()`, supprimée en 7.0.
  - `#[ValueResolver]` et `#[AsTargetedValueResolver]` sont arrivés en 6.3.
  - `#[MapRequestPayload]` et `#[MapQueryString]` sont arrivés en 6.3.
  - `#[MapEntity]` (6.2+) remplace le `@ParamConverter` de SensioFrameworkExtraBundle, qui est abandonné.
