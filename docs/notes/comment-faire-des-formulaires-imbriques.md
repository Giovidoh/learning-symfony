# Comment faire des formulaires imbriqués (CollectionType) en Symfony

## Objectif

Éditer **une entité et sa liste d'enfants dans un même formulaire**. Exemple : une recette et ses ingrédients avec leurs quantités. On peut ajouter ou retirer des lignes côté navigateur, et tout est enregistré en un seul `flush()`.

## Le modèle

Une relation ManyToMany « avec données » (la quantité, l'unité) devient une **entité intermédiaire** :

```
Recipe 1 ── n Quantity n ── 1 Ingredient
               (quantity, unit)
```

## Étapes

1. `make:entity Ingredient`, puis `make:entity Quantity` avec deux `ManyToOne` (`recipe`, `ingredient`), puis une migration.
2. Côté `Recipe::$quantities`, ajouter `cascade: ['persist']` (les nouvelles quantités sont enregistrées avec la recette) et `orphanRemoval: true` (une ligne retirée du formulaire est supprimée en base).
3. Créer `QuantityType` (`quantity`, `unit`, `ingredient` en `EntityType`) avec `data_class: Quantity::class`.
4. Dans `RecipeType`, ajouter un `CollectionType` avec :
   - `entry_type: QuantityType::class` ;
   - `allow_add` et `allow_delete` ;
   - **`by_reference: false`**.
5. Côté navigateur, un contrôleur Stimulus :
   - ajoute une ligne en clonant `data-prototype` et en remplaçant `__name__` par un index ;
   - supprime une ligne en retirant l'élément du DOM.

## Code minimal

```php
->add('quantities', CollectionType::class, [
    'entry_type'    => QuantityType::class,
    'entry_options' => ['label' => false],
    'allow_add'     => true,
    'allow_delete'  => true,
    'by_reference'  => false,  // Symfony appelle addQuantity()/removeQuantity()
    'attr' => ['data-controller' => 'form-collection'],
])
```

```php
#[ORM\OneToMany(targetEntity: Quantity::class, mappedBy: 'recipe', orphanRemoval: true, cascade: ['persist'])]
private Collection $quantities;
```

```js
// Index unique : max des index existants + 1, et non le nombre d'enfants
connect() {
    const indexes = [...this.element.querySelectorAll('[name*="[quantities]["]')]
        .map(i => parseInt(i.name.match(/\[quantities\]\[(\d+)\]/)[1]));
    this.index = indexes.length ? Math.max(...indexes) + 1 : 0;
}
```

## Pièges

- **Sans `by_reference: false`**, Symfony modifie directement la collection, sans appeler `addQuantity()`. Le lien `$quantity->setRecipe($recipe)` n'est alors jamais fait, et le `flush()` échoue sur `recipe_id NOT NULL`.
- **Sans `cascade: ['persist']`**, une nouvelle ligne provoque l'erreur « A new entity was found through the relationship… ».
- **Sans `orphanRemoval: true`**, une ligne supprimée dans le formulaire reste en base, ou provoque une erreur de clé étrangère.
- **Un setter non nullable plante AVANT la validation.** Avec `setUnit(string $unit)`, un champ vidé envoie `null`, ce qui provoque une `InvalidTypeException` et une erreur 500. Il faut `'empty_data' => ''` sur le champ, puis `#[Assert\NotBlank]` pour afficher un message.
- **Un setter nullable laisse passer `null` jusqu'à la base.** Avec `setIngredient(?Ingredient)` et sans contrainte, un ingrédient vide arrive au `flush()`, et l'erreur de clé étrangère donne une 500. Il faut `#[Assert\NotNull]` sur la propriété. L'attribut HTML `required` du navigateur ne protège pas le serveur.
- **La validation est bien appliquée aux enfants** : `#[Assert\Positive]` sur `Quantity::$quantity` renvoie une 422 sans rien ajouter, car le Form valide chaque sous-formulaire qui a un `data_class`. En dehors d'un formulaire, avec le Validator seul ou `MapRequestPayload`, il faut `#[Assert\Valid]` sur `$quantities`.
- **L'index JS ne doit pas être le nombre d'enfants.** Après une suppression suivie d'une erreur de validation, Symfony réaffiche les lignes **avec leurs index d'origine**, par exemple uniquement `[1]`. Un `childElementCount` qui vaut 1 donne alors une nouvelle ligne `[1]` qui **écrase** l'existante. Il faut prendre le maximum des index + 1.
- **Utiliser `this.element.children` plutôt que `childNodes`** : `childNodes` contient aussi les nœuds texte (les espaces), et `append()` n'existe pas sur un nœud texte.
- **`orphanRemoval` côté `Ingredient::$quantities`** veut dire que retirer une quantité de la collection d'un ingrédient la **supprime**. Ce n'est en général pas l'intention : on le met seulement du côté du parent « propriétaire », ici la recette.
- **Ce qui a changé par rapport aux anciennes versions :**
  - Le JavaScript se trouve dans un contrôleur Stimulus chargé par AssetMapper, et non plus dans du jQuery inline.
  - `cascade_validation`, supprimé en Symfony 3, n'est plus nécessaire dans un formulaire.
  - Symfony UX propose aussi `symfony/ux-live-component` avec `LiveCollectionType`, qui gère l'ajout et la suppression de lignes côté serveur, sans JS à écrire.
