<?php

namespace App\Event;

use App\DTO\ContactDTO;

final class ContactRequestEvent
{
    public function __construct(public readonly ContactDTO $data) {}
}
