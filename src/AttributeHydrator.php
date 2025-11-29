<?php

namespace WScore\DecaORM;

use ReflectionClass;
use ReflectionProperty;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\CreatedAt;
use WScore\DecaORM\Attribute\Entity;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Attribute\UpdatedAt;

/**
 * AttributeベースのHydrator実装
 * Doctrineスタイルのattributeを使ってエンティティのメタデータを読み取る
 */
class AttributeHydrator implements HydratorInterface
{
    use HydratorTrait;

    /** @var array<string, array{tableName: string, primaryKey: string, pkAutoNumber: bool, properties: array, createdAt: ?string, updatedAt: ?string}> */
    private static array $metadataCache = [];

    private string $entityClass;
    private ?string $tableName = null;
    private ?string $primaryKey = null;
    private bool $pkAutoNumber = false;
    private array $properties = [];
    private ?string $createdAt = null;
    private ?string $updatedAt = null;

    public function __construct(string $entityClass)
    {
        $this->entityClass = $entityClass;
        $this->loadMetadata();
    }

    /**
     * メタデータを読み込む（キャッシュがあれば使用）
     */
    private function loadMetadata(): void
    {
        if (isset(self::$metadataCache[$this->entityClass])) {
            $cached = self::$metadataCache[$this->entityClass];
            $this->tableName = $cached['tableName'];
            $this->primaryKey = $cached['primaryKey'];
            $this->pkAutoNumber = $cached['pkAutoNumber'];
            $this->properties = $cached['properties'];
            $this->createdAt = $cached['createdAt'];
            $this->updatedAt = $cached['updatedAt'];
        } else {
            $this->parseAttributes();
            // キャッシュに保存
            self::$metadataCache[$this->entityClass] = [
                'tableName' => $this->tableName,
                'primaryKey' => $this->primaryKey,
                'pkAutoNumber' => $this->pkAutoNumber,
                'properties' => $this->properties,
                'createdAt' => $this->createdAt,
                'updatedAt' => $this->updatedAt,
            ];
        }
    }

    /**
     * リフレクションを使ってattributeを解析
     */
    private function parseAttributes(): void
    {
        $reflection = new ReflectionClass($this->entityClass);

        // クラスレベルのattributeを解析
        $this->parseClassAttributes($reflection);

        // プロパティレベルのattributeを解析
        $this->parsePropertyAttributes($reflection);
    }

    /**
     * クラスレベルのattributeを解析（Entity, Table）
     */
    private function parseClassAttributes(ReflectionClass $reflection): void
    {
        $attributes = $reflection->getAttributes();

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance instanceof Entity) {
                if ($instance->table !== null) {
                    $this->tableName = $instance->table;
                }
            } elseif ($instance instanceof Table) {
                $this->tableName = $instance->name;
            }
        }

        // テーブル名が指定されていない場合は、クラス名から推測
        if ($this->tableName === null) {
            $shortName = $reflection->getShortName();
            // クラス名をスネークケースに変換（例: UserProfile -> user_profile）
            $this->tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName)) . 's';
        }
    }

    /**
     * プロパティレベルのattributeを解析
     */
    private function parsePropertyAttributes(ReflectionClass $reflection): void
    {
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $columnName = $propertyName; // デフォルトはプロパティ名
            $isId = false;
            $isGenerated = false;
            $isCreatedAt = false;
            $isUpdatedAt = false;

            $attributes = $property->getAttributes();

            // まず、すべての属性を確認してカラム名を決定
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();

                if ($instance instanceof Column) {
                    if ($instance->name !== null) {
                        $columnName = $instance->name;
                    }
                } elseif ($instance instanceof CreatedAt) {
                    $isCreatedAt = true;
                    if ($instance->name !== null) {
                        $columnName = $instance->name;
                    }
                } elseif ($instance instanceof UpdatedAt) {
                    $isUpdatedAt = true;
                    if ($instance->name !== null) {
                        $columnName = $instance->name;
                    }
                }
            }

            // 次に、IdやGeneratedValueなどの属性を確認
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();

                if ($instance instanceof Id) {
                    $isId = true;
                    $this->primaryKey = $columnName;
                } elseif ($instance instanceof GeneratedValue) {
                    $isGenerated = true;
                }
            }

            // CreatedAt/UpdatedAtの設定
            if ($isCreatedAt) {
                $this->createdAt = $columnName;
            }
            if ($isUpdatedAt) {
                $this->updatedAt = $columnName;
            }

            // プライマリキーがGeneratedValueを持っている場合は自動番号
            if ($isId && $isGenerated) {
                $this->pkAutoNumber = true;
            }

            // プロパティリストに追加（プライマリキーも含む）
            $this->properties[] = $columnName;
        }
    }

    public function isPkAutoNumber(): bool
    {
        return $this->pkAutoNumber;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getTableName(): string
    {
        return $this->tableName ?? '';
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey ?? '';
    }

    public function listProperties(): array
    {
        return $this->properties;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function hydrate(array $data): EntityInterface
    {
        $entity = new ($this->entityClass)();
        return $this->hydrateEntity($entity, $data);
    }

    public function dehydrate(EntityInterface $entity): array
    {
        return $this->dehydrateEntity($entity);
    }
}

