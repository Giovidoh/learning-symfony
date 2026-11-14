# Comment traiter des tâches en asynchrone avec Messenger en Symfony

## Objectif

Sortir les **tâches lentes ou fragiles** de la requête HTTP : envoi d'e-mails, génération de PDF, appels à une API externe… Le contrôleur dépose un **message** dans une file d'attente et répond tout de suite. Un **worker** (un processus en ligne de commande) traite les messages en arrière-plan, et réessaie en cas d'échec.

## Le vocabulaire

| Terme | Rôle |
|---|---|
| **Message** | Un objet simple qui décrit la tâche : `new RecipePDFMessage($id)`. Il ne contient que des données, pas de services ni d'entités. |
| **Handler** | Le service qui exécute la tâche : `#[AsMessageHandler]` et `__invoke(RecipePDFMessage $m)` |
| **Bus** | `MessageBusInterface::dispatch($message)` envoie le message |
| **Transport** | L'endroit où le message attend : `sync://` (tout de suite), `doctrine://` (table `messenger_messages`), Redis, AMQP… |
| **Worker** | `php bin/console messenger:consume async` lit le transport et appelle les handlers |

## Étapes

1. `make:message RecipePDFMessage`, qui crée le message et son handler.
2. **Router** le message vers un transport, d'une des deux façons :
   - dans `messenger.yaml` (`routing: App\Message\RecipePDFMessage: async`) ;
   - avec `#[AsMessage('async')]` sur la classe.
3. Dans le contrôleur : `$bus->dispatch(new RecipePDFMessage($recipe->getId()))`.
4. **Lancer le worker** : `php bin/console messenger:consume async -vv`. Sans worker, **rien n'est jamais traité**.
5. Gérer les échecs :
   - `retry_strategy` réessaie, ici 3 fois avec un délai multiplié par 2 ;
   - ensuite, le message part dans le `failure_transport` ;
   - `messenger:failed:show` les liste, `messenger:failed:retry` les relance.
6. Pour les **URL absolues générées dans le worker** (en ligne de commande, il n'y a pas de requête HTTP), il faut renseigner `DEFAULT_URI` dans `.env.local`.

## Code minimal

```php
#[AsMessage('async')]
final class RecipePDFMessage
{
    public function __construct(public readonly int $id) {}  // l'id, pas l'entité
}

#[AsMessageHandler]
final class RecipePDFMessageHandler
{
    public function __construct(private HttpClientInterface $http /* ... */) {}

    public function __invoke(RecipePDFMessage $message): void
    {
        // travail lent ; une exception déclenche le retry
    }
}
```

```php
$em->flush();                                            // d'abord enregistrer
$bus->dispatch(new RecipePDFMessage($recipe->getId()));  // puis déposer le message
```

## Pièges

- **Le worker ne recharge pas le code.** Après une modification d'un handler, il faut l'arrêter et le relancer. En production, on utilise `--limit` / `--time-limit` et un superviseur (systemd, Supervisor) qui le relance.
- **Le message doit contenir des données simples**, comme un id. Le message est sérialisé, et une entité Doctrine sérialisée serait obsolète, ou détachée de l'EntityManager, au moment du traitement.
- **Le handler peut s'exécuter avant ou après de nouvelles modifications.** Il faut recharger l'entité dans le handler, et gérer le cas où elle a été supprimée entre-temps : dans ce cas, on ignore le message, sans lever d'exception.
- **Router `SendEmailMessage` vers `async`** rend l'envoi d'e-mails asynchrone. `$mailer->send()` ne fait plus que déposer le message, donc un SMTP en panne ne casse plus la requête. Mais **sans worker, aucun e-mail ne part**, et un `try/catch` autour de `send()` ne voit plus les erreurs SMTP.
- **Une exception non récupérable** (une route qui n'existe pas, une configuration manquante) sera réessayée inutilement. On lève alors une `UnrecoverableMessageHandlingException` pour passer directement au transport d'échec.
- **Deux façons de router le même message** (le YAML **et** `#[AsMessage]`) : c'est redondant. Il vaut mieux en choisir une.
- **Un service dans Docker (Gotenberg) ne voit pas `localhost` de ta machine.** Pour lui, `localhost` est son propre conteneur. Il faut utiliser `http://host.docker.internal:8000`.
- **Dans l'autre sens, le port du conteneur doit être publié** : `docker run --rm -p 3000:3000 gotenberg/gotenberg:9`. Sans `-p`, `docker ps` affiche `3000/tcp` au lieu de `0.0.0.0:3000->3000/tcp`, et `localhost:3000` est injoignable depuis PHP.
- **Gotenberg 9** garde le même endpoint `/forms/chromium/convert/url` que la v8 (vérifié en 9.5.0).
- **Ce qui a changé par rapport aux anciennes versions :**
  - `#[AsMessageHandler]` remplace `MessageHandlerInterface` (supprimé en Symfony 7).
  - `#[AsMessage('async')]` (Symfony 7.2+) permet le routage sans YAML.
  - Le transport Doctrine crée sa table automatiquement, sauf si `auto_setup=0`. Dans ce cas, on la crée par une migration, ce qui est la bonne pratique.
