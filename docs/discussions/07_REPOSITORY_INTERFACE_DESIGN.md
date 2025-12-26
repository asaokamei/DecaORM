# RepositoryInterface 設計方針の検討

## 現在の状況

`RepositoryInterface`は、リレーション実装に必要な最低限のメソッドのみを定義しています：

- `getDb()`, `getTableName()`, `getPrimaryKeyColumn()`
- `sqlQuery()`, `sqlInsert()`, `sqlDelete()`, `sqlUpdate()`
- `execute()`, `fetch()`, `find()`
- `listColumnsToProperties()`, `getRepository()`, `getRelation()`, `fill()`

一方で、一般的に使用される以下のメソッドは`AbstractRepository`に実装されていますが、インターフェースには定義されていません：

- `save(EntityInterface $entity): void`
- `findById(int $id): ?EntityInterface`
- `createEntity(array $data): EntityInterface`
- `createAndSave(array $data): ?EntityInterface`
- `delete(EntityInterface $entity): void`

## 2つのアプローチの比較

### アプローチ1: 最小限のインターフェース（現在のアプローチ）

**メリット：**
- ✅ インターフェースがシンプルで理解しやすい
- ✅ リレーション実装に必要なメソッドのみに焦点を当てている
- ✅ 実装の自由度が高い（`save`の実装方法を自由に変更できる）
- ✅ インターフェースが小さく、変更の影響範囲が限定的

**デメリット：**
- ❌ DIコンテナで`RepositoryInterface`を型ヒントにできない（`save`などが使えない）
- ❌ モックを作る際に、すべてのメソッドを定義する必要がある
- ❌ IDEの補完が効かない（インターフェースに定義されていないメソッド）
- ❌ 実装の契約が不明確（どのメソッドが利用可能か分からない）

### アプローチ2: 完全なインターフェース

**メリット：**
- ✅ 型安全性が向上（DIコンテナで`RepositoryInterface`を型ヒントにできる）
- ✅ 実装の契約が明確（すべての公開メソッドが定義されている）
- ✅ IDEの補完が効く
- ✅ モックを作る際に、インターフェースを実装するだけで良い

**デメリット：**
- ❌ インターフェースが大きくなる
- ❌ 実装の自由度が下がる（インターフェースに縛られる）
- ❌ インターフェースの変更が実装に影響する

## 推奨アプローチ

### 推奨: **段階的な拡張アプローチ**

現在の最小限のインターフェースを維持しつつ、**よく使われるメソッドを段階的に追加**することを推奨します。

#### 理由：

1. **現在の設計思想との整合性**
   - DecaORMは「シンプルで明示的」をコンセプトとしている
   - 最小限のインターフェースは、この思想に合致している

2. **実用性のバランス**
   - リレーション実装に必要なメソッドは既に定義されている
   - 一般的なメソッド（`save`, `findById`など）は、必要に応じて追加できる

3. **段階的な拡張が可能**
   - ユーザーからの要望や使用状況に応じて、メソッドを追加できる
   - 破壊的変更を避けながら、機能を拡張できる

### 具体的な提案

#### オプションA: 現在のアプローチを維持（推奨）

**理由：**
- リレーション実装に必要なメソッドは既に定義されている
- 一般的なメソッドは`AbstractRepository`で実装されており、実際の使用には問題ない
- テストコードでも具体的なリポジトリクラスを使っているため、インターフェースの型ヒントは必須ではない

**使用例：**
```php
// 具体的なリポジトリクラスを使用（現在のアプローチ）
class UserService
{
    public function __construct(
        private UserRepository $userRepo  // 具体的なクラス
    ) {}
    
    public function createUser(array $data): User
    {
        return $this->userRepo->createAndSave($data);
    }
}
```

#### オプションB: よく使われるメソッドを追加

もしDIコンテナで`RepositoryInterface`を型ヒントにしたい場合は、以下のメソッドを追加：

```php
interface RepositoryInterface
{
    // ... 既存のメソッド ...
    
    /**
     * Finds a single entity by ID.
     * 
     * @param int|string $id
     * @return T|null
     */
    public function findById(int|string $id): ?EntityInterface;
    
    /**
     * Saves an entity (insert or update).
     * 
     * @param EntityInterface $entity
     * @return void
     */
    public function save(EntityInterface $entity): void;
    
    /**
     * Deletes an entity.
     * 
     * @param EntityInterface $entity
     * @return void
     */
    public function delete(EntityInterface $entity): void;
    
    /**
     * Creates a new entity from data.
     * 
     * @param array $data
     * @return EntityInterface
     */
    public function createEntity(array $data): EntityInterface;
    
    /**
     * Creates and saves a new entity.
     * 
     * @param array $data
     * @return EntityInterface|null
     */
    public function createAndSave(array $data): ?EntityInterface;
}
```

**使用例：**
```php
// インターフェースを使用（オプションB）
class UserService
{
    public function __construct(
        private RepositoryInterface $userRepo  // インターフェース
    ) {}
    
    public function createUser(array $data): EntityInterface
    {
        return $this->userRepo->createAndSave($data);
    }
}
```

## 結論

**現在の最小限のインターフェースアプローチを維持することを推奨します。**

理由：
1. DecaORMの「シンプルで明示的」という設計思想に合致
2. リレーション実装に必要なメソッドは既に定義されている
3. 実際の使用では具体的なリポジトリクラスを使うことが多く、インターフェースの型ヒントは必須ではない
4. 必要に応じて、段階的にメソッドを追加できる

ただし、将来的にDIコンテナで`RepositoryInterface`を型ヒントにしたい場合や、モックを作りやすくしたい場合は、オプションB（よく使われるメソッドを追加）を検討してください。

