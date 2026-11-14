# Comment ajouter de l'interactivité avec Symfony UX (Turbo, Stimulus)

## Objectif

Rendre l'interface dynamique **sans écrire de JavaScript ni d'API** : le serveur continue de renvoyer du HTML rendu par Twig, et de petites bibliothèques JS l'injectent au bon endroit. C'est l'approche « HTML over the wire » (Hotwire).

## Les briques

| Brique | Rôle |
|---|---|
| **Stimulus** (`symfony/stimulus-bundle`) | Attache de petits contrôleurs JS au HTML avec `data-controller="..."`. Les paquets UX sont en fait des contrôleurs Stimulus. |
| **Turbo Drive** | Intercepte les liens et les formulaires, puis remplace le `<body>` sans recharger la page. Il est actif dès l'installation. |
| **Turbo Frame** | Ne remplace qu'**un morceau** de page, à l'intérieur d'un `<turbo-frame id="...">`. |
| **Turbo Stream** | Le serveur envoie des **instructions** : `remove`, `append`, `replace`… sur des éléments ciblés par leur id. |
| Paquets UX | `ux-autocomplete` (TomSelect sur un `<select>`), `ux-toggle-password` (bouton pour afficher le mot de passe), etc. |

## Étapes

1. `composer require symfony/ux-turbo`. La recette Flex met à jour `importmap.php` et `assets/controllers.json`.
2. **Turbo Frame** : entourer la zone à remplacer avec **le même id** dans la page de départ et dans la page chargée.
3. **Turbo Stream** : quand la requête accepte ce format, renvoyer un template de `<turbo-stream>` au lieu d'une redirection.
4. **Autocomplete** : ajouter `'autocomplete' => true` sur un `ChoiceType` ou un `EntityType`.
5. **Toggle password** : ajouter `'toggle' => true` sur un `PasswordType`.

## Code minimal

```twig
{# index.html.twig #}
<tr id="row_recipe_{{ recipe.id }}">
    <td><turbo-frame id="recipe_{{ recipe.id }}">
        <a href="{{ path('admin.recipe.edit', {id: recipe.id}) }}">{{ recipe.title }}</a>
    </turbo-frame></td>
</tr>

{# edit.html.twig : MÊME id #}
<turbo-frame id="recipe_{{ recipe.id }}">{{ form(form) }}</turbo-frame>
```

```php
// Contrôleur : réponse en Turbo Stream si le navigateur l'accepte, sinon redirection classique
if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
    $request->setRequestFormat(TurboBundle::STREAM_FORMAT);
    return $this->render('admin/recipe/delete.html.twig', ['recipeId' => $id]);
}
return $this->redirectToRoute('admin.recipe.index');
```

```twig
{# delete.html.twig #}
<turbo-stream action="remove" target="row_recipe_{{ recipeId }}"></turbo-stream>
<turbo-stream action="append" target="flash">
    <template><div class="alert">Supprimée</div></template>
</turbo-stream>
```

## Pièges

- **Si les id des frames diffèrent, Turbo affiche « Content missing ».** Il cherche dans la réponse un `<turbo-frame>` qui a **exactement** l'id du frame de départ et ignore tout le reste. Il faut préfixer les id (`recipe_12`) : un id composé seulement d'un nombre est fragile.
- **Tout ce qui est hors du frame est ignoré**, y compris les messages flash. Un `addFlash()` suivi d'une redirection dans un frame ne s'affiche qu'au chargement complet suivant. Pour l'afficher immédiatement, on utilise un Turbo Stream `append` sur `#flash`.
- **Après une redirection, le frame doit exister sur la page d'arrivée.** Une recette de la page 2 éditée dans son frame, puis redirigée vers la page 1, affichera « Content missing ».
- **Avec Turbo Drive, un formulaire invalide doit renvoyer 422**, sinon Turbo n'affiche pas les erreurs. `$this->render(..., ['form' => $form])` le fait automatiquement (Symfony 6.2+). Un succès doit **rediriger** (`redirectToRoute`).
- **Le format stream n'est envoyé que pour les soumissions de formulaire** (POST/DELETE). Turbo ajoute `text/vnd.turbo-stream.html` dans l'en-tête `Accept`. Sans JavaScript, le navigateur reçoit la redirection classique : il faut garder les deux chemins.
- **Les scripts inline ne se relancent pas** quand Turbo Drive change de page, puisqu'il n'y a pas de rechargement. Le code JS se met dans un contrôleur Stimulus (`connect()`), pas dans un `DOMContentLoaded`.
- **`ux_turbo.yaml` active `csrf_protection.check_header`** : les soumissions faites par Turbo envoient le jeton CSRF dans un en-tête.
- **Ce qui a changé par rapport aux anciennes versions :**
  - Webpack Encore et `npm` ne sont plus nécessaires : les paquets UX passent par **AssetMapper** et `importmap.php`.
  - `symfony/ux-turbo` 2.x est passé en **3.x**.
  - Le jeton CSRF du login est désormais **stateless** : le contrôleur Stimulus `csrf-protection` génère le jeton côté navigateur, et le serveur vérifie l'en-tête `Origin` (Symfony 7.2+).
