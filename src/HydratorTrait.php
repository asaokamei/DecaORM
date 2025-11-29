<?php

namespace WScore\DecaORM;

trait HydratorTrait
{
    public abstract function getPrimaryKey(): string;
    abstract protected function listProperties(): array;

    /**
     * 連想配列（DB行）からエンティティに変換（ハイドレーション）
     * @return EntityInterface
     */
    protected function hydrateEntity(EntityInterface $entity, array $data): mixed
    {
        $pKey = $this->getPrimaryKey(); // HydratorInterfaceの実装が必要だがTraitなので$thisで呼べる前提

        $targetEntity = EntityCache::cache($entity);

        // 対象のエンティティにデータをセット（キャッシュ済みのものでも最新データで更新）
        foreach ($this->listProperties() as $property) {
            if (isset($data[$property])) {
                $targetEntity->set($property, $data[$property]);
            }
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