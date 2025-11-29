<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * 自動生成される値（AUTO_INCREMENTなど）を示すattribute
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class GeneratedValue
{
    public function __construct()
    {
    }
}


