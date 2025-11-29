<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * テーブル名を指定するattribute
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Table
{
    public function __construct(
        public string $name
    ) {
    }
}


