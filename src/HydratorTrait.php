<?php

namespace WScore\DecaORM;

trait HydratorTrait
{
    /** @var EntityInterface[][] */
    public static array $cached = [];
    public abstract function getPrimaryKey(): string;
    abstract private function listProperties(): array;

    /**
     * 連想配列（DB行）からエンティティに変換（ハイドレーション）
     * @return EntityInterface
     */
    private function hydrateEntity(EntityInterface $entity, array $data): mixed
    {
        $pKey = $this->getPrimaryKey(); // HydratorInterfaceの実装が必要だがTraitなので$thisで呼べる前提
        $id = $data[$pKey] ?? null;
        $class = get_class($entity);

        $targetEntity = $entity;
        if ($id !== null && isset(self::$cached[$class][$id])) {
            // キャッシュがあればそれを使う。渡された$entity（新規インスタンス）は破棄される
            $targetEntity = self::$cached[$class][$id];
        }

        // 対象のエンティティにデータをセット（キャッシュ済みのものでも最新データで更新）
        foreach ($this->listProperties() as $property) {
            if (isset($data[$property])) {
                $targetEntity->set($property, $data[$property]);
            }
        }

        // IDがあればキャッシュに登録
        if ($id !== null) {
            self::$cached[$class][$id] = $targetEntity;
        }

        return $targetEntity;
    }

    /**
     * エンティティから連想配列に変換（デハイドレーション）
     */
    public function dehydrateEntity(EntityInterface $entity): array
    {
        $data = [];
        foreach ($this->listProperties() as $property) {
            $data[$property] = $entity->get($property);
        }
        return $data;
    }
}