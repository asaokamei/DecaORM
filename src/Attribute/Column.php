<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * カラム情報を指定するattribute
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


