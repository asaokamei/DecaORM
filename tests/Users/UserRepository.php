<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use PDO;
use Psr\Container\ContainerInterface;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\AbstractRepository;

/**
 * @extends AbstractRepository<User>
 */
class UserRepository extends AbstractRepository
{
    private ?ContainerInterface $container;

    public function __construct(PDO $pdo, ContainerInterface $container = null, HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = $hydrator ?? new AttributeHydrator(User::class);
        $this->now = new DateTimeImmutable();
        $this->container = $container;
    }

    /**
     * PostにUserを読み込む（BelongsTo）
     * AttributeHydratorからリレーション情報を読み取って処理
     */
    public function loadUserForPost(Post $post): void
    {
        $postRepo = $post::getRepositoryClass();
        $postRepo = $this->container->get($postRepo);

        $postHydrator = $postRepo->getHydrator();
        $relation = $postHydrator->getRelation('user');
        
        if (!$relation instanceof BelongsTo) {
            throw new \RuntimeException('Post entity does not have a BelongsTo relation named "user"');
        }
        
        // Get foreign key property name (convert column name to property name)
        $foreignKeyProperty = $postHydrator->getPropertyNameForColumn($relation->foreignKey);
        $id = $post->get($foreignKeyProperty);
        
        if ($id === null) {
            $post->set('user', null);
            return;
        }
        
        $user = $this->findById($id);
        $post->set('user', $user);
    }
}
