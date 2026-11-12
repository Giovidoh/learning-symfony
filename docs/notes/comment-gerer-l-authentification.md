# Comment gérer l'inscription et la connexion en Symfony

## Objectif

Permettre aux utilisateurs de **s'inscrire**, de **se connecter** et de **se déconnecter**, puis **réserver certaines pages** aux utilisateurs connectés, ou à ceux qui ont un rôle précis.

## Étapes

1. **`make:user`** crée l'entité `User`, qui implémente `UserInterface` et `PasswordAuthenticatedUserInterface`. La commande configure aussi le *provider* dans `security.yaml` : c'est lui qui recharge l'utilisateur à chaque requête.
2. Générer la migration avec `make:migration`, puis l'appliquer.
3. **La connexion :**
   - **Symfony 8 / MakerBundle 1.59+ : `make:security:form-login`.** La commande génère le contrôleur `/login` et son template. Elle utilise l'authenticator **intégré** `form_login` du firewall, donc il n'y a pas de classe à écrire.
   - `make:auth` est **déprécié**. Il générait une classe `AppAuthenticator` personnalisée. On ne passe par une classe personnalisée (`make:security:custom`) que si on a besoin d'une logique particulière.
4. **L'inscription : `make:registration-form`.** La commande génère le formulaire, le contrôleur et, en option, la vérification de l'adresse e-mail avec `symfonycasts/verify-email-bundle`.
5. **Protéger les pages**, de trois façons possibles :
   - `#[IsGranted('ROLE_ADMIN')]` sur une classe de contrôleur ou sur une méthode ;
   - `access_control` dans `security.yaml`, pour protéger un préfixe d'URL entier ;
   - `$this->denyAccessUnlessGranted()` directement dans le code.
6. **Dans Twig :** `is_granted('ROLE_ADMIN')` pour tester un rôle, et `app.user` pour accéder à l'utilisateur connecté.

## Code minimal

```yaml
# security.yaml : connexion par formulaire sans classe personnalisée
firewalls:
    main:
        provider: app_user_provider
        form_login:
            login_path: app_login
            check_path: app_login
            enable_csrf: true
        logout:
            path: app_logout
```

```php
// Connexion par e-mail OU nom d'utilisateur, sans authenticator personnalisé :
// le repository implémente UserLoaderInterface, et on retire `property:` du provider
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function loadUserByIdentifier(string $identifier): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :id OR u.username = :id')
            ->setParameter('id', $identifier)
            ->getQuery()
            ->getOneOrNullResult(); // pas getSingleResult()
    }
}
```

## Où retrouver ce que faisait `AppAuthenticator` avec `form_login`

| Dans `AppAuthenticator` (`make:auth`) | Avec `form_login` (`make:security:form-login`) |
|---|---|
| `getPayload()->getString('username')` et `'password'` | `username_parameter` et `password_parameter` (par défaut `_username` et `_password`) |
| `UserBadge` avec un chargeur personnalisé (e-mail ou nom d'utilisateur) | Provider `entity` **sans** `property`, avec un repository qui implémente `UserLoaderInterface` |
| `PasswordCredentials` | Automatique |
| `CsrfTokenBadge('authenticate', ...)` | `enable_csrf: true` (**désactivé par défaut**), avec `csrf_parameter` et `csrf_token_id` |
| `RememberMeBadge` | Clé `remember_me:` du firewall. `form_login` ajoute le badge tout seul. |
| Session `LAST_USERNAME` | Automatique : `AuthenticationUtils::getLastUsername()` fonctionne de la même façon |
| `getLoginUrl()` | `login_path` |
| URL qui reçoit le formulaire | `check_path` |
| `onAuthenticationSuccess()` avec `TargetPathTrait` | Automatique, avec `default_target_path`, `always_use_default_target_path` et `use_referer` |
| Échec de connexion | `failure_path` (par défaut, retour à `login_path`) |
| Toute autre logique de succès ou d'échec | Un listener sur `LoginSuccessEvent` ou `LoginFailureEvent`, ou bien `success_handler` / `failure_handler` |
| Refuser un compte non vérifié | `user_checker:` sur le firewall, avec une classe qui implémente `UserCheckerInterface` |

Pour voir toutes les options : `php bin/console config:dump-reference security`.

## Pièges

- **`getSingleResult()` lève une exception quand il n'y a aucun résultat.** Dans un *user loader*, une connexion avec un identifiant inconnu provoque alors une **erreur 500** au lieu du message « Invalid credentials ». Il faut utiliser `getOneOrNullResult()` : `UserBadge` transforme le `null` en `UserNotFoundException`.
- **`ROLE_USER` est donné à tout le monde** par `getRoles()`. Si l'inscription est ouverte, protéger l'administration avec `ROLE_USER` revient à la donner à n'importe quel visiteur. Il faut `ROLE_ADMIN`.
- **`isVerified` ne bloque rien tout seul.** Pour refuser la connexion d'un compte non vérifié, il faut un **UserChecker**.
- **Ne jamais enregistrer le mot de passe en clair.** Le champ `plainPassword` a `'mapped' => false`, et on enregistre `hashPassword()`. Avec `'auto'`, Symfony choisit le meilleur algorithme disponible et met à jour les anciens hashes grâce à `PasswordUpgraderInterface`.
- **Le CSRF est « stateless » depuis Symfony 7.2** (`csrf.yaml`, `stateless_token_ids`). Il s'appuie sur les en-têtes `Origin` et `Referer` plutôt que sur la session.
- **Ce qui a changé par rapport aux anciennes versions :**
  - **Guard** (Symfony ≤ 5.3) a été remplacé par les **authenticators**, avec `Passport` et des *badges*.
  - `make:auth` a été remplacé par `make:security:form-login` et `make:security:custom`.
  - `User::__serialize()` utilise un hash CRC32C du mot de passe pour ne pas stocker le vrai hash en session (Symfony 7.3+).
