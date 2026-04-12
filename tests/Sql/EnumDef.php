<?php
/** @noinspection PhpLanguageLevelInspection */

namespace WScore\DecaORM\Tests\Sql;

enum Status: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

enum IntStatus: int {
    case ONE = 1;
    case TWO = 2;
}
