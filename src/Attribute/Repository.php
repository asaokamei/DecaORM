<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * Attribute to specify the repository class
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Repository
{
    public function __construct(
        public string $class
    ) {
    }
}


