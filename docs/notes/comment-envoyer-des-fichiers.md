# Comment envoyer des fichiers en Symfony (VichUploader)

## Objectif

Permettre d'envoyer une image avec une recette :
- le **fichier** est enregistré sur le disque ;
- seul son **nom** est enregistré en base.

## Étapes

1. Installer le bundle avec `composer require vich/uploader-bundle`. La recette Flex crée `config/packages/vich_uploader.yaml`.
2. **Déclarer un mapping** dans ce fichier : le dossier de destination (`upload_destination`), l'URL publique (`uri_prefix`) et la façon de nommer les fichiers (`namer`, par exemple `SmartUniqueNamer`).
3. **Dans l'entité, ajouter deux propriétés :**
   - `$thumbnail` (`string`, colonne en base) : le nom du fichier ;
   - `$thumbnailFile` (`File`, **pas** de colonne) : le fichier envoyé, avec `#[Vich\UploadableField(mapping: 'recipes', fileNameProperty: 'thumbnail')]`.
4. **Dans le formulaire**, ajouter `->add('thumbnailFile', FileType::class, ['required' => false])`.
5. **Afficher l'image** avec `<img src="{{ vich_uploader_asset(recipe, 'thumbnailFile') }}">`.
6. **Exclure les fichiers envoyés de git** en ajoutant `/public/images/recipes/` au `.gitignore`.

## Code minimal

```php
#[ORM\Column(length: 255, nullable: true)]
private ?string $thumbnail = null;

#[Vich\UploadableField(mapping: 'recipes', fileNameProperty: 'thumbnail')]
#[Assert\Image()]
private ?File $thumbnailFile = null;

public function setThumbnail(?string $thumbnail): static { $this->thumbnail = $thumbnail; return $this; }
```

## Pièges

- **Le nom de la propriété `...File` doit correspondre à son getter et à son setter.** Vich la lit avec PropertyAccess. Une faute de frappe provoque « Can't get a way to read the property ».
- **La colonne du nom de fichier doit être `nullable: true`**, et son setter doit accepter `?string`. Une recette peut ne pas avoir d'image, et Vich passe `null` quand on la supprime.
- **`FileType` est obligatoire par défaut.** Sans `'required' => false`, le navigateur force l'envoi d'un nouveau fichier, même sur la page d'édition.
- **Si seul le fichier change, Doctrine ne voit aucune modification** et n'enregistre rien. Il faut modifier une colonne à ce moment-là, par exemple `updatedAt`, dans `setThumbnailFile()` ou dans un listener de formulaire.
- **`#[Assert\Image]`** vérifie le type MIME réel du fichier, pas seulement son extension. On peut le compléter avec `maxSize`.
- **Le formulaire doit être envoyé en `multipart/form-data`.** `form_start()` l'ajoute automatiquement dès qu'il y a un `FileType`. Avec une balise `<form>` écrite à la main, il faut l'ajouter soi-même.
