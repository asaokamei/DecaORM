# DecaORM

DecaORMは、PHP 8のアトリビュート（Attribute）を活用した、シンプルで軽量なデータマッパー型ORMライブラリです。
エンティティとデータベーステーブルのマッピングを直感的に定義でき、リポジトリパターンによる柔軟なデータアクセスを提供します。

## 特徴

*   **Attribute Mapping**: PHP 8のアトリビュート（`#[Table]`, `#[Column]`, `#[Id]`など）を使用して、マッピング情報をエンティティクラスに直接記述できます。
*   **Repository Pattern**: データアクセスロジックをリポジトリに分離し、保守性の高いコードを実現します。
*   **Relations**: `#[HasOne]`, `#[HasMany]`, `#[BelongsTo]` アトリビュートによるリレーションシップ（1対1、1対多）をサポートしています。
*   **Lifecycle Callbacks**: `#[CreatedAt]`, `#[UpdatedAt]` によるタイムスタンプの自動更新に対応しています。
*   **Flexible Hydrator**: 標準の `AttributeHydrator` に加え、パフォーマンスを重視したカスタムHydratorの実装も可能です。

### ライセンス

MIT License

### インストール

現在 Packagist には登録されていないため、リポジトリから直接利用するか、ローカルリポジトリとして設定してください。

```bash
composer require wscore/deca-orm
```

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
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\EntityTrait;

#[Table(name: 'users')]
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
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\EntityTrait;

#[Table(name: 'posts')]
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
        // 親クラスの protected メソッド fill() を呼び出す
        $this->fill($user, 'posts');
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
リポジトリに実装したメソッドを通じてロードします。

```php
$user = $userRepo->findById(1);

// postsプロパティは初期状態では null
var_dump($user->posts); // null

// 関連データをロード
$userRepo->loadPosts($user);

// データが格納される
foreach ($user->posts as $post) {
    echo $post->title;
}
```
