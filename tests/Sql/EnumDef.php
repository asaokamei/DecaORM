<?php

namespace tests\Sql;

enum Status: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

enum IntStatus: int {
    case ONE = 1;
    case TWO = 2;
}
