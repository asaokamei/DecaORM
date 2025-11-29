<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * エンティティクラスを示すattribute
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Entity
{
    public function __construct(
        public ?string $table = null
    ) {
    }
}


