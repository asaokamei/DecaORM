<?php

namespace WScore\DecaORM\Tests\Fixtures\Persistence;

use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\Contracts\RepositoryHooksInterface;
use WScore\DecaORM\OrmManager;

/**
 * @extends AbstractRepository<HookItem>
 */
class HookItemRepository extends AbstractRepository
{
    public function __construct(OrmManager $manager, ?RepositoryHooksInterface $hooks = null)
    {
        $this->setUpRepository($manager, null, HookItem::class);
        if ($hooks !== null) {
            $this->hooks = $hooks;
        }
    }
}
