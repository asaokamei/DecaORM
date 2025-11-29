<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * Attribute indicating the entity class
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Entity
{
    public function __construct(
        public ?string $table = null
    ) {
    }
}


