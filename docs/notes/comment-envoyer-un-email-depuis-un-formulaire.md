# Comment envoyer un e-mail depuis un formulaire en Symfony

## Objectif

Créer une page de contact qui valide la saisie et envoie un e-mail, **sans rien enregistrer en base**. On n'utilise donc pas d'entité, mais un **DTO** : un simple objet qui transporte des données.

## Étapes

1. **Créer le DTO** (`src/DTO/ContactDTO.php`). C'est une classe avec des propriétés publiques et des contraintes `#[Assert\...]`. Le Validator fonctionne sur n'importe quel objet, pas seulement sur les entités.
2. **Créer le formulaire** avec `make:form ContactType`. Dans `configureOptions`, mettre `'data_class' => ContactDTO::class`.
3. **Dans le contrôleur** :
   - `new ContactDTO()`, puis `createForm()` et `handleRequest()` ;
   - si le formulaire est valide, construire un `TemplatedEmail` et l'envoyer avec `MailerInterface::send()` ;
   - ajouter un message flash, puis rediriger.
4. **Écrire le template de l'e-mail** (`templates/emails/contact.html.twig`). Les données passées avec `->context([...])` y sont disponibles.
5. **Tester en local avec Mailpit**, qui capture les e-mails au lieu de les envoyer :
   - lancer `mailpit.exe` ;
   - mettre `MAILER_DSN=smtp://localhost:1025` dans **`.env.local`** ;
   - consulter les e-mails reçus sur http://localhost:8025.
6. **Choisir entre envoi synchrone et asynchrone** dans `config/packages/messenger.yaml` :
   - `SendEmailMessage: async` met l'e-mail en file d'attente, et il faut lancer `messenger:consume async` pour qu'il parte ;
   - `sync` l'envoie tout de suite.

## Code minimal

```php
$email = new TemplatedEmail()          // PHP 8.4 : plus besoin de parenthèses autour de new
    ->from('contact@monsite.fr')       // l'adresse du SITE
    ->replyTo($data->email)            // l'adresse du visiteur, pour lui répondre
    ->to($data->service)               // destinataire choisi dans le formulaire
    ->subject('Demande de contact')
    ->htmlTemplate('emails/contact.html.twig')
    ->context(['data' => $data]);

$mailer->send($email);
```

```php
->add('service', ChoiceType::class, [
    'choices' => ['Compta' => 'compta@demo.fr', 'Support' => 'support@demo.fr'],
])
```

## Pièges

- **Un `ChoiceType` refuse toute valeur hors liste.** Même si quelqu'un modifie le HTML pour envoyer `pirate@evil.com`, le formulaire devient invalide. On peut donc se fier à `$data->service` comme destinataire.
- **`from` doit être l'adresse du site**, pas celle du visiteur. Sinon les filtres SPF et DMARC classent l'e-mail en spam, ou le rejettent. L'adresse du visiteur va dans `replyTo`.
- **Avec Messenger en `async`**, `send()` ne fait que mettre l'e-mail en file d'attente. Une panne SMTP ne sera donc jamais attrapée par un `try/catch` autour de `send()`. Seules les erreurs de construction du message le seront, comme une adresse invalide (`RfcComplianceException`).
- **Après un POST qui échoue, il faut répondre 422** (ou rediriger), pas 200. Turbo l'exige, et un client d'API se fiera au statut HTTP.
- **Twig échappe automatiquement les données** dans le template de l'e-mail : pas de risque d'injection HTML. Pour garder les retours à la ligne du message, utiliser `{{ data.message|nl2br }}`.
- **`MAILER_DSN` va dans `.env.local`**, jamais dans `.env`, qui est versionné.
- **Ce qui a changé par rapport aux anciennes versions :** `SwiftMailer` a été remplacé par le composant **Mailer** (Symfony 4.3+), et `TemplatedEmail` permet d'écrire le contenu de l'e-mail avec Twig.
