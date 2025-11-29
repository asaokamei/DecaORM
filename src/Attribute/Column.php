<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * Specify column information
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public ?string $name = null,
        public ?string $type = null
    ) {
    }
}


