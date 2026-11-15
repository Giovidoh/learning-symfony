<?php

namespace App\ValueResolver\Attribute;

use App\ValueResolver\EntityHydratorValueResolver;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * Charge l'entité depuis la route, lui applique le JSON de la requête, puis la valide.
 *
 * Étend ValueResolver (HttpKernel) : l'argument est résolu uniquement par EntityHydratorValueResolver.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class MapHydratedEntity extends ValueResolver
{
    public function __construct(
        /** Groupes de (dé)sérialisation : quels champs le client a le droit de modifier */
        public readonly array $groups = [],
        /** Groupes de validation (null = groupe "Default") */
        public readonly string|GroupSequence|array|null $validationGroups = null,
    ) {
        parent::__construct(EntityHydratorValueResolver::class);
    }
}
