# Comment remplir la base avec des fixtures en Symfony

## Objectif

Remplir la base de **fausses données réalistes** (utilisateurs, catégories, recettes) en une commande, pour développer, faire des démonstrations et lancer les tests sur une base connue. On n'insère plus rien à la main dans phpMyAdmin.

## Étapes

1. Installer les paquets **en dev uniquement** : `composer require --dev orm-fixtures fakerphp/faker`.
2. Créer une classe de fixtures avec `make:fixtures RecipeFixtures`. Elle étend `Fixture` et contient une méthode `load(ObjectManager $manager)`.
3. Créer les objets, les `persist()`, puis faire **un seul `flush()`** à la fin.
4. **Partager des objets entre fixtures** :
   - `$this->addReference('USER1', $user)` dans l'une ;
   - `$this->getReference('USER1', User::class)` dans l'autre ;
   - et `DependentFixtureInterface::getDependencies()` pour fixer l'**ordre** de chargement.
5. Lancer `php bin/console doctrine:fixtures:load`. La commande **vide toute la base** puis la recharge. L'option `--append` ajoute les données sans vider.
6. **Les services s'injectent dans le constructeur** comme partout, car les fixtures sont des services : `UserPasswordHasherInterface`, `SluggerInterface`…

## Code minimal

```php
class RecipeFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly SluggerInterface $slugger) {}

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $faker->addProvider(new \FakerRestaurant\Provider\fr_FR\Restaurant($faker));

        for ($i = 1; $i <= 10; $i++) {
            $title = $faker->unique()->foodName();          // unique() : pas de doublon
            $recipe = new Recipe()
                ->setTitle($title)
                ->setSlug($this->slugger->slug($title)->lower())
                ->setUser($this->getReference('USER' . $faker->numberBetween(1, 10), User::class));
            $manager->persist($recipe);
        }
        $manager->flush();
    }

    public function getDependencies(): array { return [UserFixtures::class]; }
}
```

## Pièges

- **Les fixtures ne passent PAS par le Validator.** `UniqueEntity`, `Length`, `Regex`… ne sont pas vérifiés, et la base peut contenir des données que tes propres formulaires refuseront ensuite à l'édition. Il faut générer des données **conformes à tes contraintes** :
  - `unique()` contre les doublons ;
  - `->lower()` pour les slugs ;
  - une longueur minimale respectée.
- **`randomElement([1, 10])`** tire 1 **ou** 10, pas une valeur entre les deux. Pour un intervalle, utiliser `numberBetween(1, 10)`.
- **`slug()` renvoie un objet `UnicodeString`**, pas une chaîne, et **ne met pas en minuscules**. Il faut ajouter `->lower()`, et `->toString()` si le setter attend un `string` en mode strict.
- **La locale du provider détermine la langue** : `vi_VN` donne des plats vietnamiens courts (« Mì », « Xôi »).
- **`doctrine:fixtures:load` supprime toutes les données.** C'est voulu en dev, mais il ne faut jamais le lancer sur une base qui contient de vraies données.
- **Des dates aléatoires indépendantes** peuvent donner un `updatedAt` antérieur au `createdAt`. On peut utiliser `$faker->dateTimeBetween($createdAt)`.
- **Les tokens et mots de passe des fixtures** (`admin`, `0000`, `user1`) sont pratiques en dev. Le bundle de fixtures étant en `require-dev`, ils n'existent pas en production.
- **Ce qui a changé par rapport aux anciennes versions :**
  - `getReference()` **exige maintenant la classe** en deuxième argument (DoctrineFixturesBundle 4 / data-fixtures 2).
  - `ContainerAwareInterface` a été supprimé : on injecte les services par le constructeur.
  - `fzaninotto/faker`, abandonné, a été remplacé par `fakerphp/faker`.
