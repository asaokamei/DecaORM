<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * Attribute indicating update timestamp
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class UpdatedAt
{
    public function __construct(
        public ?string $name = null
    ) {
    }
}


