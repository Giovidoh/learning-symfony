# Comment faire des requêtes Doctrine avancées en Symfony (select partiel, DTO, DQL)

## Objectif

Ne récupérer **que ce dont la page a besoin**, par exemple la liste des catégories avec leur nombre de recettes, en **une seule requête**, sans charger toutes les entités liées.

## Étapes

1. **DQL** (Doctrine Query Language) : c'est du SQL qui parle en **entités et propriétés**, pas en tables et colonnes. On l'écrit soit en chaîne avec `createQuery()`, soit avec le `QueryBuilder`, qui produit le même DQL.
2. **Select partiel** : `->select('c.id', 'c.name')` renvoie des **tableaux**, pas des entités. C'est plus léger, mais ce n'est pas typé.
3. **Mélanger une entité et une valeur calculée** (`select('c as category', 'COUNT(r.id) as total')`) renvoie aussi des tableaux, de la forme `['category' => Category, 'total' => 5]`.
4. **DTO avec `NEW`** : `SELECT NEW App\DTO\MaClasse(...)` appelle le **constructeur** pour chaque ligne. Le résultat est un tableau d'objets typés, lisibles dans Twig comme une entité.

## Code minimal

```php
final class CategoryWithCountDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $count,
    ) {}
}
```

```php
/** @return CategoryWithCountDTO[] */
public function findAllWithCount(): array
{
    return $this->createQueryBuilder('c')
        ->select(sprintf('NEW %s(c.id, c.name, COUNT(r.id))', CategoryWithCountDTO::class))
        ->leftJoin('c.recipes', 'r')
        ->groupBy('c.id')
        ->orderBy('c.name')
        ->getQuery()
        ->getResult();
}
```

Pour tester une requête sans passer par le navigateur :

```
php bin/console doctrine:query:dql "SELECT NEW App\DTO\CategoryWithCountDTO(c.id, c.name, COUNT(r.id)) FROM App\Entity\Category c LEFT JOIN c.recipes r GROUP BY c.id"
```

## Pièges

- **`COUNT(c.id)` au lieu de `COUNT(r.id)`.** Avec un `LEFT JOIN`, une catégorie sans recette garde **une** ligne où `r.id` vaut `NULL`. `COUNT` ignore les `NULL`, donc il faut compter une colonne **du côté joint**. Sinon, on obtient 1 au lieu de 0.
- **`leftJoin` ou `innerJoin`** : `innerJoin` fait disparaître les catégories qui n'ont aucune recette.
- **L'ordre des arguments de `NEW` doit suivre celui du constructeur**, et les types doivent correspondre. Les arguments sont **positionnels** : en inverser deux casse tout. Dans les versions récentes d’ORM 3, `NEW NAMED Dto(c.id AS id, c.name AS name, COUNT(r.id) AS count)` passe les valeurs **par nom**, comme les arguments nommés de PHP. L'ordre n'a alors plus d'importance (vérifié dans ta 3.7.6).
- **Le nom complet de la classe dans une chaîne PHP** : on écrit `\\` entre guillemets doubles. `sprintf('NEW %s(...)', Dto::class)` évite cette erreur et suit les renommages de l'IDE.
- **Un DTO n'est pas une entité.** Il n'est pas suivi par l'EntityManager : le modifier puis faire `flush()` n'enregistre rien. C'est pour **lire**, pas pour écrire.
- **`GROUP BY` + ordre** : sans `orderBy`, l'ordre des lignes n'est pas garanti.
- **Ce qui a changé par rapport aux anciennes versions :**
  - Les objets partiels (`PARTIAL c.{id, name}`) ont été retirés en ORM 3.0, puis **réintroduits** dans une version 3.x : la syntaxe existe dans ta 3.7.6. Ils restent risqués, car ils donnent une entité incomplète que l'on peut `flush()` par erreur. Les DTO avec `NEW` sont l'approche recommandée.
  - Les DTO serviront aussi de base aux réponses JSON de l'API.
