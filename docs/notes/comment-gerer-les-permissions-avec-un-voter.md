# Comment gérer les permissions avec un Voter en Symfony

## Objectif

Décider **au cas par cas** si l'utilisateur connecté peut faire une action **sur un objet précis**. Exemple : « user1 peut modifier **ses** recettes, pas celles des autres ».

Un rôle (`ROLE_ADMIN`) répond à la question « qui es-tu ? ». Un Voter répond à la question « as-tu le droit de faire **ça** sur **cet objet** ? ».

## Le vocabulaire

| Terme | Exemple | Rôle |
|---|---|---|
| **attribute** | `'RECIPE_EDIT'` | Le nom de l'action |
| **subject** | `$recipe` | L'objet concerné, ou `null` si l'action ne porte sur aucun objet (`RECIPE_CREATE`) |
| **token** | `$token->getUser()` | Qui demande |

## Étapes

1. Créer la classe avec `php bin/console make:voter RecipeVoter`. Elle étend `Voter` et est enregistrée automatiquement grâce à l'autoconfiguration.
2. **`supports($attribute, $subject)`** : est-ce que la question me concerne ? On vérifie l'attribute **et** le type du subject.
3. **`voteOnAttribute(...)`** : on renvoie `true` (accès accordé) ou `false` (refusé).
4. On l'utilise :
   - en attribut : `#[IsGranted('RECIPE_EDIT', subject: 'recipe')]`, où `'recipe'` est le **nom de l'argument** de l'action ;
   - en PHP : `$this->denyAccessUnlessGranted('RECIPE_EDIT', $recipe)` ou `$security->isGranted(...)` ;
   - en Twig : `{% if is_granted('RECIPE_EDIT', recipe) %}`.
5. (Optionnel) Un **`AdminVoter`** qui accorde tout aux admins. Avec la stratégie `affirmative` (celle par défaut), **un seul vote favorable suffit**.

## Code minimal

```php
final class RecipeVoter extends Voter
{
    public const EDIT = 'RECIPE_EDIT';
    public const CREATE = 'RECIPE_CREATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::CREATE
            || ($attribute === self::EDIT && $subject instanceof Recipe);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => true,
            self::EDIT   => $subject->getUser() === $user,   // null-safe : pas d'auteur → false
            default      => false,
        };
    }
}
```

```php
#[Route('/{id}', name: 'edit')]
#[IsGranted(RecipeVoter::EDIT, subject: 'recipe')]
public function edit(Recipe $recipe): Response { ... }
```

```php
// Le créateur devient l'auteur, sinon personne ne pourra modifier la recette
$recipe->setUser($this->getUser());
```

## Pièges

- **Le subject peut avoir une relation `null`.** `$subject->getUser()->getId()` provoque une erreur 500 si la recette n'a pas d'auteur. **Cette erreur survient même pour un admin**, car le `RecipeVoter` est appelé avant l'`AdminVoter`. Il faut comparer les objets avec `$subject->getUser() === $user`. Grâce à l'*identity map* de Doctrine, c'est la même instance.
- **Penser à renseigner le propriétaire à la création** (`setUser($this->getUser())`). Sinon, la recette est orpheline : elle n'apparaît plus dans la liste filtrée, et personne ne peut la modifier.
- **Un Voter dont `supports()` renvoie toujours `true`** (comme l'`AdminVoter`) est interrogé pour **tous** les attributs, y compris `ROLE_USER` et `IS_AUTHENTICATED`. C'est sans danger en stratégie `affirmative`, parce qu'il ne fait qu'**ajouter** des « oui ». Avec la stratégie `unanimous`, en revanche, son « non » bloquerait tout le monde sauf les admins.
- **`in_array('ROLE_ADMIN', $user->getRoles())` ignore la `role_hierarchy`.** Il ne faut pas pour autant appeler `isGranted('ROLE_ADMIN')` depuis l'`AdminVoter` : comme il supporte tout, il s'interrogerait lui-même, ce qui provoque une **récursion infinie**. Une alternative : `$token->getRoleNames()`.
- **Ne déclarer dans `supports()` que les attributs que le Voter tranche vraiment.** Un attribut supporté mais toujours refusé ne sert à rien : s'il n'est pas supporté, le Voter s'abstient, et le résultat est le même.
- **Un `#[IsGranted]` sur une page ne protège pas le bouton dans Twig.** Il faut ajouter `is_granted()` dans le template pour ne pas afficher « Éditer » à quelqu'un qui recevrait une erreur 403.
- **Filtrer la liste (requête) et protéger l'action (Voter) sont deux choses distinctes.** Cacher les recettes des autres dans la liste n'empêche pas de taper `/admin/recettes/42` dans l'URL. C'est le Voter qui l'empêche.
- **Ce qui a changé par rapport aux anciennes versions :**
  - `voteOnAttribute()` a un 4ᵉ paramètre, `?Vote $vote`, depuis Symfony 7.3 : `$vote?->addReason('...')` explique le refus dans le Profiler.
  - `#[IsGranted]` (Symfony 6.2+) remplace l'annotation `@IsGranted` de SensioFrameworkExtraBundle, qui a été abandonné.
  - `subject:` peut aussi être une `Expression` : `subject: new Expression('args["recipe"].getCategory()')`.
