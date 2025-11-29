<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * Attribute indicating a value that is automatically generated (AUTO_INCREMENT, etc.)
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class GeneratedValue
{
    public function __construct()
    {
    }
}


