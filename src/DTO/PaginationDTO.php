<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class PaginationDTO
{
    public function __construct(
        #[Assert\Positive()]
        public readonly int $page = 1
    ) {}
}
