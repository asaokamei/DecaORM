<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * Attribute indicating the primary key
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Id
{
    public function __construct()
    {
    }
}


