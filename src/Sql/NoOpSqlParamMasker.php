<?php

namespace WScore\DecaORM\Sql;

use WScore\DecaORM\Contracts\SqlParamMaskerInterface;

class NoOpSqlParamMasker implements SqlParamMaskerInterface
{
    public function mask(array $params): array
    {
        return $params;
    }
}
