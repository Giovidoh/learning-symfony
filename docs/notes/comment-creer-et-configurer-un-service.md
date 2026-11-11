# Comment créer et configurer un service en Symfony

## Objectif

Mettre de la logique dans une classe réutilisable (un **service**). On laisse le **container** la construire et lui fournir ses dépendances. On n'écrit jamais `new MaClasse(...)` à la main.

## Étapes

1. **Créer une classe dans `src/`.** Grâce au bloc `App\: resource: '../src/'` de `config/services.yaml`, elle devient automatiquement un service. Son identifiant est le nom complet de la classe, par exemple `App\Demo`.
2. **Déclarer les dépendances dans le constructeur.** Avec l'**autowiring**, le container lit le type de chaque argument (par exemple `ValidatorInterface`) et injecte le bon service.
3. **Configurer les valeurs qu'un type ne suffit pas à décrire.** Un `string` ou un `int` ne peut pas être deviné. Deux façons de le fournir :
   - dans `services.yaml`, avec `arguments: { $key: 'hello' }` ;
   - directement dans la classe, avec `#[Autowire('hello')]`. C'est la façon actuelle, privilégiée par la doc.
4. **Utiliser le service.** On le demande en argument d'un contrôleur, ou dans le constructeur d'un autre service : formulaire, validateur, commande…
5. **Inspecter le container :**
   - `php bin/console debug:container 'App\Demo'` affiche les arguments injectés ;
   - `php bin/console debug:autowiring validator` liste les types disponibles pour l'autowiring ;
   - `php bin/console lint:container` vérifie les types de tous les services.

## Code minimal

```php
namespace App;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class Demo
{
    public function __construct(
        private ValidatorInterface $validator,              // injecté grâce au type
        #[Autowire('hello')] private string $key,          // valeur scalaire
        #[Autowire(env: 'APP_SECRET')] private string $secret, // variable d'environnement
        #[Autowire(param: 'kernel.project_dir')] private string $dir, // paramètre du container
    ) {}
}
```

```yaml
# Équivalent en YAML (méthode historique, toujours valide)
services:
    App\Demo:
        arguments:
            $key: 'hello'
```

## Pièges

- **`class: App\Demo` est inutile** quand l'identifiant du service est déjà le nom de la classe.
- **Les services sont privés et partagés par défaut** :
  - *privés* : impossible de faire `$container->get()` dessus, il faut passer par l'injection ;
  - *partagés* : une seule instance par requête.
- **Faire `new` sur un service**, comme `new AsciiSlugger()`, empêche de le configurer ou de le remplacer dans les tests. Il vaut mieux injecter son interface, ici `SluggerInterface`.
- **Pas d'injection dans une entité.** Une entité n'est pas un service : c'est une donnée, qui est créée par Doctrine ou par toi.
- **L'autowiring a besoin d'un type unique.** S'il existe plusieurs implémentations d'une interface, il faut préciser laquelle avec `#[Target]`, ou avec un alias.
- **Ce qui a changé par rapport aux anciennes versions :**
  - Avant Symfony 3.3, il fallait déclarer chaque service à la main en YAML ou en XML.
  - `#[Autowire]` (Symfony 6.1+) permet de tout configurer dans la classe.
  - Le format XML pour configurer les services est déprécié en Symfony 7.4 et supprimé en 8.0.
