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
 * Attribute-based Hydrator implementation
 * Use Doctrine-style attributes to read entity metadata
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
     * Load metadata (use cached if available)
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
            // Save to cache
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
     * Parse attributes using reflection
     */
    private function parseAttributes(): void
    {
        $reflection = new ReflectionClass($this->entityClass);

        // Parse class-level attributes
        $this->parseClassAttributes($reflection);

        // Parse property-level attributes
        $this->parsePropertyAttributes($reflection);
    }

    /**
     * Parse class-level attributes (Entity, Table)
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

        // If the table name is not specified, infer it from the class name
        if ($this->tableName === null) {
            $shortName = $reflection->getShortName();
            // Convert class name to snake_case (e.g. UserProfile -> user_profile)
            $this->tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName)) . 's';
        }
    }

    /**
     * Parse property-level attributes
     */
    private function parsePropertyAttributes(ReflectionClass $reflection): void
    {
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $columnName = $propertyName; // Default is property name
            $isId = false;
            $isGenerated = false;
            $isCreatedAt = false;
            $isUpdatedAt = false;

            $attributes = $property->getAttributes();

            // First, check all attributes to determine the column name
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

            // Next, check attributes for Id and GeneratedValue
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();

                if ($instance instanceof Id) {
                    $isId = true;
                    $this->primaryKey = $columnName;
                } elseif ($instance instanceof GeneratedValue) {
                    $isGenerated = true;
                }
            }

            // Set CreatedAt/UpdatedAt
            if ($isCreatedAt) {
                $this->createdAt = $columnName;
            }
            if ($isUpdatedAt) {
                $this->updatedAt = $columnName;
            }

            // If the primary key has GeneratedValue, it is an auto-number
            if ($isId && $isGenerated) {
                $this->pkAutoNumber = true;
            }

            // Add to property list (including primary key)
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

