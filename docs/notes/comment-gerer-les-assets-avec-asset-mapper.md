# Comment gérer le CSS et le JavaScript avec AssetMapper en Symfony

## Objectif

Servir le CSS, le JS et les images de l'application, et utiliser des paquets npm (comme `canvas-confetti`), **sans Node, sans npm, sans webpack**. Le navigateur charge directement les modules JavaScript grâce à une **importmap**.

## Étapes

1. `composer require symfony/asset-mapper`, déjà présent dans le squelette `webapp`. La recette crée `assets/app.js`, `assets/styles/app.css`, `importmap.php` et `config/packages/asset_mapper.yaml`.
2. Dans `base.html.twig`, `{{ importmap('app') }}` génère la balise `<script type="importmap">`, des `<link rel="modulepreload">` et charge le point d'entrée `app.js`.
3. **Le CSS s'importe depuis le JS** avec `import './styles/app.css'`. AssetMapper le transforme en `<link rel="stylesheet">`.
4. **Ajouter un paquet npm** : `php bin/console importmap:require canvas-confetti`. La commande ajoute une ligne dans `importmap.php` et télécharge le fichier dans `assets/vendor/`. Ensuite, dans le JS : `import confetti from 'canvas-confetti'`.
5. **Utiliser une image ou un fichier d'`assets/` dans Twig** : `{{ asset('images/logo.png') }}`. AssetMapper ajoute une empreinte (*hash*) au nom du fichier, ce qui permet de le mettre en cache sans risque.
6. **En production** : `php bin/console asset-map:compile` copie tous les fichiers dans `public/assets/`. En dev, Symfony les sert à la volée : **il ne faut pas compiler en dev.**

## Code minimal

```php
// importmap.php
return [
    'app' => ['path' => './assets/app.js', 'entrypoint' => true],
    'canvas-confetti' => ['version' => '1.9.4'],
];
```

```js
// assets/app.js
import './stimulus_bootstrap.js';
import './styles/app.css';
import confetti from 'canvas-confetti';
```

## Commandes utiles

| Commande | Rôle |
|---|---|
| `importmap:require <paquet>` | Ajouter un paquet |
| `importmap:remove <paquet>` | Retirer un paquet |
| `importmap:install` | Retélécharger `assets/vendor/`, qui n'est pas versionné, après un `git clone` |
| `importmap:outdated` / `importmap:update` | Voir et mettre à jour les versions |
| `importmap:audit` | Chercher des failles connues dans les paquets |
| `debug:asset-map` | Lister tous les fichiers servis et leur chemin logique |
| `asset-map:compile` | Préparer les fichiers pour la production |

## Pièges

- **Ne jamais lancer `asset-map:compile` en dev.** Les fichiers de `public/assets/` sont alors servis à la place de tes sources, et tes modifications CSS ou JS semblent ne plus rien faire. Si c'est arrivé, il suffit de supprimer `public/assets/`.
- **Les imports relatifs ont besoin de l'extension** : `import './stimulus_bootstrap.js'`, pas `'./stimulus_bootstrap'`. Le navigateur ne devine rien, contrairement à webpack.
- **`assets/vendor/` et `public/assets/` sont dans le `.gitignore`.** Après un `git clone`, il faut lancer `importmap:install`, sinon les imports de paquets échouent.
- **Pas de JSX, de TypeScript ni de Sass « natifs ».** Il faut des bundles en plus (`sensiolabs/typescript-bundle`, `symfonycasts/sass-bundle`), sinon on revient à Webpack Encore. Pour une app React ou Next.js séparée, comme dans ton futur poste, AssetMapper ne sert pas : le front a son propre outillage, et Symfony n'expose que l'API.
- **Un paquet npm qui importe du CSS** doit être ajouté explicitement, par exemple `importmap:require bootstrap/dist/css/bootstrap.min.css`.
- **Ce qui a changé par rapport aux anciennes versions :** jusqu'à Symfony 6.2, la solution par défaut était **Webpack Encore**, avec Node, `package.json` et un `npm run build`. Depuis 6.3, **AssetMapper** est la solution par défaut du squelette `webapp`. Encore existe toujours pour les projets qui ont besoin d'une vraie étape de build.
