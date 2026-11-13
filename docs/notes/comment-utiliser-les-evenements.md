# Comment utiliser les évènements en Symfony

## Objectif

**Découpler** le code. Le contrôleur annonce simplement « il s'est passé quelque chose » (`dispatch`). D'autres classes, qu'il ne connaît pas, **réagissent** : envoyer un e-mail, écrire un log, modifier la réponse… Pour ajouter une réaction, on ajoute un listener, sans toucher au contrôleur.

Symfony lui-même fonctionne ainsi. Le traitement d'une requête est une suite d'évènements (`kernel.request`, `kernel.controller`, `kernel.response`, `kernel.exception`…), et la sécurité, le Profiler ou les sessions ne sont que des listeners.

## Le vocabulaire

| Terme | Rôle |
|---|---|
| **Event** | Un objet qui transporte les données (`ContactRequestEvent` contient le `ContactDTO`) |
| **Dispatcher** | Le service qui diffuse l'évènement : `EventDispatcherInterface::dispatch($event)` |
| **Listener** | Une méthode appelée pour un évènement, avec `#[AsEventListener]` |
| **Subscriber** | Une classe qui déclare elle-même ses évènements dans `getSubscribedEvents()` |

## Étapes

1. **Créer l'évènement** : une simple classe (`src/Event/ContactRequestEvent.php`). Elle peut étendre `Symfony\Contracts\EventDispatcher\Event` si on veut pouvoir faire `stopPropagation()`.
2. **Le déclencher** dans le contrôleur : `$dispatcher->dispatch(new ContactRequestEvent($data))`. Le nom de l'évènement est alors le nom de sa classe.
3. **Y réagir**, au choix :
   - avec un **listener**, `#[AsEventListener]` sur une méthode : l'évènement est déduit du type de l'argument ;
   - avec un **subscriber**, `implements EventSubscriberInterface` et `getSubscribedEvents()` qui renvoie `[Event::class => 'methode']`. On l'obtient avec `make:subscriber`.
4. Vérifier l'enregistrement : `php bin/console debug:event-dispatcher kernel.response`.
5. Pour les évènements de sécurité, il faut préciser le dispatcher du firewall : `debug:event-dispatcher --dispatcher=security.event_dispatcher.main security.interactive_login`.

## Code minimal

```php
// Contrôleur
$dispatcher->dispatch(new ContactRequestEvent($data));
```

```php
// Listener
final class ContactMailer
{
    public function __construct(private MailerInterface $mailer) {}

    #[AsEventListener]
    public function __invoke(ContactRequestEvent $event): void
    {
        $this->mailer->send(/* ... */ $event->data);
    }
}
```

```php
// Listener kernel.response, limité à la requête principale et au HTML
#[AsEventListener]
public function onResponse(ResponseEvent $event): void
{
    $response = $event->getResponse();
    if (!$event->isMainRequest() || !str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
        return;
    }
    // ...
}
```

## Pièges

- **Un listener `kernel.response` s'applique à TOUTES les réponses** : les pages HTML, mais aussi le JSON de l'API, les assets servis en dev par AssetMapper (JS, CSS), les sous-requêtes et les réponses d'erreur. Le `PizzaPateListener` de la vidéo change par exemple `"title":"Pizza Margherita"` en `"Pâte Margherita"` **dans l'API**. Il faut toujours filtrer avec `isMainRequest()` et le type de contenu.
- **Une exception levée dans un listener remonte jusqu'à celui qui a appelé `dispatch()`.**
  - C'est voulu pour le formulaire de contact : le `try/catch` du contrôleur affiche le flash d'erreur.
  - Mais pour l'e-mail envoyé à la connexion, un serveur SMTP en panne provoque une **erreur 500 au moment de se connecter**. Une action secondaire (notification, log) ne doit pas faire échouer l'action principale : on l'entoure d'un `try/catch`, ou on l'envoie en **asynchrone avec Messenger**.
- **Les évènements de sécurité vivent sur le dispatcher du firewall.**
  - `InteractiveLoginEvent` est dispatché sous le nom `security.interactive_login` (Symfony fait la correspondance depuis le nom de la classe) sur `security.event_dispatcher.main`. Un `dispatch()` sur le dispatcher global n'appelle donc pas ton subscriber.
  - Il n'est envoyé que pour les **connexions interactives** (formulaire de login), pas pour l'authenticator stateless de l'API.
- **`LoginSuccessEvent`**, l'évènement moderne, se déclenche à **chaque** authentification réussie, y compris à **chaque requête** de l'API stateless. Un e-mail de connexion branché dessus partirait à chaque appel d'API. Il faut filtrer avec `$event->getFirewallName() === 'main'`.
- **Le `from()` d'un e-mail ne doit pas être l'adresse du visiteur.** Les serveurs de messagerie (SPF/DMARC) le rejettent comme une usurpation. On utilise une adresse du site en `from()`, et `replyTo($data->email)`.
- **La priorité** décide de l'ordre d'appel : `#[AsEventListener(priority: 10)]`. Plus le nombre est grand, plus le listener passe tôt.
- **Ce qui a changé par rapport aux anciennes versions :**
  - `#[AsEventListener]` (Symfony 5.3+) évite de déclarer le listener dans `services.yaml` avec un tag `kernel.event_listener`.
  - `dispatch($event)` prend maintenant l'**objet** en premier argument. Dans les anciennes versions, c'était `dispatch('nom', $event)`. Le nom est devenu facultatif et vaut par défaut la classe de l'évènement.
  - Les noms d'évènements du kernel ont des classes dédiées : `ResponseEvent` remplace `FilterResponseEvent`, `RequestEvent` remplace `GetResponseEvent` (depuis Symfony 4.3).
