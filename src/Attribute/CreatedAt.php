<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * Attribute indicating creation timestamp
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class CreatedAt
{
    public function __construct(
        public ?string $name = null
    ) {
    }
}


