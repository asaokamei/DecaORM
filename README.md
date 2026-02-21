# DecaORM

DecaORMは、PHP 8のアトリビュート（Attribute）を活用した、シンプルで軽量なデータマッパー型ORMライブラリです。
エンティティとデータベーステーブルのマッピングを直感的に定義でき、リポジトリパターンによる柔軟なデータアクセスを提供します。

## 特徴

*   **Attribute Mapping**: PHP 8のアトリビュート（`#[Table]`, `#[Column]`, `#[Id]`など）を使用して、マッピング情報をエンティティクラスに直接記述できます。
*   **Repository Pattern**: データアクセスロジックをリポジトリに分離し、保守性の高いコードを実現します。
*   **Relations**: `#[HasOne]`, `#[HasMany]`, `#[BelongsTo]`, `#[BelongsToOne]`, `#[ManyToMany]` アトリビュートによるリレーションシップ（1対1、1対多、多対多）をサポートしています。
*   **Batch Loading**: N+1問題を解決するためのバッチローディング機能を提供します。
*   **Identity Map**: 同じ主キーを持つエンティティインスタンスが複数存在しないことを保証し、メモリ上の一意性を管理します。
*   **Dirty Tracking**: エンティティの変更を追跡し、変更されたフィールドのみを更新することで、不要なUPDATEクエリを削減します。
*   **Lifecycle Callbacks**: `#[CreatedAt]`, `#[UpdatedAt]` によるタイムスタンプの自動更新に対応しています。
*   **Flexible Hydrator**: 標準の `AttributeHydrator` に加え、パフォーマンスを重視したカスタムHydratorの実装も可能です。
*   **Simple & Explicit**: シンプルで明示的な設計により、コードを見ただけで何が起きるか予測できます。

### サポートされていない機能

次の機能はサポートされていません。

*   **Unit of Work (UoW)**: エンティティの保存順序の自動解決や、変更の遅延書き込み（flush）は実装されていません。依存性を考慮して手動で保存順序を制御する必要があります。
*   **カスケード削除**: 親エンティティを削除した際に、関連する子エンティティを自動的に削除する機能はありません。手動で削除する必要があります。
*   **自動リレーション読み込み**: リレーションデータは自動的には読み込まれません。`load()` メソッドを明示的に呼び出す必要があります。

### ライセンス

MIT License

### インストール

Composerでインストールしてください。

```bash
composer require wscore/decaorm
```

### ドキュメント

* [entity.md](docs/entity.md)
* [sql.md](docs/sql.md)

## 使い方

### 1. エンティティの定義

`WScore\DecaORM\Attribute` 名前空間のアトリビュートを使用して、エンティティクラスを定義します。
`EntityInterface` を実装し、`EntityTrait` を利用することで、基本的なエンティティ機能が提供されます。

#### 親エンティティ (例: User)

```php
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;

#[Table(name: 'users')]
#[Repository(UserRepository::class)]
class User implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'user_id')]
    public ?int $id = null;

    #[Column(name: 'name')]
    public string $name = '';

    // リレーションの定義（1対多）
    // targetEntity: 関連先クラス
    // mappedBy: 関連先でのプロパティ名
    #[HasMany(targetEntity: Post::class, mappedBy: 'user')]
    public ?array $posts = null;
}
```

#### 子エンティティ (例: Post)

```php
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;

#[Table(name: 'posts')]
#[Repository(PostRepository::class)]
class Post implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'post_id')]
    public ?int $id = null;

    #[Column(name: 'user_id')]
    public ?int $user_id = null;

    #[Column(name: 'title')]
    public string $title = '';

    // リレーションの定義（多対1）
    // foreignKey: 外部キーカラム名
    // inversedBy: 関連先（親）でのプロパティ名
    #[BelongsTo(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'posts')]
    public ?User $user = null;
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
    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        // 対象のエンティティクラスを指定してHydratorを初期化
        $this->hydrator = new AttributeHydrator(User::class);
        $this->now = new \DateTimeImmutable();
    }
    
    // リレーションを手動でロードするヘルパーメソッドの例
    public function loadPosts(User $user): void
    {
        // 親クラスの protected メソッド load() を呼び出す
        $this->load($user, 'posts');
    }
}
```

### 3. 基本的な操作 (CRUD)

```php
$pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
$userRepo = new UserRepository($pdo);

// --- 作成 (Create) ---
$user = new User();
$user->name = 'Deca Taro';
$userRepo->save($user); // INSERTが実行され、IDが自動採番されます
echo $user->getId(); 

// --- 取得 (Read) ---
$user = $userRepo->findById(1);
if ($user) {
    echo $user->name;
}

// --- 更新 (Update) ---
$user->name = 'Deca Jiro';
$userRepo->save($user); // IDが存在するためUPDATEが実行されます

// --- 削除 (Delete) ---
$userRepo->delete($user);
```

### 4. リレーションの利用

リレーションデータは自動的には読み込まれません（Lazy Loading的な挙動に近いですが、自動発火はしません）。
`load()` メソッドを使用して明示的にロードします。

#### 単一エンティティのリレーション読み込み

```php
$user = $userRepo->findById(1);

// postsプロパティは初期状態では null
var_dump($user->posts); // null

// 関連データをロード
$userRepo->load($user, 'posts');

// データが格納される
foreach ($user->posts as $post) {
    echo $post->title;
}
```

#### バッチローディング（N+1問題の解決）

複数のエンティティに対して、一度のクエリで関連データを読み込むことができます。

```php
// 複数のユーザーを取得
$users = $userRepo->sqlQuery()
    ->whereIn('user_id', [1, 2, 3, 4, 5])
    ->getResult();

// 一度のクエリで全ユーザーの投稿を読み込む（N+1問題を回避）
$posts = $userRepo->load($users, 'posts');

// $post はEntityCollection
$titles = $posts->map(fn($e) => $e->get('title'));

// 各ユーザーの投稿にアクセス
foreach ($users as $user) {
    foreach ($user->posts as $post) {
        echo $post->title;
    }
}
```

#### Collectionオブジェクトの利用

複雑な条件で複数のエンティティを取得するためにCollectionオブジェクトが用意されている。また、Collectionオブジェクトからは、バッチローディングを簡単に行う機能が用意されている。

```php
// Collectionオブジェクト
$users = $userRepo->sqlQuery()->...->getCollection();
// リレーションを読み込む
$posts = $users->load('posts');
$comments = $posts->load('comments');
// エンティティの保存など
$posts->save();
```

#### ManyToManyリレーションの利用

多対多のリレーションでは、中間テーブルを使用して関連付けを管理します。

```php
// Studentエンティティ
#[Table(name: 'students')]
class Student implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'student_id')]
    public ?int $id = null;

    #[Column(name: 'name')]
    public string $name = '';

    // ManyToManyリレーションの定義
    #[ManyToMany(
        targetEntity: Course::class,
        joinTable: 'student_course',
        foreignKey: 'student_id',
        inverseForeignKey: 'course_id'
    )]
    public ?array $courses = null;
}

// Courseエンティティ
#[Table(name: 'courses')]
class Course implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'course_id')]
    public ?int $id = null;

    #[Column(name: 'name')]
    public string $name = '';

    #[ManyToMany(
        targetEntity: Student::class,
        joinTable: 'student_course',
        foreignKey: 'course_id',
        inverseForeignKey: 'student_id'
    )]
    public ?array $students = null;
}
```

**ManyToManyリレーションの読み込み:**

```php
// 単一エンティティのリレーション読み込み
$student = $studentRepo->findById(1);
$studentRepo->load($student, 'courses');

// バッチローディング
$students = $studentRepo->sqlQuery()
    ->whereIn('student_id', [1, 2, 3])
    ->getResult();
$studentRepo->load($students, 'courses');
```

**ManyToManyリレーションの同期:**

リポジトリに`ManyToManyTrait`を使用することで、`syncManyToMany()`メソッドが利用できます。

```php
use WScore\DecaORM\Trait\ManyToManyTrait;

class StudentRepository extends AbstractRepository
{
    use ManyToManyTrait;
    // ...
}

// エンティティのリレーションプロパティに設定してから同期
$student->set('courses', [$course1, $course2]);
$studentRepo->syncManyToMany($student, 'courses');
```

`syncManyToMany()`は、エンティティのリレーションプロパティに設定されたエンティティの状態をデータベースに反映します。現在のDBの状態と比較し、必要なINSERT/DELETEを自動的に実行します。

### 5. エンティティの保存と依存性の管理

DecaORMには**Unit of Work (UoW)**が実装されていません。そのため、エンティティを保存する際は、**依存性を考慮して適切な順番で保存する必要があります**。

#### 複数のエンティティを保存する場合

重要なポイント：**エンティティは先に作成して関連付けても問題ありません**。保存の順番だけ注意してください。

**注意**: 現状では、親エンティティ側（User）から子エンティティ（Post）への関連付け（`$user->posts = [...]`）が必要です。本来は`setPosts()`や`setUser()`のようなメソッドで双方向の関連付けを行うのが望ましいですが、これは現在のORMの範囲外です。

```php
// 1. エンティティを作成
$user = new User();
$user->name = 'John Doe';

$post1 = new Post();
$post1->title = 'Post 1';
$post1->user = $user;

$post2 = new Post();
$post2->title = 'Post 2';
$post2->user = $user;

// 2. 親エンティティ側から子エンティティを関連付け
// 注意: 現状では親側からの関連付けが必要です
$user->posts = [$post1, $post2];

// 3. 親エンティティを保存（IDが確定し、子エンティティのforeignKeyが自動設定される）
$userRepo->save($user);
// この時点で、$post1->user_id と $post2->user_id が自動的に設定される

// 4. 子エンティティを保存
$postRepo->save($post1);
$postRepo->save($post2);
```

#### 自動的な外部キー設定の仕組み

親エンティティ（User）を保存すると、DecaORMは以下の処理を自動的に行います：

1. **親エンティティのIDを確定**（INSERT実行後）
2. **HasMany/HasOneのリレーションを走査**
3. **関連する子エンティティ（Post）のBelongsTo/BelongsToOneのforeignKeyを自動設定**

そのため、子エンティティの`user_id`を手動で設定する必要はありません。エンティティ間の関連付け（`$post->user = $user`）だけで十分です。

#### トランザクション管理

複数のエンティティを保存する場合は、トランザクションを使用してデータの整合性を保つことを推奨します。

```php
$pdo->beginTransaction();
try {
    // エンティティを作成
    $user = new User();
    $user->name = 'John Doe';

    $post = new Post();
    $post->title = 'My Post';

    // 親エンティティ側から子エンティティを関連付け
    $user->posts = [$post];

    // 親を先に保存（IDが確定し、子のforeignKeyが自動設定される）
    $userRepo->save($user);

    // 子を保存
    $postRepo->save($post);

    $pdo->commit();
} catch (\Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

### デフォルトコンテナの設定（アプリ起動時に1回）

```php
use WScore\DecaORM\RepositoryManager;
RepositoryManager::setContainer($container);
```

### スコープ実行（テナントごとのコンテナ切り替え）

Webアプリで「1リクエスト = 1テナント」のように扱う場合は、ミドルウェア（またはフロントコントローラ）で、
テナント確定後に `runWithContainer()` で処理全体を包むのが安全です。

```php
use WScore\DecaORM\RepositoryManager;
// tenantContainer は tenantId に対応するPDO/Repository群を持つコンテナ
return RepositoryManager::runWithContainer($tenantContainer, 
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
