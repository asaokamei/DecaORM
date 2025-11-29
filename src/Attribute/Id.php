<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * プライマリキーを示すattribute
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Id
{
    public function __construct()
    {
    }
}


