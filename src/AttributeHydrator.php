<?php

namespace WScore\DecaORM;

use ReflectionClass;
use ReflectionProperty;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\CreatedAt;
use WScore\DecaORM\Attribute\CustomLoader;
use WScore\DecaORM\Attribute\Entity;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\Attribute\MorphTo;
use WScore\DecaORM\Attribute\MorphToOne;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Attribute\UpdatedAt;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\HydratorInterface;
use WScore\DecaORM\Trait\HydratorTrait;

/**
 * Attribute-based Hydrator implementation
 * Use Doctrine-style attributes to read entity metadata
 */
class AttributeHydrator implements HydratorInterface
{
    use HydratorTrait;

    /** @var array<string, array{tableName: string, primaryKey: string, pkAutoNumber: bool, properties: array, propertyToColumnMap: array, relations: array, createdAt: ?string, updatedAt: ?string}> */
    private static array $metadataCache = [];

    private string $entityClass;
    private ?string $tableName = null;
    private ?string $primaryKey = null;
    private bool $pkAutoNumber = false;
    private array $properties = [];
    /** @var array<string, string> propertyName => columnName */
    private array $propertyToColumnMap = [];
    /** @var array<string, string> columnName => propertyName */
    private array $columnToPropertyMap = [];
    /** @var array<string, array{type: string, targetEntity: string, ...}> propertyName => relation info */
    private array $relations = [];
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
            // Check if cached entry has the required propertyToColumnMap key
            // If not, invalidate cache and re-parse to ensure mappings are correct
            if (!isset($cached['propertyToColumnMap'])) {
                // Invalidate old cache entry and re-parse
                unset(self::$metadataCache[$this->entityClass]);
                $this->parseAttributes();
                // Save to cache with new structure
                self::$metadataCache[$this->entityClass] = [
                    'tableName' => $this->tableName,
                    'primaryKey' => $this->primaryKey,
                    'pkAutoNumber' => $this->pkAutoNumber,
                    'properties' => $this->properties,
                    'propertyToColumnMap' => $this->propertyToColumnMap,
                    'relations' => $this->relations,
                    'createdAt' => $this->createdAt,
                    'updatedAt' => $this->updatedAt,
                ];
                return;
            }
            $this->tableName = $cached['tableName'];
            $this->primaryKey = $cached['primaryKey'];
            $this->pkAutoNumber = $cached['pkAutoNumber'];
            $this->properties = $cached['properties'];
            $this->propertyToColumnMap = $cached['propertyToColumnMap'];
            $this->columnToPropertyMap = array_flip($this->propertyToColumnMap);
            $this->relations = $cached['relations'] ?? [];
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
                'propertyToColumnMap' => $this->propertyToColumnMap,
                'relations' => $this->relations,
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
            $hasColumn = false;

            $attributes = $property->getAttributes();

            // First, check all attributes to determine the column name and if it's a DB column
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();

                if ($instance instanceof Column) {
                    $hasColumn = true;
                    if ($instance->name !== null) {
                        $columnName = $instance->name;
                    }
                } elseif ($instance instanceof CreatedAt) {
                    $isCreatedAt = true;
                    $hasColumn = true; // CreatedAt is a DB column
                    if ($instance->name !== null) {
                        $columnName = $instance->name;
                    }
                } elseif ($instance instanceof UpdatedAt) {
                    $isUpdatedAt = true;
                    $hasColumn = true; // UpdatedAt is a DB column
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
                    $hasColumn = true; // Id is always a DB column (primary key)
                    $this->primaryKey = $propertyName;
                } elseif ($instance instanceof GeneratedValue) {
                    $isGenerated = true;
                }
            }

            // Set CreatedAt/UpdatedAt
            if ($isCreatedAt) {
                $this->createdAt = $propertyName;
            }
            if ($isUpdatedAt) {
                $this->updatedAt = $propertyName;
            }

            // If the primary key has GeneratedValue, it is an auto-number
            if ($isId && $isGenerated) {
                $this->pkAutoNumber = true;
            }

            // Add to property list only if it's a DB column
            // (has Column attribute, or is CreatedAt/UpdatedAt, or is Id)
            if ($hasColumn) {
                $this->properties[] = $propertyName;
                // Store property name to column name mapping
                $this->propertyToColumnMap[$propertyName] = $columnName;
                $this->columnToPropertyMap[$columnName] = $propertyName;
            }

            // Parse relation attributes (ManyToOne, OneToMany, OneToOne)
            $this->parseRelationAttributes($property);
        }
    }

    /**
     * Parse relation attributes on a property
     */
    private function parseRelationAttributes(ReflectionProperty $property): void
    {
        $propertyName = $property->getName();
        $attributes = $property->getAttributes();

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance instanceof BelongsTo) {
                $this->relations[$propertyName] = $instance;
                $instance->propertyName = $propertyName;
            } elseif ($instance instanceof MorphTo) {
                $this->relations[$propertyName] = $instance;
                $instance->propertyName = $propertyName;
            } elseif ($instance instanceof MorphToOne) {
                $this->relations[$propertyName] = $instance;
                $instance->propertyName = $propertyName;
            } elseif ($instance instanceof HasMany) {
                $this->relations[$propertyName] = $instance;
                $instance->propertyName = $propertyName;
            } elseif ($instance instanceof BelongsToOne) {
                $this->relations[$propertyName] = $instance;
                $instance->propertyName = $propertyName;
            } elseif ($instance instanceof HasOne) {
                $this->relations[$propertyName] = $instance;
                $instance->propertyName = $propertyName;
            } elseif ($instance instanceof ManyToMany) {
                $this->relations[$propertyName] = $instance;
                $instance->propertyName = $propertyName;
            } elseif ($instance instanceof CustomLoader) {
                $this->relations[$propertyName] = $instance;
                $instance->propertyName = $propertyName;
            }
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
        return $this->materializeFromRow($data);
    }

    public function hydrateDetached(array $data): EntityInterface
    {
        return $this->materializeFromRow($data);
    }

    public function dehydrate(EntityInterface $entity): array
    {
        return $this->dehydrateEntity($entity);
    }

    /**
     * Get property name for a given column name
     */
    public function getPropertyNameForColumn(string $columnName): ?string
    {
        return $this->columnToPropertyMap[$columnName] ?? null;
    }

    /**
     * Get column name for a given property name
     */
    public function getColumnNameForProperty(string $propertyName): ?string
    {
        return $this->propertyToColumnMap[$propertyName] ?? null;
    }

    public function getPrimaryKeyColumn(): string
    {
        return $this->getColumnNameForProperty($this->getPrimaryKey());
    }

    public function getCreatedAtColumn(): string
    {
        return $this->getColumnNameForProperty($this->getCreatedAt());
    }

    public function getUpdatedAtColumn(): ?string
    {
        return $this->getColumnNameForProperty($this->getUpdatedAt());
    }

    /**
     * Get all relations for this entity
     * 
     * @return array<string, array{type: string, targetEntity: string, ...}>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Get relation information for a specific property
     * 
     * @param string $propertyName The property name
     * @return mixed|null|HasMany|HasOne|BelongsTo|MorphTo|MorphToOne
     */
    public function getRelation(string $propertyName): mixed
    {
        return $this->relations[$propertyName] ?? null;
    }
}

