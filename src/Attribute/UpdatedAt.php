<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * 更新日時を示すattribute
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class UpdatedAt
{
    public function __construct(
        public ?string $name = null
    ) {
    }
}


