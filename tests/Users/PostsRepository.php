<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use PDO;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\AbstractRepository;

/**
 * @extends AbstractRepository<Post>
 */
class PostsRepository extends AbstractRepository
{
    public function __construct(PDO $pdo, HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = $hydrator ?? new AttributeHydrator(Post::class);
        $this->now = new DateTimeImmutable();
    }

    public function create(User $user, array $data): Post
    {
        $data['user_id'] = $user->getId();
        return $this->createAndSave($data);
    }

    /**
     * UserにPostsを読み込む（HasMany）
     * AttributeHydratorからリレーション情報を読み取って処理
     */
    public function loadPostsForUser(UserRepository $userRepo, User $user): void
    {
        $userHydrator = $userRepo->getHydrator();
        $relation = $userHydrator->getRelation('posts');
        
        if ($relation === null || $relation['type'] !== 'HasMany') {
            throw new \RuntimeException('User entity does not have a HasMany relation named "posts"');
        }
        
        // Get foreign key column name
        $foreignKeyColumn = $relation['foreignKey'];
        $orderBy = $relation['orderBy'];
        
        // Find posts by foreign key
        $posts = $this->find($user->getId(), $foreignKeyColumn, $orderBy);
        
        if (empty($posts)) {
            $user->set('posts', []);
            return;
        }
        
        // Set bidirectional link (post -> user)
        $postHydrator = $this->getHydrator();
        $postRelation = $postHydrator->getRelation('user');
        if ($postRelation !== null && $postRelation['inversedBy'] === 'posts') {
            foreach ($posts as $post) {
                $post->set('user', $user);
            }
        }
        
        $user->set('posts', $posts);
    }
}

