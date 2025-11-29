<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * Attribute to specify the table name
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Table
{
    public function __construct(
        public string $name
    ) {
    }
}


