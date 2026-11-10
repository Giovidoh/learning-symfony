# Comment faire un CRUD avec les formulaires en Symfony

## Objectif

Créer, modifier et supprimer une entité Doctrine depuis des pages HTML, en laissant le composant **Form** faire le travail :
- lire la requête,
- remplir l'entité,
- valider,
- protéger contre les attaques CSRF.

## Étapes

1. **Générer la classe de formulaire** avec `php bin/console make:form RecipeType Recipe`.
   - Elle décrit les champs, et `data_class` la relie à l'entité.
   - Supprime les champs que l'utilisateur ne doit pas saisir, comme `createdAt` et `updatedAt`.
2. **Dans le contrôleur**, suivre toujours le même cycle :
   - `createForm()` crée le formulaire,
   - `handleRequest($request)` le remplit avec les données envoyées,
   - `if (isSubmitted() && isValid())` vérifie la soumission,
   - on enregistre, on ajoute un message flash, puis on redirige.
3. **Récupérer l'entité directement en argument.** Avec `edit(Recipe $recipe)`, l'*EntityValueResolver* trouve l'entité grâce à `{id}` dans l'URL, ou renvoie une 404 s'il ne la trouve pas.
4. **Afficher le formulaire** dans le template avec `{{ form(form) }}`, ou plus finement avec `form_start`, `form_row` et `form_end`.
5. **Afficher les messages flash** dans le layout avec `app.flashes`.
6. **Supprimer** avec un petit `<form method="post">` qui contient :
   - un champ caché `_method=DELETE`, qui demande `framework.http_method_override: true`,
   - un **token CSRF**.

## Code minimal

```php
#[Route('/recettes/{id}/edit', name: 'recipe.edit', methods: ['GET', 'POST'])]
public function edit(Recipe $recipe, Request $request, EntityManagerInterface $em): Response
{
    $form = $this->createForm(RecipeType::class, $recipe);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush(); // pas de persist() : l'entité est déjà suivie par Doctrine
        $this->addFlash('success', 'Recette modifiée');

        return $this->redirectToRoute('recipe.index');
    }

    return $this->render('recipe/edit.html.twig', ['recipe' => $recipe, 'form' => $form]);
}
```

```twig
{# Suppression : formulaire HTML à la main, avec un token CSRF #}
<form action="{{ path('recipe.delete', {id: recipe.id}) }}" method="post">
    <input type="hidden" name="_method" value="DELETE">
    <input type="hidden" name="_token" value="{{ csrf_token('delete-recipe-' ~ recipe.id) }}">
    <button>Supprimer</button>
</form>
```

## Pièges

- **`persist()` ou `flush()` seul ?**
  - Pour une **nouvelle** entité, il faut `persist()` puis `flush()`.
  - Pour une entité **chargée depuis la base**, `flush()` suffit, car l'Unit of Work de Doctrine la suit déjà.
- **Toujours rediriger après un POST réussi** (pattern Post/Redirect/Get). Sinon, un F5 renvoie le formulaire.
- **Les formulaires HTML ne connaissent que GET et POST.**
  - Pour envoyer un `DELETE`, il faut `_method` et `http_method_override: true`.
  - Cette option est désactivée par défaut depuis Symfony 7.
- **Les formulaires créés avec `createForm()` ont leur token CSRF automatiquement**, mais pas un `<form>` écrit à la main. Il faut alors vérifier le token :
  - soit avec `$this->isCsrfTokenValid()`,
  - soit avec l'attribut `#[IsCsrfTokenValid]` (Symfony 7.1 et plus).
- **Ordre des routes :** une route générique comme `/recettes/{slug}` peut capturer `/recettes/create`. Les `requirements` évitent ce conflit.
- **Ce qui a changé par rapport aux anciennes versions :**
  - `{{ form(form) }}` accepte directement l'objet `$form` : `createView()` n'est plus nécessaire depuis Symfony 6.2.
  - `@ParamConverter` (SensioFrameworkExtraBundle) est remplacé par l'*EntityValueResolver* et `#[MapEntity]`.
