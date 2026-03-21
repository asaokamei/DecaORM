<?php

declare(strict_types=1);

namespace WScore\DecaORM\Tests\Fixtures\Morph;

use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\OrmManager;

class MorphThumbnailRepository extends AbstractRepository
{
    public function __construct(OrmManager $manager)
    {
        $this->setUpRepository($manager, null, MorphThumbnail::class);
    }
}
