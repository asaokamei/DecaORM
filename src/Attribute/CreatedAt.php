<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * 作成日時を示すattribute
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class CreatedAt
{
    public function __construct(
        public ?string $name = null
    ) {
    }
}


