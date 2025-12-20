# リレーションアトリビュート

DecaORMでは、エンティティ間のリレーションをアトリビュートで定義します。DoctrineORMスタイルではなく、より直感的な命名（Rails/ActiveRecordスタイル）を採用しています。

## アトリビュート一覧



|リレーション  |   親    |子（外部キーを持つ|
|------------|-----------|--------|
|OneToMany  |HasMany     |BelongsTo|
|OneToOne   |HasOne?     |BelongsToOne?|
|ManyToMany |ManyToMany|ManyToMany|

### 実装済み

#### 1. `BelongsTo` (多対一)
外部キーを持つ側（子側）で使用します。

```php
#[BelongsTo(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'posts')]
public ?User $user = null;
```

**パラメータ:**
- `targetEntity` (必須): 関連先のエンティティクラス名
- `foreignKey` (必須): 外部キーカラム名
- `inversedBy` (オプション): 逆側のプロパティ名（双方向リレーションの場合）
- `fetch` (オプション): フェッチ戦略（'LAZY' または 'EAGER'、デフォルト: 'LAZY'）

**使用例:**
- Post が User に属する場合
- Comment が Post に属する場合

#### 2. `HasMany` (一対多)
外部キーを持たない側（親側）で使用します。

```php
#[HasMany(targetEntity: Post::class, foreignKey: 'user_id', orderBy: 'created_at DESC')]
public ?array $posts = null;
```

**パラメータ:**
- `targetEntity` (必須): 関連先のエンティティクラス名
- `foreignKey` (必須): 子側のテーブルの外部キーカラム名
- `orderBy` (オプション): ソート順（例: 'created_at DESC'）
- `fetch` (オプション): フェッチ戦略（'LAZY' または 'EAGER'、デフォルト: 'LAZY'）

**使用例:**
- User が複数の Post を持つ場合
- Post が複数の Comment を持つ場合

#### 3. `HasOne` (一対一)
1対1のリレーションで使用します。どちらの側でも使用可能です。

```php
// 外部キーが相手側にある場合（デフォルト）
#[HasOne(targetEntity: Profile::class, foreignKey: 'user_id')]
public ?Profile $profile = null;

// 外部キーがこの側にある場合
#[HasOne(targetEntity: User::class, foreignKey: 'profile_id', onThisSide: true)]
public ?User $user = null;
```

**パラメータ:**
- `targetEntity` (必須): 関連先のエンティティクラス名
- `foreignKey` (必須): 外部キーカラム名
- `onThisSide` (オプション): 外部キーがこの側にある場合 `true`（デフォルト: `false`）
- `inversedBy` (オプション): 逆側のプロパティ名（双方向リレーションの場合）
- `fetch` (オプション): フェッチ戦略（'LAZY' または 'EAGER'、デフォルト: 'LAZY'）

**使用例:**
- User が Profile を1つ持つ場合
- Profile が User に属する場合

### 実装予定

#### 4. `ManyToMany` (多対多)
多対多のリレーションで使用します。中間テーブルが必要です。

```php
#[ManyToMany(targetEntity: Tag::class, joinTable: 'post_tags', foreignKey: 'post_id', inverseForeignKey: 'tag_id')]
public ?array $tags = null;
```

**パラメータ:**
- `targetEntity` (必須): 関連先のエンティティクラス名
- `joinTable` (必須): 中間テーブル名
- `foreignKey` (必須): この側の外部キーカラム名（中間テーブル内）
- `inverseForeignKey` (必須): 相手側の外部キーカラム名（中間テーブル内）
- `orderBy` (オプション): ソート順（例: 'created_at DESC'）
- `fetch` (オプション): フェッチ戦略（'LAZY' または 'EAGER'、デフォルト: 'LAZY'）

**注意:** ManyToManyリレーションでは、双方向リンクは自動的に設定されません。部分的なデータを設定することは誤解を招くため、必要に応じて明示的に`fill()`を呼び出してください。

**使用例:**
- Post が複数の Tag を持つ場合
- Tag が複数の Post に付けられる場合

## リレーションタイプ別のアトリビュート選択

### OneToMany (一対多) の場合

**親側（外部キーを持たない側）:**
```php
#[HasMany(targetEntity: Post::class, foreignKey: 'user_id')]
public ?array $posts = null;
```

**子側（外部キーを持つ側）:**
```php
#[BelongsTo(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'posts')]
public ?User $user = null;
```

### OneToOne (一対一) の場合

**外部キーを持たない側:**
```php
#[HasOne(targetEntity: Profile::class, foreignKey: 'user_id')]
public ?Profile $profile = null;
```

**外部キーを持つ側:**
```php
#[HasOne(targetEntity: User::class, foreignKey: 'profile_id', onThisSide: true)]
public ?User $user = null;
```

または、`BelongsTo`を使用することも可能（将来的に検討）:
```php
#[BelongsTo(targetEntity: User::class, foreignKey: 'profile_id')]
public ?User $user = null;
```

### ManyToMany (多対多) の場合（実装予定）

**両側で同じアトリビュートを使用:**
```php
// Post側
#[ManyToMany(targetEntity: Tag::class, joinTable: 'post_tags', foreignKey: 'post_id', inverseForeignKey: 'tag_id')]
public ?array $tags = null;

// Tag側
#[ManyToMany(targetEntity: Post::class, joinTable: 'post_tags', foreignKey: 'tag_id', inverseForeignKey: 'post_id')]
public ?array $posts = null;
```

## 完全な使用例

```php
// Userエンティティ
#[Table(name: 'users')]
class User implements EntityInterface
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'user_id')]
    public ?string $id = null;

    #[Column(name: 'user_name')]
    public string $name = '';

    // OneToMany: User has many Posts
    #[HasMany(targetEntity: Post::class, foreignKey: 'user_id', orderBy: 'created_at DESC')]
    public ?array $posts = null;

    // OneToOne: User has one Profile
    #[HasOne(targetEntity: Profile::class, foreignKey: 'user_id')]
    public ?Profile $profile = null;
}

// Postエンティティ
#[Table(name: 'posts')]
class Post implements EntityInterface
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'post_id')]
    public ?string $id = null;

    // BelongsTo: Post belongs to User
    #[BelongsTo(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'posts')]
    public ?User $user = null;

    #[Column(name: 'title')]
    public string $title = '';
}
```

## リレーションのロード

現在の実装では、リレーションをロードするためにリポジトリメソッドを呼び出す必要があります：

```php
// PostにUserを読み込む
$userRepo->loadUserForPost($postRepo, $post);

// UserにPostsを読み込む
$postRepo->loadPostsForUser($userRepo, $user);
```

将来的には、より自動的なロード機能を追加する予定です。

## 実装状況

- ✅ `BelongsTo` - 実装済み
- ✅ `HasMany` - 実装済み
- ✅ `HasOne` - 実装済み（未テスト）
- ⏳ `ManyToMany` - 実装予定

