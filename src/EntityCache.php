<?php

namespace WScore\DecaORM;

/**
 * エンティティのキャッシュを管理するクラス
 */
class EntityCache
{
    /** @var EntityInterface[][] */
    private static array $cached = [];

    /**
     * キャッシュからエンティティを取得
     * 
     * @param string $class エンティティクラス名
     * @param int|string $id エンティティのID
     * @return EntityInterface|null
     */
    public static function get(string $class, int|string $id): ?EntityInterface
    {
        return self::$cached[$class][$id] ?? null;
    }

    /**
     * キャッシュにエンティティを保存
     * 
     * @param string $class エンティティクラス名
     * @param int|string $id エンティティのID
     * @param EntityInterface $entity エンティティ
     */
    public static function set(string $class, int|string $id, EntityInterface $entity): void
    {
        if (!isset(self::$cached[$class])) {
            self::$cached[$class] = [];
        }
        self::$cached[$class][$id] = $entity;
    }

    /**
     * キャッシュにエンティティが存在するかチェック
     * 
     * @param string $class エンティティクラス名
     * @param int|string $id エンティティのID
     * @return bool
     */
    public static function has(string $class, int|string $id): bool
    {
        return isset(self::$cached[$class][$id]);
    }

    /**
     * エンティティをキャッシュに登録または取得
     * IDがnullの場合はそのまま返す。IDがある場合はキャッシュがあればそれを返し、なければキャッシュに登録して返す
     * 
     * @param EntityInterface $entity エンティティ
     * @return EntityInterface キャッシュ済みのエンティティまたは元のエンティティ
     */
    public static function cache(EntityInterface $entity): EntityInterface
    {
        $class = get_class($entity);
        $id = $entity->getId();
        if ($id === null) {
            return $entity;
        }
        if (self::has($class, $id)) {
            return self::get($class, $id);
        }
        self::set($class, $id, $entity);
        return $entity;
    }

    /**
     * キャッシュをクリア
     * 
     * @param string|null $class 指定したクラスのキャッシュのみクリア。nullの場合は全てクリア
     */
    public static function clear(?string $class = null): void
    {
        if ($class === null) {
            self::$cached = [];
        } else {
            unset(self::$cached[$class]);
        }
    }
}

