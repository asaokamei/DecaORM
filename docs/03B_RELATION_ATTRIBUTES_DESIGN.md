# リレーションアトリビュート設計案

> **注意**: このドキュメントは設計時のものです。現在の実装については [`RELATION_ATTRIBUTES.md`](03_RELATION_ATTRIBUTES.md) を参照してください。

## 実装済みアトリビュート

### 1. BelongsTo (旧: ManyToOne)
親エンティティへの参照（外部キーを持つ側）

```php
#[BelongsTo(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'posts')]
public ?User $user = null;
```

**パラメータ:**
- `targetEntity`: 関連先のエンティティクラス名（必須）
- `foreignKey`: 外部キーカラム名（必須）
- `inversedBy`: 逆側のプロパティ名（双方向リレーションの場合、オプション）
- `fetch`: フェッチ戦略（LAZY/EAGER、デフォルト: LAZY）

### 2. HasMany (旧: OneToMany)
子エンティティのコレクション（親側）

```php
#[HasMany(targetEntity: Post::class, foreignKey: 'user_id', orderBy: 'created_at DESC')]
public ?array $posts = null;
```

**パラメータ:**
- `targetEntity`: 関連先のエンティティクラス名（必須）
- `foreignKey`: 子側のテーブルの外部キーカラム名（必須）
- `orderBy`: ソート順（オプション、例: 'created_at DESC'）
- `fetch`: フェッチ戦略（LAZY/EAGER、デフォルト: LAZY）

### 3. HasOne (旧: OneToOne)
1対1のリレーション

```php
// 外部キーが相手側にある場合（デフォルト）
#[HasOne(targetEntity: Profile::class, foreignKey: 'user_id')]
public ?Profile $profile = null;

// 外部キーがこの側にある場合
#[HasOne(targetEntity: User::class, foreignKey: 'profile_id', onThisSide: true)]
public ?User $user = null;
```

**パラメータ:**
- `targetEntity`: 関連先のエンティティクラス名（必須）
- `foreignKey`: 外部キーカラム名（必須）
- `onThisSide`: 外部キーがこの側にある場合 `true`（デフォルト: `false`）
- `inversedBy`: 逆側のプロパティ名（双方向リレーションの場合、オプション）
- `fetch`: フェッチ戦略（LAZY/EAGER、デフォルト: LAZY）

### 4. ManyToMany（実装予定）
多対多のリレーション（中間テーブルが必要）

## エンティティからリポジトリを取得する方法

### オプション1: リポジトリレジストリパターン（推奨）

```php
class RepositoryRegistry
{
    private static array $repositories = [];
    private static ?PDO $db = null;
    
    public static function setDb(PDO $db): void
    {
        self::$db = $db;
    }
    
    public static function getRepository(string $entityClass): RepositoryInterface
    {
        if (!isset(self::$repositories[$entityClass])) {
            // 規約: EntityClass -> EntityClassRepository
            // 例: User -> UserRepository
            $repositoryClass = str_replace('\\Entity\\', '\\Repository\\', $entityClass) . 'Repository';
            
            // または、名前空間の最後の部分を置き換え
            // 例: App\Entity\User -> App\Repository\UserRepository
            if (!class_exists($repositoryClass)) {
                $parts = explode('\\', $entityClass);
                $entityName = array_pop($parts);
                $parts[] = 'Repository';
                $parts[] = $entityName . 'Repository';
                $repositoryClass = implode('\\', $parts);
            }
            
            if (!class_exists($repositoryClass)) {
                throw new \RuntimeException("Repository not found for entity: {$entityClass}");
            }
            
            $hydrator = new AttributeHydrator($entityClass);
            self::$repositories[$entityClass] = new $repositoryClass(self::$db, $hydrator);
        }
        
        return self::$repositories[$entityClass];
    }
}
```

### オプション2: リポジトリファクトリーパターン

```php
interface RepositoryFactoryInterface
{
    public function getRepository(string $entityClass): RepositoryInterface;
}

class DefaultRepositoryFactory implements RepositoryFactoryInterface
{
    public function __construct(
        private PDO $db,
        private array $repositoryMap = []
    ) {}
    
    public function getRepository(string $entityClass): RepositoryInterface
    {
        // カスタムマッピングがあれば使用
        if (isset($this->repositoryMap[$entityClass])) {
            $repositoryClass = $this->repositoryMap[$entityClass];
            $hydrator = new AttributeHydrator($entityClass);
            return new $repositoryClass($this->db, $hydrator);
        }
        
        // 規約ベースの解決
        // ...
    }
}
```

### オプション3: RepositoryTraitにリポジトリ取得メソッドを追加

```php
trait RepositoryTrait
{
    // ...
    
    protected function getRepositoryForEntity(string $entityClass): RepositoryInterface
    {
        // リポジトリレジストリまたはファクトリーを使用
        return RepositoryRegistry::getRepository($entityClass);
    }
}
```

## リレーションメタデータの保存

`AttributeHydrator`にリレーション情報を追加：

```php
class AttributeHydrator
{
    /** @var array<string, array{type: string, targetEntity: string, ...}> */
    private array $relations = [];
    
    // リレーションアトリビュートを解析
    private function parseRelationAttributes(ReflectionProperty $property): void
    {
        $attributes = $property->getAttributes();
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            
            if ($instance instanceof ManyToOne) {
                $this->relations[$property->getName()] = [
                    'type' => 'ManyToOne',
                    'targetEntity' => $instance->targetEntity,
                    'joinColumn' => $instance->joinColumn,
                    'inversedBy' => $instance->inversedBy,
                    'fetch' => $instance->fetch ?? 'LAZY',
                ];
            }
            // OneToMany, OneToOne も同様
        }
    }
    
    public function getRelations(): array
    {
        return $this->relations;
    }
}
```

## 使用例

```php
class User implements EntityInterface
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'user_id')]
    public ?string $id = null;
    
    #[HasMany(targetEntity: Post::class, foreignKey: 'user_id', orderBy: 'created_at DESC')]
    public ?array $posts = null;
}

class Post implements EntityInterface
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'post_id')]
    public ?string $id = null;
    
    #[BelongsTo(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'posts')]
    public ?User $user = null;
}
```

## 実装状況

- ✅ **BelongsTo** (旧: ManyToOne) - 実装済み・テスト済み
- ✅ **HasMany** (旧: OneToMany) - 実装済み・テスト済み
- ✅ **HasOne** (旧: OneToOne) - 実装済み（未テスト）
- ⏳ **ManyToMany** - 実装予定

## 考慮事項

1. **循環参照の回避**: 双方向リレーションで無限ループを防ぐ
2. **レイジーローディング**: デフォルトでLAZY、必要に応じてEAGER
3. **バッチローディング**: N+1問題の回避
4. **キャッシュとの統合**: EntityCacheとの連携

