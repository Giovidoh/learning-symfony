<?php

namespace App\ValueResolver;

use App\ValueResolver\Attribute\MapHydratedEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Appelé seulement pour les arguments marqués #[MapHydratedEntity] (AsTargetedValueResolver).
 */
#[AsTargetedValueResolver]
final readonly class EntityHydratorValueResolver implements ValueResolverInterface
{
    public function __construct(
        // On réutilise le resolver de Doctrine (celui qui fait le find() à partir de {id})
        #[Autowire(service: 'doctrine.orm.entity_value_resolver')]
        private ValueResolverInterface $entityValueResolver,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
    ) {}

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attribute = $argument->getAttributesOfType(MapHydratedEntity::class)[0] ?? null;
        if (!$attribute instanceof MapHydratedEntity) {
            return [];
        }

        // 1. Charger l'entité (404 levée par Doctrine si elle n'existe pas)
        $entity = $this->entityValueResolver->resolve($request, $argument)[0] ?? null;
        if (!\is_object($entity)) {
            return [];
        }

        // 2. Appliquer le JSON sur l'entité existante (seulement les champs des groupes autorisés)
        $format = $request->getContentTypeFormat() ?? 'json';
        if ('json' !== $format) {
            throw new UnsupportedMediaTypeHttpException(\sprintf('Format "%s" non supporté, envoyez du JSON.', $format));
        }

        try {
            $this->serializer->deserialize($request->getContent(), $entity::class, $format, [
                'groups' => $attribute->groups,
                AbstractNormalizer::OBJECT_TO_POPULATE => $entity,
            ]);
        } catch (SerializerException $e) {
            throw new BadRequestHttpException('JSON invalide : ' . $e->getMessage(), $e);
        }

        // 3. Valider le résultat
        $violations = $this->validator->validate($entity, groups: $attribute->validationGroups);
        if (\count($violations) > 0) {
            throw new UnprocessableEntityHttpException((string) $violations, new ValidationFailedException($entity, $violations));
        }

        return [$entity];
    }
}
