<?php

namespace App\Message;

// Routé vers le transport "async" dans config/packages/messenger.yaml
final class RecipePDFMessage
{
    public function __construct(
        public readonly int $id,
    ) {}
}
