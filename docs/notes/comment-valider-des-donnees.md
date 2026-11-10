# Comment valider des données en Symfony

## Objectif

Refuser les données invalides avant qu'elles n'arrivent en base, et afficher l'erreur à côté du bon champ. Le composant **Validator** vérifie un objet, en général l'entité, à partir de contraintes qu'on déclare dessus. Le formulaire déclenche cette vérification quand on appelle `isValid()`.

## Étapes

1. **Déclarer les contraintes sur l'entité** avec des attributs `#[Assert\...]` : `Length`, `Positive`, `Regex`, `LessThan`, `NotBlank`…
2. **Interdire les doublons** avec `#[UniqueEntity('slug')]`, placé sur la **classe**. Cette contrainte interroge la base, c'est pourquoi elle vient du bridge Doctrine.
3. **Gérer les champs vides** : un champ texte vide est transmis à l'entité comme `null`. Deux solutions :
   - soit `'empty_data' => ''` dans le formulaire, avec une propriété `string $title = ''` dans l'entité ;
   - soit une propriété et un setter nullables, avec `#[Assert\NotBlank]`.
4. **Créer une contrainte personnalisée** avec `php bin/console make:validator BanWord`. La commande génère deux classes :
   - `BanWord` porte les options ;
   - `BanWordValidator` contient la logique et signale les erreurs avec `buildViolation()`.
5. **Choisir quelles contraintes s'appliquent** grâce aux **groupes** :
   - on écrit `groups: ['Extra']` sur la contrainte ;
   - on active le groupe avec `'validation_groups' => ['Default', 'Extra']` dans le formulaire.
6. **Contrôler le résultat** avec `php bin/console debug:validator 'App\Entity\Recipe'`, qui liste toutes les contraintes appliquées.

## Code minimal

```php
#[ORM\Entity]
#[UniqueEntity('slug')]
class Recipe
{
    #[ORM\Column(length: 255)]
    #[Assert\Length(min: 5)]
    #[BanWord]
    private string $title = '';

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    #[Assert\LessThan(24 * 60)]
    private ?int $duration = null;
}
```

```php
final class BanWordValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return; // une contrainte ignore le vide : c'est le rôle de NotBlank
        }
        foreach ($constraint->banWords as $banWord) {
            if (str_contains(mb_strtolower($value), $banWord)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ banWord }}', $banWord)
                    ->addViolation();
            }
        }
    }
}
```

## Pièges

- **Presque toutes les contraintes laissent passer `null`.** Une `Length(min: 5)` placée sur une propriété nullable accepte un champ vide. Pour rendre un champ obligatoire, il faut `NotBlank`.
- **`empty_data` évite une erreur 500** : sans lui, un champ vide envoie `null` à un setter qui attend un `string`.
- **Contraintes sur l'entité ou dans le formulaire ?**
  - Sur l'entité, elles s'appliquent partout : formulaire, API, commandes.
  - Dans le formulaire (option `constraints`), elles ne s'appliquent qu'à ce formulaire.
  - En règle générale, on préfère l'entité.
- **`Sequentially`** arrête la vérification à la première contrainte qui échoue. Pratique pour ne pas afficher un message de regex quand la longueur est déjà fausse.
- **`UniqueEntity` ne remplace pas un index `unique` en base.** Deux requêtes simultanées peuvent passer la vérification toutes les deux. Pour une garantie totale, il faut aussi un index unique dans la colonne Doctrine.
- **Ce qui a changé par rapport aux anciennes versions :**
  - Les annotations `@Assert\...` ont disparu.
  - En Symfony 8, on passe les options en arguments nommés, par exemple `new Length(min: 5)`. L'ancienne syntaxe en tableau, `new Length(['min' => 5])`, n'est plus acceptée : elle était dépréciée depuis 7.3.
