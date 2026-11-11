# Comment faire une relation ManyToOne en Symfony

## Objectif

Relier deux entités : **plusieurs** recettes appartiennent à **une** catégorie.

## Étapes

1. Lancer `php bin/console make:entity Recipe`, ajouter un champ `category` de type `relation`, choisir `ManyToOne` vers `Category`, et accepter le côté inverse `Category::$recipes`.
2. Générer la migration avec `make:migration`. Elle ajoute la colonne `category_id` et la **clé étrangère**. Relire la migration, puis lancer `doctrine:migrations:migrate`.
3. Dans le formulaire, ajouter un champ `EntityType` avec `'class' => Category::class` et `'choice_label' => 'name'`. Il affiche une liste déroulante remplie depuis la base.
4. Dans Twig, la catégorie s'affiche avec `{{ recipe.category.name|default('') }}`. Le `default` évite une erreur quand la recette n'a pas de catégorie.

## Code minimal

```php
// Recipe : côté propriétaire, c'est lui qui porte la colonne category_id
#[ORM\ManyToOne(inversedBy: 'recipes')]
private ?Category $category = null;

// Category : côté inverse, aucune colonne en base
#[ORM\OneToMany(targetEntity: Recipe::class, mappedBy: 'category')]
private Collection $recipes;
```

## Pièges

- **Seul le côté propriétaire (`ManyToOne`) est enregistré.** Modifier uniquement `$category->getRecipes()` ne change rien en base. C'est pour ça que `make:entity` génère `addRecipe()`, qui appelle aussi `$recipe->setCategory($this)`.
- **`cascade: ['remove']` sur `OneToMany`** : supprimer une catégorie **supprime toutes ses recettes**. C'est souvent trop brutal. Il existe deux alternatives :
  - `#[ORM\JoinColumn(onDelete: 'SET NULL')]` : les recettes perdent leur catégorie mais sont conservées ;
  - interdire la suppression d'une catégorie qui contient encore des recettes.
- **`cascade: ['persist']` sur `ManyToOne`** : quand on enregistre une recette, Doctrine enregistre aussi une catégorie **nouvelle** qui lui serait rattachée. C'est pratique, mais ça peut créer des catégories sans qu'on s'en rende compte.
- **Requêtes N+1** : afficher `recipe.category.name` dans une boucle déclenche une requête SQL par recette. On le voit dans l'onglet Doctrine du Profiler. La solution est de faire une jointure dans le repository : `->leftJoin('r.category', 'c')->addSelect('c')`.
- **Ce qui a changé par rapport aux anciennes versions :** les annotations `@ORM\ManyToOne` ont été remplacées par des attributs, et `targetEntity` est déduit du type PHP de la propriété.
