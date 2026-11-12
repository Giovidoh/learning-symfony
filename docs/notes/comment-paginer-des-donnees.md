# Comment paginer des données en Symfony

## Objectif

Afficher une longue liste, par exemple les recettes de l'administration, **par pages** : on ne charge que N lignes à la fois (`LIMIT` / `OFFSET` en SQL), et on affiche des liens vers les autres pages.

## Deux approches

| | Doctrine seul | KnpPaginatorBundle |
|---|---|---|
| Installation | Rien, c'est inclus dans l'ORM | `composer require knplabs/knp-paginator-bundle` |
| Classe | `Paginator` (**dépréciée dans ORM 3.x récent**, ta version 3.7.6 ; supprimée en 4.0), remplacée par `OffsetPaginator` | `PaginatorInterface`, injecté par autowiring |
| Liens de pages en Twig | À écrire soi-même | `knp_pagination_render()` |
| Tri par colonne | À écrire soi-même | `knp_pagination_sortable()` |
| Pour une API JSON | Bien adapté | Possible, mais pensé pour Twig |

## Étapes avec KnpPaginator

1. Installer le bundle. La recette Flex crée `config/packages/knp_paginator.yaml`. Y choisir le template `bootstrap_v5_pagination.html.twig`.
2. Dans le repository, injecter `PaginatorInterface` et lui passer un **QueryBuilder**, pas un résultat déjà chargé : c'est lui qui ajoute le `LIMIT`, l'`OFFSET` et la requête `COUNT`.
3. Dans le contrôleur, lire la page avec `$request->query->getInt('page', 1)`.
4. Dans Twig, boucler sur l'objet de pagination comme sur un tableau, puis appeler `knp_pagination_render()`.

## Code minimal

```php
// RecipeRepository
public function paginateRecipes(int $page): PaginationInterface
{
    return $this->paginator->paginate(
        $this->createQueryBuilder('r')->select('r', 'c')->leftJoin('r.category', 'c'),
        $page,
        20,
        [
            'distinct' => false,                          // pas de collection jointe
            'sortFieldAllowList' => ['r.id', 'r.title'],  // colonnes triables autorisées
        ]
    );
}
```

```twig
<th>{{ knp_pagination_sortable(recipes, 'Titre', 'r.title') }}</th>
{% for recipe in recipes %} ... {% endfor %}
{{ knp_pagination_render(recipes) }}
```

```php
// Sans bundle, avec Doctrine ORM 3.7 : OffsetPaginator remplace Paginator
$page = new OffsetPaginator(fetchJoinCollection: false)
    ->paginate($qb, Window::fromPageNumberAndSize($pageNumber, 20));
$page->getItems(); $page->getPageCount(); $page->hasNextPage();
```

## Pièges

- **`?page=0` ou `?page=-3` provoque une erreur 500.** KnpPaginator lève `PageNumberInvalidException` quand la page est inférieure à 1. **`convert_exception: true` ne règle pas ce cas** : son listener ne convertit en 404 que `InvalidValueException`, c'est-à-dire un tri interdit (`?sort=` hors de `sortFieldAllowList`). `PageNumberInvalidException` hérite de `\OutOfRangeException` et n'est pas concernée. Il faut donc valider la page dans le contrôleur : `#[MapQueryParameter(options: ['min_range' => 1])] int $page = 1` renvoie une 404 si la page est invalide, et `max(1, ...)` la ramène à 1 sans erreur.
- **Toujours définir `sortFieldAllowList`.** Sans elle, n'importe quel visiteur peut trier sur n'importe quel champ via `?sort=...`.
- **`distinct`** : à mettre à `false` quand la jointure est un `ManyToOne`, car il n'y a pas de doublons et la requête est plus simple. On le laisse à `true` dès qu'on joint une **collection** (`OneToMany`, `ManyToMany`). Sinon, une recette avec trois éléments compte pour trois lignes et la page affiche moins de résultats que prévu.
- **Sans `orderBy`, l'ordre n'est pas garanti** par la base. Une même ligne peut alors apparaître sur deux pages, ou sur aucune.
- **Joindre la catégorie** (`leftJoin` + `select('r', 'c')`) évite les requêtes N+1 dans la boucle Twig.
- **Ce qui a changé par rapport aux anciennes versions :** `Doctrine\ORM\Tools\Pagination\Paginator` est déprécié dans les versions récentes d’ORM 3 (vérifié dans ta 3.7.6). Il est remplacé par `OffsetPaginator` (pages numérotées) et `CursorPaginator` (défilement infini, très efficace sur de grosses tables). Ces deux classes ne gardent aucun état et retournent un objet `Page`. `HINT_ENABLE_DISTINCT` se trouve maintenant sur `PaginatorInterface`.
