# Comment protéger une API avec un token (authenticator stateless) en Symfony

## Objectif

Identifier le client d'une API (un front Next.js, une app mobile) **à chaque requête**, grâce à un en-tête `Authorization: Bearer <token>`, **sans session ni cookie** : c'est le mode *stateless*.

## Étapes

1. **Ajouter un champ `apiToken` à `User`** avec `make:entity`, puis faire une migration.
2. **Déclarer un provider** qui retrouve l'utilisateur par ce champ : un provider `entity` avec `property: apiToken`.
3. **Déclarer un firewall `api`** placé **avant** `main` (le premier qui correspond gagne), avec `pattern: ^/api/` et `stateless: true`.
4. **Écrire l'authenticator** (avec `make:security:custom`, ou en étendant `AbstractAuthenticator`) :
   - `supports()` : l'en-tête `Authorization` commence-t-il par `Bearer ` ?
   - `authenticate()` : renvoie un `SelfValidatingPassport(new UserBadge($token))`. Il n'y a pas de mot de passe à vérifier, le token suffit.
   - `onAuthenticationSuccess()` renvoie `null` : la requête continue jusqu'au contrôleur.
   - `onAuthenticationFailure()` renvoie une `JsonResponse` avec le code 401.
5. **Protéger les routes.** L'authenticator **identifie**, mais il **n'interdit rien**. Il faut ajouter `#[IsGranted('ROLE_USER')]` sur les actions qui écrivent, ou un `access_control` sur `^/api`.

## Code minimal

```yaml
firewalls:
    api:
        pattern: ^/api/
        stateless: true
        provider: api_user_provider
        custom_authenticator: App\Security\APIAuthenticator
```

```php
#[Route('/api/recipes', methods: ['POST'])]
#[IsGranted('ROLE_USER')]   // sans token valide → 401
public function create(...) { ... }
```

### La version intégrée de Symfony (depuis 6.2) : `access_token`

Pas besoin d'écrire d'authenticator. On écrit seulement un *handler* qui transforme le token en identifiant d'utilisateur :

```yaml
api:
    pattern: ^/api/
    stateless: true
    access_token:
        token_handler: App\Security\ApiTokenHandler
```

```php
final class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private UserRepository $users) {}

    public function getUserBadgeFrom(string $token): UserBadge
    {
        $user = $this->users->findOneBy(['apiToken' => $token])
            ?? throw new BadCredentialsException('Invalid token.');
        return new UserBadge($user->getUserIdentifier());
    }
}
```

`access_token` lit déjà l'en-tête `Bearer`, renvoie 401 avec un en-tête `WWW-Authenticate`, et sait aussi valider des **JWT/OIDC** (`token_handler: oidc`).

## Pièges

- **Sans `#[IsGranted]` ni `access_control`, l'API reste ouverte.** Une requête **sans** en-tête `Authorization` n'est pas refusée : `supports()` renvoie `false` et l'utilisateur est simplement **anonyme**.
- **Le firewall `api` doit être déclaré avant `main`**, sinon `main` prend aussi les URL en `/api/`.
- **Le token doit être long, aléatoire et unique** : `bin2hex(random_bytes(32))`, avec `unique: true` sur la colonne. Un token de 2 ou 7 caractères se devine en quelques secondes.
- **Idéalement, on stocke un hash du token** (comme pour un mot de passe), pour qu'une fuite de la base ne donne pas accès à l'API. On donne le token en clair une seule fois, à sa création.
- **`str_contains($header, 'Bearer ')`** accepte aussi `xxxBearer abc`. Utiliser `str_starts_with`. De même, `str_replace` remplace toutes les occurrences : `substr($header, 7)` est plus juste.
- **`stateless: true`** : pas de session. `$this->getUser()` fonctionne quand même, mais seulement pour la requête en cours.
- **Ce qui a changé par rapport aux anciennes versions :**
  - Le système Guard et `getCredentials()`/`checkCredentials()` ont été remplacés par les authenticators à `Passport` (Symfony 5.3+). `SelfValidatingPassport` sert quand il n'y a pas de mot de passe.
  - Depuis 6.2, l'authenticator intégré `access_token` rend la classe personnalisée inutile dans la plupart des cas.
  - Pour un front Next.js, on utilise souvent des **JWT** (`lexik/jwt-authentication-bundle`) ou un **cookie de session HttpOnly**, si le front et l'API sont sur le même domaine.
