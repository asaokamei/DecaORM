# CustomLoader設計の検討

## 現状の問題点

### 1. 型不整合の問題

現在の実装では以下の型不整合が発生する可能性がある：

- `fill()`メソッドは常に`EntityCollection`を返す
- `EntityCollection`は`EntityInterface[]`を前提としている（`getIds()`, `findById()`など）
- `LoadCustomLoader::load()`は`EntityInterface[]`を返すことを前提としている
- しかし、CustomLoaderで`postCount`のような数値を返す場合、`EntityCollection`に数値の配列を渡すことになり、型不整合が発生する

```php
// 例：postCountを返すCustomLoader
#[CustomLoader(targetEntity: Post::class, method: 'loadPostCount')]
public int $postCount = 0;

// fill()の返り値はEntityCollectionだが、postCountは数値
$collection = $userRepo->fill($users, 'postCount'); // 型不整合
$collection->getIds(); // エラー：数値にはgetId()がない
```

### 2. ORM本来のリレーションから外れる

CustomLoaderを計算値（数値、文字列など）に使うと、ORMのリレーション概念から外れる：

- リレーションは通常、エンティティ間の関係を表す
- 計算値はリレーションではなく、エンティティの属性として扱うべき
- `fill()`メソッドは「リレーションを埋める」という意味を持つが、計算値はリレーションではない

## 設計オプション

### オプション1: CustomLoaderをリレーション専用に制限（現状維持）

**メリット:**
- 型安全性が保たれる
- ORMの概念が明確
- `fill()`の返り値が一貫している

**デメリット:**
- 計算値（postCountなど）を別の方法で実装する必要がある
- ユーザーの要望（何でもLoadできる）を満たせない

### オプション2: fill()の返り値を柔軟にする

`fill()`メソッドの返り値を、リレーションタイプによって変える：

```php
public function fill(EntityInterface|array $entities, string $relationName): EntityCollection|mixed
{
    // ...
    if ($relation instanceof CustomLoader) {
        $results = LoadCustomLoader::load($entities, $relation, $this);
        // CustomLoaderの場合は、返り値の型をチェック
        if (empty($results) || !($results[0] instanceof EntityInterface)) {
            // EntityInterfaceでない場合は、そのまま返す（または別の処理）
            return $results; // 数値配列など
        }
    }
    return new EntityCollection($results, $targetRepo);
}
```

**メリット:**
- CustomLoaderで何でも返せる
- 柔軟性が高い

**デメリット:**
- `fill()`の返り値の型が一貫しない
- 呼び出し側で型チェックが必要
- `EntityCollection`のメソッドが使えない（数値配列の場合）

### オプション3: CustomLoaderを2つのタイプに分ける

#### 3-1. CustomLoader（リレーション用）とCustomAttribute（計算値用）を分ける

```php
// リレーション用（EntityInterface[]を返す）
#[CustomLoader(targetEntity: Task::class, method: 'findTasks')]
public array $tasks = [];

// 計算値用（何でも返せる）
#[CustomAttribute(method: 'loadPostCount')]
public int $postCount = 0;
```

**メリット:**
- 用途が明確に分かれる
- 型安全性が保たれる
- `fill()`はリレーション専用、`loadAttribute()`は計算値専用

**デメリット:**
- 新しい属性とメソッドが必要
- APIが複雑になる

#### 3-2. CustomLoaderに`isRelation`フラグを追加

```php
#[CustomLoader(targetEntity: Post::class, method: 'loadPostCount', isRelation: false)]
public int $postCount = 0;
```

**メリット:**
- 既存のCustomLoaderを拡張
- 型チェックが可能

**デメリット:**
- `fill()`の返り値が一貫しない
- 実装が複雑になる

### オプション4: fill()と別のメソッドを用意する

```php
// リレーション用（EntityCollectionを返す）
public function fill(EntityInterface|array $entities, string $relationName): EntityCollection

// 計算値用（何でも返せる）
public function loadAttribute(EntityInterface|array $entities, string $attributeName): mixed
```

**メリット:**
- 用途が明確に分かれる
- 型安全性が保たれる
- `fill()`はリレーション専用

**デメリット:**
- 新しいメソッドが必要
- APIが複雑になる

### オプション5: CustomLoaderの返り値を常にvoidにして、エンティティに直接設定

現在の実装では、CustomLoaderのメソッドは`void`を返すか`EntityInterface[]`を返すかのどちらか。計算値の場合は常に`void`を返すようにする：

```php
public function loadPostCount(EntityInterface|array $entities): void
{
    $entities = is_array($entities) ? $entities : [$entities];
    // 計算して直接エンティティに設定
    foreach ($entities as $entity) {
        $count = $this->calculatePostCount($entity);
        $entity->set('postCount', $count);
    }
}

// fill()の返り値は空のEntityCollection
$collection = $userRepo->fill($users, 'postCount'); // 空のEntityCollection
$user->get('postCount'); // 数値が取得できる
```

**メリット:**
- 既存のAPIを変更せずに済む
- CustomLoaderで何でも設定できる
- `fill()`の返り値は常に`EntityCollection`（空の場合もある）

**デメリット:**
- `fill()`の返り値が意味を持たない場合がある（空のEntityCollection）
- 計算値の場合、`fill()`の返り値を使えない

## 重要な設計原則

### fill()がEntityCollectionを返すことの重要性

`fill()`が`EntityCollection`を返すことで、リレーションのチェーン読み込みが可能になる：

```php
// Collectionオブジェクトからチェーンでリレーションを読み込む
$users = $userRepo->sqlQuery()->...->getResult();
$posts = $users->fill('posts');  // EntityCollection
$comments = $posts->fill('comments');  // さらに先のリレーション
$authors = $comments->fill('author');  // さらに先のリレーション
```

このメリットは極めて大きいため、**`fill()`は常に`EntityCollection`を返すという挙動は変更しない**。

## 推奨案

### 推奨: オプション5（CustomLoaderの返り値をvoidにして、エンティティに直接設定）

**理由:**
1. **既存のAPIを変更せずに済む**: `fill()`の返り値は常に`EntityCollection`（リレーションチェーンのメリットを維持）
2. **柔軟性**: CustomLoaderで何でも設定できる（数値、文字列、配列など）
3. **実装が簡単**: 既存の`LoadCustomLoader`を少し修正するだけ
4. **一貫性**: `fill()`は常に`EntityCollection`を返す（計算値の場合は空の`EntityCollection`）

**実装方針:**
- CustomLoaderのメソッドは`void`を返すか`EntityInterface[]`を返すかのどちらか
- `void`を返す場合：エンティティに直接設定（計算値など）
- `EntityInterface[]`を返す場合：リレーションエンティティを返す（従来通り）
- `fill()`の返り値は、**常に`EntityCollection`を返す**（`EntityInterface[]`が返された場合はそのエンティティを含む、`void`の場合は空の`EntityCollection`）
- 計算値の場合、`fill()`の返り値（空の`EntityCollection`）は使わず、エンティティから直接値を取得する

**使用例:**
```php
// リレーション用（EntityInterface[]を返す）
public function findTasks(EntityInterface|array $entities): array
{
    // ... タスクを取得して返す
    return $tasks; // EntityInterface[]
}

// 計算値用（voidを返す）
public function loadPostCount(EntityInterface|array $entities): void
{
    $entities = is_array($entities) ? $entities : [$entities];
    foreach ($entities as $entity) {
        $count = $this->calculatePostCount($entity);
        $entity->set('postCount', $count);
    }
}

// 使用例1: リレーション（チェーン可能）
$projects = $projectRepo->sqlQuery()->...->getResult();
$tasks = $projects->fill('tasks'); // EntityCollection（タスクが入っている）
$comments = $tasks->fill('comments'); // さらに先のリレーションをチェーンで読み込める

// 使用例2: 計算値（チェーンはできないが、エンティティに値が設定される）
$users = $userRepo->sqlQuery()->...->getResult();
$collection = $users->fill('postCount'); // EntityCollection（空、postCountはエンティティに設定済み）
$count = $users[0]->get('postCount'); // 数値が取得できる

// 使用例3: リレーションと計算値を組み合わせる
$users = $userRepo->sqlQuery()->...->getResult();
$posts = $users->fill('posts'); // リレーション（EntityCollectionにPostが入る）
$users->fill('postCount'); // 計算値（空のEntityCollection、postCountはエンティティに設定済み）
$count = $users[0]->get('postCount'); // 数値
```

### 代替案: オプション4（fill()と別のメソッドを用意）

より明確に用途を分けたい場合は、オプション4も検討可能：

```php
// リレーション用
$tasks = $projectRepo->fill($projects, 'tasks'); // EntityCollection

// 計算値用
$userRepo->loadAttribute($users, 'postCount'); // void、postCountはエンティティに設定済み
$count = $user->get('postCount'); // 数値
```

## 最終的な設計決定

### 決定事項

1. **`fill()`のシグネチャは変更しない**: 常に`EntityCollection`を返す（リレーションチェーンのメリットを維持）

2. **Loaderの返り値で判断**: CustomLoaderのメソッドが`void`を返すか`EntityInterface[]`を返すかで判断

3. **`CustomLoader::targetEntity`をオプショナルにする**: 計算値の場合は`targetEntity`を指定しなくて済む

4. **`EntityCollection`を汎用Collectionとして使う**: 数値配列なども扱えるようにする
   - 一部のメソッド（`fill()`, `save()`, `findById()`, `getIds()`など）は`EntityInterface`を前提とする
   - これらのメソッドは、`EntityInterface`でない場合はエラーにならないようチェックする
   - 使う人が適切に使うか、エラーにならないようチェックする

### 実装方針

- CustomLoaderのメソッドが`void`を返す場合：エンティティに直接設定（計算値など）
- CustomLoaderのメソッドが`EntityInterface[]`を返す場合：リレーションエンティティを返す（従来通り）
- `fill()`の返り値は、**常に`EntityCollection`を返す**（`EntityInterface[]`が返された場合はそのエンティティを含む、`void`の場合は空の`EntityCollection`）
- `EntityCollection`は汎用Collectionとして使える（`map()`, `filter()`, `sort()`などは任意の配列で動作）
- `EntityInterface`を前提とするメソッドは、チェックしてエラーにならないようにする

### メリット

1. **リレーションチェーンを維持**: `fill()`は常に`EntityCollection`を返すため、リレーションのチェーン読み込みが可能
2. **柔軟性**: CustomLoaderで何でも設定できる（数値、文字列、配列など）
3. **汎用性**: `EntityCollection`を汎用Collectionとして使える（計算値の配列も扱える）
4. **実装が簡単**: 既存のコードを少し修正するだけ

### 使用例

```php
// Entity定義（計算値の場合、targetEntityは不要）
#[CustomLoader(method: 'loadPostCount')]
public int $postCount = 0;

// Repository
public function loadPostCount(EntityInterface|array $entities): void
{
    // ... 計算してエンティティに設定
}

// 使用例1: 計算値（EntityCollectionを汎用Collectionとして使う）
$users = $userRepo->sqlQuery()->whereIn('user_id', [1, 2, 3])->getResult();
$collection = $userRepo->fill($users, 'postCount'); // EntityCollection（空、または数値配列）
$counts = $collection->getEntities(); // 数値配列（もし返り値があれば）
$count = $users[0]->get('postCount'); // 数値が取得できる

// 使用例2: リレーション（チェーン可能）
$users = $userRepo->sqlQuery()->...->getResult();
$posts = $users->fill('posts'); // EntityCollection（Post[]）
$comments = $posts->fill('comments'); // さらに先のリレーションをチェーンで読み込める
```

これにより、既存のAPIを変更せずに、CustomLoaderで何でも設定できるようになり、かつリレーションチェーンのメリットも維持される。

## 実装の詳細

### 1. CustomLoader::targetEntityをオプショナルにする

```php
// src/Attribute/CustomLoader.php
public function __construct(
    public ?string $targetEntity = null,  // オプショナルに
    public string $method
) {
}
```

計算値の場合は`targetEntity`を指定しなくて済む：

```php
// 計算値の場合
#[CustomLoader(method: 'loadPostCount')]
public int $postCount = 0;

// リレーションの場合
#[CustomLoader(targetEntity: Task::class, method: 'findTasks')]
public array $tasks = [];
```

### 2. RepositoryTrait::fill()でtargetEntityがnullの場合の処理

```php
// src/Trait/RepositoryTrait.php
elseif ($relation instanceof CustomLoader) {
    $results = LoadCustomLoader::load($entities, $relation, $this);
    // CustomLoaderの場合、targetEntityがnullの可能性がある（計算値の場合）
    $targetRepo = $relation->targetEntity 
        ? $this->getRepository($relation->targetEntity) 
        : null;
    return new EntityCollection($results, $targetRepo);
}
```

### 3. EntityCollectionを汎用Collectionとして使う

`EntityCollection`に`isEntityCollection()`メソッドを追加し、`EntityInterface`を前提とするメソッドでチェック：

```php
// src/EntityCollection.php
private function isEntityCollection(): bool
{
    if (empty($this->entities)) {
        return false;
    }
    foreach ($this->entities as $entity) {
        if (!($entity instanceof EntityInterface)) {
            return false;
        }
    }
    return true;
}
```

`EntityInterface`を前提とするメソッドでチェックを追加：

- `fill()`: `EntityInterface`と`repository`が必要な場合は例外を投げる
- `save()`: `EntityInterface`と`repository`が必要な場合は例外を投げる
- `findById()`, `hasId()`, `getIds()`, `getIdMap()`: `EntityInterface`でない場合は空配列や`null`を返す
- `getValues()`, `sort()`, `groupBy()`: `EntityInterface`でない場合は適切に処理

### 4. エラーハンドリングの方針

- **エラーを投げる**: `fill()`, `save()`など、`EntityInterface`と`repository`が必須のメソッド
- **空配列や`null`を返す**: `findById()`, `hasId()`, `getIds()`など、`EntityInterface`でない場合は意味を持たないメソッド
- **例外を投げる**: `groupBy()`, プロパティベースの`sort()`など、`EntityInterface`が必須のメソッド

これにより、`EntityCollection`を汎用Collectionとして使えるようになり、計算値の配列なども扱える。

## 設計のメリット・デメリット

### メリット

1. **リレーションチェーンを維持**: `fill()`は常に`EntityCollection`を返すため、リレーションのチェーン読み込みが可能
   ```php
   $users->fill('posts')->fill('comments')->fill('author');
   ```

2. **既存のAPIを変更しない**: `fill()`のシグネチャは変更不要

3. **柔軟性**: CustomLoaderで何でも設定できる（数値、文字列、配列など）

4. **実装が簡単**: 既存の`LoadCustomLoader`は既に`void`を返す場合に対応している

5. **一貫性**: `fill()`は常に`EntityCollection`を返す（計算値の場合は空）

### デメリット

1. **計算値の場合、fill()の返り値が意味を持たない**: 空の`EntityCollection`が返されるが、使わない

2. **targetEntityが意味を持たない場合がある**: 計算値の場合、`targetEntity`を指定する必要があるが、実際には使われない

3. **ORMの概念から外れる**: 計算値はリレーションではないが、`fill()`メソッドで読み込む

### 代替案との比較

| 項目 | 推奨案（オプション5） | オプション4（別メソッド） |
|------|---------------------|----------------------|
| リレーションチェーン | ✅ 維持される | ❌ 計算値は別メソッドのためチェーン不可 |
| APIの一貫性 | ✅ `fill()`は常に`EntityCollection` | ⚠️ `fill()`と`loadAttribute()`の2つ |
| 実装の複雑さ | ✅ 既存コードで対応可能 | ⚠️ 新しいメソッドが必要 |
| 型安全性 | ✅ `fill()`の返り値は常に`EntityCollection` | ✅ 用途が明確に分かれる |

### 最終的な推奨

**オプション5（現状維持）を推奨**。理由：

1. リレーションチェーンのメリットが極めて大きい
2. 既存のAPIを変更する必要がない
3. 実装が簡単
4. 計算値の場合、`fill()`の返り値が使われないというデメリットは、リレーションチェーンのメリットと比較して小さい

計算値の場合、`fill()`の返り値（空の`EntityCollection`）は使わず、エンティティから直接値を取得するという使い方で十分実用的。

## 実装の詳細

### 現在の実装状況

現在の`LoadCustomLoader::load()`は既に`void`を返す場合に対応している：

```php
// LoadCustomLoader.php (現在の実装)
$result = $repository->{$relation->method}($entities);

// If result is EntityInterface[], return it
// If result is void or null, return empty array (mapping was done in loader)
if (is_array($result)) {
    return $result;
}

return [];
```

### 問題点

1. **targetEntityの問題**: 計算値の場合、`targetEntity`が適切でない可能性がある
   - 例：`postCount`の場合、`targetEntity: Post::class`だが、実際には数値を返す
   - `fill()`で`targetRepo`を取得する際に、`Post::class`のリポジトリを取得しようとするが、実際には不要

2. **EntityCollectionの作成**: 計算値の場合、空の`EntityCollection`を作成するが、`targetRepo`が適切でない可能性がある

### 実装の修正案

#### 修正案1: targetEntityをオプショナルにする

```php
// CustomLoader.php
public function __construct(
    public ?string $targetEntity = null,  // オプショナルに
    public string $method
) {
}
```

**メリット:**
- 計算値の場合、`targetEntity`を指定しなくて済む
- リレーションの場合のみ`targetEntity`を指定

**デメリット:**
- `fill()`で`targetRepo`を取得する際に、`null`チェックが必要
- 既存のコードに影響する可能性がある

#### 修正案2: fill()でCustomLoaderの場合の処理を分ける

```php
// RepositoryTrait.php
public function fill(EntityInterface|array $entities, string $relationName): EntityCollection
{
    $relation = $this->hydrator->getRelation($relationName);
    
    if ($relation instanceof CustomLoader) {
        $results = LoadCustomLoader::load($entities, $relation, $this);
        // CustomLoaderの場合、targetRepoはオプショナル
        $targetRepo = $relation->targetEntity 
            ? $this->getRepository($relation->targetEntity) 
            : null;
        return new EntityCollection($results, $targetRepo);
    }
    
    $targetRepo = $this->getRepository($relation->targetEntity);
    // ... 他のリレーション処理
}
```

**メリット:**
- 既存のコードへの影響が少ない
- `targetRepo`が`null`でも`EntityCollection`は動作する（空の場合）

**デメリット:**
- `EntityCollection`の一部メソッドが`targetRepo`を前提としている可能性がある

#### 修正案3: 現状維持（推奨）

現在の実装で、計算値の場合も`targetEntity`を指定する（例：`Post::class`）。`fill()`の返り値（空の`EntityCollection`）は使わない。

**メリット:**
- 既存のコードを変更する必要がない
- 実装が簡単

**デメリット:**
- `targetEntity`が意味を持たない場合がある（計算値の場合）

### 推奨される実装

**修正案3（現状維持）を推奨**。理由：

1. **既存のコードを変更する必要がない**: `LoadCustomLoader`は既に`void`を返す場合に対応している
2. **実装が簡単**: 追加の修正は不要
3. **使用例が明確**: 計算値の場合、`fill()`の返り値は使わず、エンティティから直接取得
4. **リレーションチェーンを維持**: `fill()`は常に`EntityCollection`を返すため、リレーションのチェーン読み込みが可能

**使用例:**
```php
// Entity定義
#[CustomLoader(targetEntity: Post::class, method: 'loadPostCount')]
public int $postCount = 0;

// Repository
public function loadPostCount(EntityInterface|array $entities): void
{
    $entities = is_array($entities) ? $entities : [$entities];
    $userIds = array_map(fn($e) => $e->getId(), $entities);
    
    // バッチでカウントを取得
    $counts = $this->db->query("
        SELECT user_id, COUNT(*) as count 
        FROM posts 
        WHERE user_id IN (" . implode(',', $userIds) . ")
        GROUP BY user_id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $countMap = [];
    foreach ($counts as $row) {
        $countMap[$row['user_id']] = (int)$row['count'];
    }
    
    // エンティティに設定
    foreach ($entities as $entity) {
        $entity->set('postCount', $countMap[$entity->getId()] ?? 0);
    }
}

// 使用例1: 計算値のみ
$users = $userRepo->sqlQuery()->whereIn('user_id', [1, 2, 3])->getResult();
$collection = $userRepo->fill($users, 'postCount'); // 空のEntityCollection（使わない）
$count = $users[0]->get('postCount'); // 数値が取得できる

// 使用例2: リレーションと計算値を組み合わせる
$users = $userRepo->sqlQuery()->whereIn('user_id', [1, 2, 3])->getResult();
$posts = $users->fill('posts'); // リレーション（EntityCollectionにPostが入る）
$users->fill('postCount'); // 計算値（空のEntityCollection、postCountはエンティティに設定済み）
$count = $users[0]->get('postCount'); // 数値
$userPosts = $users[0]->get('posts'); // Post[]
```

**注意点:**
- `fill()`の返り値（`EntityCollection`）は、計算値の場合は使わない（空の`EntityCollection`が返される）
- エンティティから直接値を取得する（`$entity->get('postCount')`）
- `targetEntity`は指定する必要があるが、計算値の場合は実際には使われない（型チェックのため）
- リレーションの場合（`EntityInterface[]`を返す場合）は、`fill()`の返り値を使ってチェーン読み込みが可能

