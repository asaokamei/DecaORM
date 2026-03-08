<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\RepositoryManager;

/**
 * @extends AbstractRepository<Comment>
 */
class CommentRepository extends AbstractRepository
{
    public function __construct(RepositoryManager $manager)
    {
        $this->setUpRepository($manager, null, Comment::class);
    }
}
