<?php

declare(strict_types=1);

namespace WScore\DecaORM\Tests\Support;

use PDOStatement;
use WScore\DecaORM\Tests\Users\UserRepository;

final class SpyUserRepository extends UserRepository
{
    /**
     * @var list<string>
     */
    public array $executedSql = [];

    public function execute(string $sql, array $data): bool|PDOStatement
    {
        $this->executedSql[] = $sql;
        return parent::execute($sql, $data);
    }
}
