# DecaORM

DecaORMは、PHP 8のアトリビュート（Attribute）を活用した、シンプルで軽量なデータマッパー型ORMライブラリです。
エンティティとデータベーステーブルのマッピングを直感的に定義でき、リポジトリパターンによる柔軟なデータアクセスを提供します。

### 対応

PHP: 8.1, 8.2, 8.3, 8.4  
データベース: SQLite, MySQL, PostgreSQL

## 特徴

*   **Attribute Mapping**: PHP 8のアトリビュート（`#[Table]`, `#[Column]`, `#[Id]`など）を使用して、マッピング情報をエンティティクラスに直接記述できます。
*   **Repository Pattern**: データアクセスロジックをリポジトリに分離し、保守性の高いコードを実現します。
*   **Relations**: `#[HasOne]`, `#[HasMany]`, `#[BelongsTo]`, `#[BelongsToOne]`, `#[ManyToMany]` に加え、多態関連の `#[MorphTo]`, `#[MorphToOne]`（子が FK + 型判別子で複数種の親を指す）をサポートしています。
*   **Lazy Loading**: ゲッター内で `load()` を呼ぶことで、リレーションに初回アクセスしたタイミングで読み込む遅延読み込みパターンが利用できます。
*   **Batch Loading**: N+1問題を解決するためのバッチローディング機能を提供します。
*   **Identity Map**: 同じ主キーを持つエンティティインスタンスが複数存在しないことを保証し、メモリ上の一意性を管理します。
*   **Dirty Tracking**: エンティティの変更を追跡し、変更されたフィールドのみを更新することで、不要なUPDATEクエリを削減します。
*   **Lifecycle Callbacks**: `#[CreatedAt]`, `#[UpdatedAt]` によるタイムスタンプの自動更新に対応しています。
*   **Flexible Hydrator**: 標準の `AttributeHydrator` に加え、パフォーマンスを重視したカスタムHydratorの実装も可能です。
*   **Simple & Explicit**: シンプルで明示的な設計により、コードを見ただけで何が起きるか予測できます。
*   **Repository hooks**: 任意の `RepositoryHooksInterface` により、テナント境界・論理削除・楽観ロックなど横断ルールを差し込めます（[repository-hooks-ja.md](docs/repository-hooks-ja.md)）。

### サポートされていない機能

次の機能はサポートされていません。

*   **Unit of Work (UoW)**: エンティティの保存順序の自動解決や、変更の遅延書き込み（flush）は実装されていません。依存性を考慮して手動で保存順序を制御する必要があります。
*   **カスケード削除**: 親エンティティを削除した際に、関連する子エンティティを自動的に削除する機能はありません。手動で削除する必要があります。
*   **Eager Loading（一括先行読み込み）**: リレーションの自動一括読み込みはありません。必要に応じて `load()` を明示的に呼ぶか、ゲッターで Lazy Loading を実装してください。

### ライセンス

MIT License

### インストール

Composerでインストールしてください。

```bash
composer require wscore/decaorm
```

### ドキュメント

* [entity.md](docs/entity-ja.md)
* [sql.md](docs/sql-ja.md)
* [repository-hooks.md](docs/repository-hooks-ja.md)

**English:** [README.md](README.md) | [entity-en.md](docs/entity-en.md) | [sql-en.md](docs/sql-en.md) | [repository-hooks-en.md](docs/repository-hooks-en.md)

## 使い方

### 1. エンティティの定義

`WScore\DecaORM\Attribute` 名前空間のアトリビュートを使用して、エンティティクラスを定義します。
`EntityInterface` を実装し、`EntityTrait` を利用することで、基本的なエンティティ機能が提供されます。

```php
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;

#[Table(name: 'users')]               // 必須アトリビュート
#[Repository(UserRepository::class)]  // 必須アトリビュート
class User implements EntityInterface
{
    use EntityTrait;

    #[Id]                       // プライマリキーとして指定
    #[GeneratedValue]           // AutoNumberingなど
    #[Column(name: 'user_id')]  // DBコラム名など指定
    private ?int $id = null;    // プロパティ型の詳細は docs/entity.md 参照

    #[Column(name: 'name')]     // DBコラム名。同じ場合はnameは省略可能
    private string $name = '';  // 

    public function getId(): int 
    {
        return (int) $this->id;
    }
    public function getName(): string 
    {
        return $this->name;
    }
}
```


### 2. リポジトリの実装

`AbstractRepository` を継承して、特定エンティティ用のリポジトリを作成します。

```php
use PDO;
use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\AttributeHydrator;

/**
 * @extends AbstractRepository<User>
 */
class UserRepository extends AbstractRepository
{
    public function __construct(OrmManager $manager)
    {
        $this->setUpRepository($manager, null, User::class);
    }
}
```

### 3. 基本的な操作 (CRUD)

```php
$pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
$manager = OrmManager::initialize($container);
$userRepo = new UserRepository($manager);

// --- 作成 (Create) ---
$user = new User();
$user->fill(['name' => 'Deca Taro']);
$user->save(); // INSERTが実行され、IDが自動採番されます
echo $user->getId(); 

// --- 取得 (Read) ---
$user = $userRepo->findById(1);
if ($user) {
    echo $user->getName();
}

// --- 更新 (Update) ---
$user->setName('Deca Jiro');  // または setRaw('name', 'Deca Jiro')
$user->save(); // IDが存在するためUPDATEが実行されます

// --- 削除 (Delete) ---
$user->delete();
```

## リレーションの利用

リレーションデータは自動的には読み込まれません。`load()` を明示的に呼ぶか、ゲッターで Lazy Loading を実装して初回アクセス時に読み込むことができます。

### 親エンティティ (例: User)

```php
class User implements EntityInterface
{
    ... 
    // リレーションの定義（1対多）
    // targetEntity: 関連先クラス
    // mappedBy: 関連先でのプロパティ名
    #[HasMany(targetEntity: Post::class, mappedBy: 'user')]
    private ?array $posts = null;

    public function getPosts(): EntityCollection
    {
        return $this->load('posts'); // 直接loadを呼ぶことも可能
    }
    /**
     * @param EntityCollection<Post>|null $posts
     */
    public function setPosts(?EntityCollection $posts): void
    {
        $this->associate('posts', $posts); // 直接associateを呼ぶことも可能
    }

    public function addPost(Post $post): void
    {
        $this->addHasMany('posts', $post);
    }

    public function removePost(Post $post): void
    {
        $this->removeHasMany('posts', $post);
    }
}
```

### 子エンティティ (例: Post)

```php
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;

#[Table(name: 'posts')]
#[Repository(PostRepository::class)]
class Post implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'post_id')]
    private ?int $id = null;

    #[Column(name: 'user_id')]
    private ?int $user_id = null; // ユーザー用外部キープロパティ

    #[Column(name: 'title')]
    private string $title = '';

    // リレーションの定義（多対1）
    // foreignKey: 外部キープロパティ名
    // inversedBy: 関連先（親）でのプロパティ名
    #[BelongsTo(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'posts')]
    private ?User $user = null;
}
```

### Lazy Loading（遅延読み込み）

ゲッター内で `load($relationName)` を呼ぶと、そのリレーションへ初回アクセスしたときにだけ DB から読み込み、以降はキャッシュされた値が返ります。

```php
// 利用側: 初回アクセス時にのみクエリが発行される
$user = $userRepo->findById(1);
$posts = $user->load('posts');  // ここで SELECT が実行される
$posts = $user->load('posts');  // 2回目はキャッシュから返る
```

### associate() による関連づけ

直接 `associate()` を呼ぶこともできます（汎用コードやハイドレーション時など）。

エンティティ側でリレーションを設定するには、公開 API の `associate($relationName, $targetOrTargets)` を使います。リレーションの種類に応じて、内部で適切な処理（FK の設定・逆参照の更新など）が行われます。

```php
$post->associate('user', $user);
$user->associate('roles', $roleCollection);
```

**補足**: `associate()` はエンティティのメモリ上での関連づけのみ行います。ManyToMany の中間テーブルへ反映するには、リポジトリの `syncManyToMany($entity, $relationName)` を別途呼び出してください。

- **BelongsTo / BelongsToOne / HasOne**: 単一エンティティまたは `null` を渡す。
- **MorphTo / MorphToOne**: 単一エンティティまたは `null` を渡す（FK・型判別子・プロパティが `associate()` で更新される）。
- **HasMany / ManyToMany**: コレクション（`EntityCollection` または `iterable`）または `null` を渡す。

setter から呼び出すと、型安全にリレーションを更新できます。


### バッチローディング（N+1問題の解決）

複数のエンティティに対して、一度のクエリで関連データを読み込むことができます。

```php
// 複数のユーザーを取得
$users = $userRepo->sqlQuery()
    ->whereIn('user_id', [1, 2, 3, 4, 5])
    ->getResult();

// 一度のクエリで全ユーザーの投稿を読み込む（N+1問題を回避）
$posts = $users->load('posts');

// $posts は EntityCollection
$titles = $posts->map(fn($e) => $e->getRaw('title'));

// 各ユーザーの投稿にアクセス
foreach ($users as $user) {
    foreach ($user->getPosts() as $post) {
        echo $post->getRaw('title');  // または getTitle() など
    }
}
```

### Collectionオブジェクトの利用

複雑な条件で複数のエンティティを取得するためにCollectionオブジェクトが用意されている。また、Collectionオブジェクトからは、バッチローディングを簡単に行う機能が用意されている。

```php
// Collectionオブジェクト
$users = $userRepo->sqlQuery()->...->getResult();
// リレーションを読み込む
$posts = $users->load('posts');
$comments = $posts->load('comments');
// エンティティの保存など
$posts->save();
$comments->save();
```

### ManyToManyリレーションの利用

多対多のリレーションでは、中間テーブルを使用して関連付けを管理します。

ManyToManyアトリビュートにおいては、DBでのテーブル名とコラム名を指定してください。JoinTable用のレポジトリやエンティティを作成しないためです。

```php
class User implements EntityInterface
{
    ...
    /** @var EntityCollection<Role>|null */
    #[ManyToMany(
        targetEntity: Role::class,
        joinTable: 'user_role',       // DBテーブル名
        foreignKey: 'user_id',        // 外部キーコラム名
        inverseForeignKey: 'role_id'  // 外部キーコラム名
    )]
    private ?EntityCollection $roles = null;

    public function getRoles(): EntityCollection
    {
        return $this->load('roles');
    }

    /**
     * @param EntityCollection<Role>|null $roles
     */
    public function setRoles(?EntityCollection $roles): void
    {
        $this->associate('roles', $roles);
    }
}

#[Table(name: 'roles')]
#[Repository(RoleRepository::class)]
class Role implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'role_id')]
    public ?string $id = null;

    #[Column(name: 'role_name')]
    public string $name = '';

    /** @var EntityCollection<User>|null */
    #[ManyToMany(
        targetEntity: User::class,
        joinTable: 'user_role',
        foreignKey: 'role_id',
        inverseForeignKey: 'user_id'
    )]
    public ?EntityCollection $users = null;

    public function getId(): ?int
    {
        return $this->id !== null ? (int) $this->id : null;
    }

    public function getUsers(): EntityCollection
    {
        return $this->load('users');
    }

    /**
     * @param EntityCollection<User>|null $users
     */
    public function setUsers(?EntityCollection $users): void
    {
        $this->associate('users', $users);
    }
}
```

**ManyToManyリレーションの読み込み:**

```php
// 単一エンティティのリレーション読み込み
$user = $userRepo->findById(1);
$user->load('roles');

// バッチローディング
$users = $studentRepo->sqlQuery()
    ->whereIn('student_id', [1, 2, 3])
    ->getResult();
$users->load('roles');
```

**ManyToManyリレーションの同期:**

リポジトリに`ManyToManyTrait`を使用することで、`syncManyToMany()`メソッドが利用できます。

```php
use WScore\DecaORM\Trait\ManyToManyTrait;

class UserRepository extends AbstractRepository
{
    use ManyToManyTrait;
    // ...
}

// エンティティのリレーションプロパティに設定してから同期
$user->getRoles()->add($role1);
$user->getRoles()->add($role2);
$user->getRoles()->delEntity($role3);

$userRepo->syncManyToMany($user, 'roles');
```

`syncManyToMany()`は、エンティティのリレーションプロパティに設定されたエンティティの状態をデータベースに反映します。現在のDBの状態と比較し、必要なINSERT/DELETEを自動的に実行します。

### 多態（Morph）リレーション

1つの子テーブル行が、**複数種類の親エンティティ**（例: 投稿と動画）のどちらかを指す場合に使います。Rails の polymorphic / Laravel の morph に相当します。

- **子側**: `#[MorphTo]`（多対1）または `#[MorphToOne]`（多対1で親側が HasOne のとき）。  
  - `foreignKey` … 親の ID を持つプロパティ名  
  - `typeColumn` … **判別子**（どの親クラスか）を保存するプロパティ名  
  - `typeMap` … **DB に保存する文字列** ⇒ **エンティティクラス** の対応表（1 クラス 1 エントリ）  
  - `inversedBy` … 親エンティティ上の `#[HasMany]` / `#[HasOne]` のプロパティ名（任意・双方向のメモリ整合用）
- **親側**: 従来どおり `#[HasMany]` または `#[HasOne]` を使い、`mappedBy` に子の MorphTo / MorphToOne の**プロパティ名**を指定します。追加の専用アトリビュートは不要です。

```php
// 子: Comment が post または video を指す
#[MorphTo(
    foreignKey: 'commentable_id',
    typeColumn: 'commentable_type',
    typeMap: [
        'post' => MorphPost::class,
        'video' => MorphVideo::class,
    ],
    inversedBy: 'comments',
)]
private MorphPost|MorphVideo|null $commentable = null;

// 親: Post
#[HasMany(targetEntity: MorphComment::class, mappedBy: 'commentable')]
private ?EntityCollection $comments = null;
```

**読み込みの挙動**

- 親から `load('comments')` すると、子リポジトリ側で `mappedBy` のメタデータを見て、`type` + `fk` で絞り込んだ SELECT が実行されます（`MappedByQuery`）。
- 子から親へ `load('commentable')` すると、判別子と ID から対象リポジトリを選び **親を 1 件ずつ解決**します。親のクラスが混在しうるため、戻り値は **`EntityCollection` ではなく `Collection`** です（`EntityCollection` は同一エンティティクラス前提のため）。
- 親を INSERT する際、子がメモリ上で紐づいていれば、`MorphTo` / `MorphToOne` も `BelongsTo` と同様に **INSERT 後に FK・型・プロパティが埋まる**ようになっています。

**リポジトリ API**

多態用の特別なメソッドは `RepositoryInterface` にはありません。逆参照のクエリ組み立ては Relation 層の `MappedByQuery` が、公開 API（`getRelation` / `find` / `sqlQuery` など）だけで行います。

## エンティティの保存と依存性の管理

DecaORMには**Unit of Work (UoW)**が実装されていません。そのため、エンティティを保存する際は、**依存性を考慮して適切な順番で保存する必要があります**。

### 複数のエンティティを保存する場合

重要なポイント：**エンティティは先に作成して関連付けても問題ありません**。保存の順番だけ注意してください。

エンティティ間の関連づけは、setter 内で `associate()` を呼ぶ形で行うと、FK や逆参照が正しく更新されます。

```php
// 1. エンティティを作成
$user = new User();
$user->name = 'John Doe';

$post1 = new Post();
$post1->title = 'Post 1';

$post2 = new Post();
$post2->title = 'Post 2';

// 2. setter で関連づけ（双方向の整合が取れる）
$user->setPosts(new EntityCollection([$post1, $post2], $postRepo));
// または 子側から: $post1->setUser($user); $post2->setUser($user);

// 3. 親エンティティを保存（IDが確定し、子エンティティのforeignKeyが自動設定される）
$userRepo->save($user);
// この時点で、$post1->user_id と $post2->user_id が自動的に設定される

// 4. 子エンティティを保存
$postRepo->save($post1);
$postRepo->save($post2);
```

### 自動的な外部キー設定の仕組み

親エンティティ（User）を保存すると、DecaORMは以下の処理を自動的に行います：

1. **親エンティティのIDを確定**（INSERT実行後）
2. **HasMany/HasOneのリレーションを走査**
3. **関連する子エンティティ（Post）のBelongsTo/BelongsToOneのforeignKeyを自動設定**

そのため、子エンティティの`user_id`を手動で設定する必要はありません。エンティティ間の関連付け（`$post->user = $user`）だけで十分です。

### トランザクション管理

複数のエンティティを保存する場合は、トランザクションを使用してデータの整合性を保つことを推奨します。

```php
use WScore\DecaORM\OrmManager;

OrmManager::transaction(function() use ($userRepo, $postRepo) {
    // エンティティを作成
    $user = new User();
    $user->setName('John Doe');  // または setRaw('name', 'John Doe')

    $post = new Post();
    $post->setTitle('My Post');  // または setRaw('title', 'My Post')

    // 親エンティティ側から子エンティティを関連付け（setter で associate を使用）
    $user->setPosts(new EntityCollection([$post], $postRepo));

    // 親を先に保存（IDが確定し、子のforeignKeyが自動設定される）
    $userRepo->save($user);

    // 子を保存
    $postRepo->save($post);

});
```

### デフォルトコンテナの設定（アプリ起動時に1回）

```php
use WScore\DecaORM\OrmManager;
$manager = OrmManager::initialize($container);
```

### スコープ実行（テナントごとのコンテナ切り替え）

Webアプリで「1リクエスト = 1テナント」のように扱う場合は、ミドルウェア（またはフロントコントローラ）で、
テナント確定後に `runWithContainer()` で処理全体を包むのが安全です。

```php
use WScore\DecaORM\OrmManager;
// tenantContainer は tenantId に対応するPDO/Repository群を持つコンテナ
return $manager->runWithContainer($tenantContainer, 
    function () use ($handler, $request) { // PSR-15想定なら: 
        return $handler->handle(request);
    });
```


## 制限事項と注意点

1. **保存順序の管理**: Unit of Workがないため、エンティティを保存する際は依存性を考慮して適切な順番で保存してください。親エンティティを先に保存し、IDを確定させてから子エンティティを保存します。

2. **トランザクション管理**: 複数のエンティティを保存する場合は、トランザクションを使用してデータの整合性を保つことを推奨します。

3. **リレーションの読み込み**: リレーションデータは自動的には読み込まれません。必要に応じて `load()` メソッドを明示的に呼び出してください。

4. **外部キー制約**: データベースの外部キー制約を適切に設定することで、データの整合性を保つことができます。

5. **エンティティの状態管理**: エンティティの新規作成と更新は、IDの有無で自動判定されます。手動で `insertEntity()` や `updateEntity()` を呼び出すことも可能です。
