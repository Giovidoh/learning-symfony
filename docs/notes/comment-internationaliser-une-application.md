# Comment internationaliser une application en Symfony

## Objectif

Afficher l'application dans plusieurs langues. Il faut traiter trois choses :

- **les textes de l'interface** (titres, labels, messages), avec le composant Translation ;
- **la langue courante** (la *locale*), qui vient de l'URL, de l'utilisateur ou du navigateur ;
- **les données en base** (le titre d'une recette), avec l'extension Gedmo Translatable.

## Étapes

1. **Configurer** `config/packages/translation.yaml` : `default_locale`, `fallbacks` et, de préférence, `enabled_locales`.
2. **Écrire les fichiers de traduction** `translations/messages.{locale}.yaml`. Pour les pluriels et les variables, utiliser le format ICU dans `messages+intl-icu.{locale}.yaml`.
3. **Traduire** dans Twig avec `'clé'|trans({...})`, dans PHP avec `TranslatorInterface::trans()`. Les labels de formulaire sont traduits automatiquement : il suffit de donner la clé comme `label`.
4. **Mettre la locale dans l'URL** : préfixe `{_locale}` sur les routes. Le `LocaleListener` de Symfony lit `_locale` et appelle `$request->setLocale()`.
5. (Option) **Locale de l'utilisateur** : un listener sur `RequestEvent` qui appelle `LocaleSwitcher::setLocale($user->getLocale())`.
6. **Contenu en base** : `stof/doctrine-extensions-bundle`, `#[Gedmo\Translatable]` sur les champs, puis une **migration** pour créer la table `ext_translations`.

## Code minimal

```yaml
# config/packages/translation.yaml
framework:
    default_locale: fr
    enabled_locales: [fr, en]     # seules ces langues sont chargées
    translator:
        fallbacks: [en]           # si une clé manque en fr
```

```yaml
# config/routes.yaml : préfixe sur les pages HTML seulement
controllers:
    resource: { path: ../src/Controller/, namespace: App\Controller }
    type: attribute
    exclude: ../src/Controller/API/
    prefix: /{_locale}
    requirements: { _locale: fr|en }

api:
    resource: { path: ../src/Controller/API/, namespace: App\Controller\API }
    type: attribute               # l'API reste sur /api/..., sans langue

root:                             # "/" redirige vers la page d'accueil, avec la locale par défaut
    path: /
    controller: Symfony\Bundle\FrameworkBundle\Controller\RedirectController
    defaults: { route: home }
```

```yaml
# translations/messages+intl-icu.fr.yaml
home_recipe_count: >-
    {count, plural, =0 {Aucune recette} =1 {Une recette} other {# recettes}}
welcome: Bonjour {name}
```

```twig
{{ 'welcome'|trans({name: app.user.username}) }}
{{ 'home_recipe_count'|trans({count: recipes|length}) }}
<a href="{{ path(app.current_route, app.current_route_parameters|merge({_locale: 'en'})) }}">EN</a>
```

```php
// Champ traduit en base
#[ORM\Column(length: 255)]
#[Gedmo\Translatable]
private string $title = '';

// Requête DQL / QueryBuilder : il faut le walker pour récupérer la traduction
$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, TranslationWalker::class)
      ->setHint(TranslatableListener::HINT_FALLBACK, 1);
```

## Pièges

- **Le préfixe `{_locale}` change toutes les URL.** Tout ce qui repose sur un chemin écrit en dur casse :
  - un firewall `pattern: ^/api/` ne voit plus `/fr/api/...` : l'API tombe dans le firewall `main` et redirige vers `/login` ;
  - une règle `access_control` sur `^/admin` ;
  - une `RedirectResponse('/')` : `/` n'a plus de route, donc 404.

  Il faut **générer les URL par nom de route** (`generate('home')`), pas par chemin.
- **Une API ne se traduit pas par l'URL.** Le client envoie l'en-tête `Accept-Language`, ou bien l'API renvoie des données brutes et le front (Next.js) gère la langue. On exclut donc les contrôleurs API du préfixe.
- **Les `requirements` doivent correspondre aux langues réellement traduites.** Accepter `de` sans fichier `messages.de.yaml` affiche le fallback anglais sous une URL allemande.
- **Hors requête (worker, commande)**, le routeur remplit `_locale` avec la `default_locale`. La génération d'URL fonctionne donc, mais toujours dans la langue par défaut. Il faut passer `_locale` explicitement si le message en dépend.
- **Après l'ajout ou la modification d'un fichier de traduction**, il faut parfois vider le cache : `php bin/console cache:clear`.
- `debug:translation fr` liste les clés manquantes ou inutilisées.
  `translation:extract fr --force` génère les clés trouvées dans le code.
- **Gedmo Translatable** stocke la langue par défaut dans la table de l'entité, et les **autres** langues dans `ext_translations`. Un `find()` est traduit automatiquement. Une requête DQL ne l'est **qu'avec** le `TranslationWalker`.
- **Un listener « locale de l'utilisateur »** prend le pas sur la langue de l'URL. Il faut choisir une seule source de vérité, ou ne l'appliquer que si l'URL n'impose rien.
- **Ce qui a changé par rapport aux anciennes versions :**
  - `LocaleSwitcher` (6.1+) change la locale partout d'un coup : traducteur, routeur, requête.
  - `enabled_locales` (5.1+) limite les catalogues chargés.
  - `TranslatableMessage` / `t()` (5.2+) permet de traduire un message plus tard, par exemple dans un flash.
  - Le format ICU (`+intl-icu`) est recommandé à la place de l'ancienne syntaxe `transchoice`, qui a été supprimée.
