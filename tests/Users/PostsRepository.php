<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use PDO;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\RepositoryTrait;

class PostsRepository
{
    use RepositoryTrait;

    public function __construct(PDO $pdo, HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = $hydrator ?? new AttributeHydrator(Post::class);
        $this->now = new DateTimeImmutable();
    }

    public function find(int $id): ?Post
    {
        $post = $this->fetchEntityById($id);
        if (!$post instanceof Post) {
            return null;
        }
        return $post;
    }

    public function createAndSave(array $data): ?Post
    {
        $id = $this->insertData($data);
        return $id
            ? $this->find($id)
            : null;
    }

    public function save(Post $post): void
    {
        if ($post->getId() === null) {
            $this->insertEntity($post);
        } else {
            $this->updateEntity($post);
        }
    }

    public function delete(Post $post): void
    {
        $this->deleteEntity($post);
    }

    /**
     * UserにPostsを読み込む（OneToMany）
     * UserRepositoryから呼び出されることを想定
     */
    public function loadPostsForUser(User $user): void
    {
        $this->fillChildEntities($user, 'posts', 'user_id', 'user');
    }
}

