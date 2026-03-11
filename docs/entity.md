# エンティティの作成方法

`DecaORM`におけるエンティティは、データベースのテーブル行を表現するPHPクラスです。
PHP 8のアトリビュートを使用して、テーブル名やカラムとのマッピングを定義します。

## 基本原則

*   **プロパティの型**: すべてのプロパティは **string** である必要があります（ORM内部での値の受け渡しや変換の整合性を保つため）。
*   **必須インターフェース**: `WScore\DecaORM\Contracts\EntityInterface` を実装する必要があります。
*   **トレイトの利用**: 通常、`WScore\DecaORM\Trait\EntityTrait` を `use` することで、必要なメソッドの実装を簡略化します。

## アトリビュート一覧

### クラスレベル・アトリビュート

| アトリビュート | 必須 | 説明 |
| :--- | :---: | :--- |
| `#[Table(name: '...')]` | **Yes** | マッピング先のテーブル名を指定します。 |
| `#[Repository(ClassName::class)]` | **Yes** | このエンティティを扱うデフォルトのリポジトリクラスを指定します。 |

### プロパティレベル・アトリビュート

| アトリビュート | 必須 | 説明 |
| :--- | :---: | :--- |
| `#[Id]` | **Yes** | 主キー（Primary Key）となるプロパティに指定します。 |
| `#[Column(name: '...')]` | No | テーブルのカラム名を指定します。省略時はプロパティ名が使用されます。 |
| `#[GeneratedValue]` | No | IDがデータベース側で自動採番（AUTO_INCREMENT等）される場合に指定します。 |
| `#[CreatedAt]` | No | 作成日時を自動設定するカラムに指定します。 |
| `#[UpdatedAt]` | No | 更新日時を自動更新するカラムに指定します。 |

---

## リレーション・アトリビュート

DecaORMでは、アトリビュートを使用してエンティティ間のリレーションを定義します。
リレーションシップを定義するためのアトリビュートです。

*   `#[HasOne(targetEntity: ..., mappedBy: ...)]`
*   `#[HasMany(targetEntity: ..., mappedBy: ...)]`
*   `#[BelongsTo(targetEntity: ..., foreignKey: ..., inversedBy: ...)]`
*   `#[ManyToMany(targetEntity: ..., joinTable: ..., foreignKey: ..., inverseForeignKey: ...)]`

### HasMany と BelongsTo (1対多)

最も一般的なリレーションです。例えば「一人のユーザーが複数の投稿を持つ」場合に使用します。

*   **HasMany**: 親から子への参照（1対多）。
    *   `targetEntity`: 関連先のクラス名。
    *   `mappedBy`: 関連先（子）で自身を指しているプロパティ名。
*   **BelongsTo**: 子から親への参照（多対1）。
    *   `targetEntity`: 関連先のクラス名。
    *   `foreignKey`: データベース上の外部キーカラム名。
    *   `inversedBy`: 関連先（親）で自身を指しているプロパティ名。

```php
// User.php (親)
#[HasMany(targetEntity: Post::class, mappedBy: 'user')]
private ?array $posts = null;

// Post.php (子)
#[Column(name: 'user_id')]
private string $user_id = '';

#[BelongsTo(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'posts')]
private ?User $user = null;
```

### HasMany / BelongsTo 用メソッドの書き方

双方向の参照を自分で更新する場合、`User::addPost()` と `Post::setUser()` が互いに呼び合って
永久ループにならないようにガードを入れる必要があります。以下はフラグなしで安全に動作する実装例です。

```php
// User.php
class User implements EntityInterface
{
    use EntityTrait;

    #[HasMany(targetEntity: Post::class, mappedBy: 'user')]
    private ?array $posts = null;

    /**
     * @return Post[]
     */
    public function getPosts(): array
    {
        return $this->posts ?? [];
    }

    /**
     * @param Post[] $posts
     */
    public function setPosts(array $posts): static
    {
        $this->posts = $posts;
        foreach ($posts as $post) {
            if ($post->getUser() !== $this) {
                $post->setUser($this);
            }
        }
        return $this;
    }

    public function addPost(Post $post): void
    {
        $this->posts ??= [];
        if (in_array($post, $this->posts, true)) {
            return;
        }
        $this->posts[] = $post;

        // 逆側の参照も更新
        $post->setUser($this);
    }

    public function removePost(Post $post): void
    {
        if ($this->posts === null) {
            return;
        }
        $index = array_search($post, $this->posts, true);
        if ($index === false) {
            return;
        }
        array_splice($this->posts, $index, 1);

        if ($post->getUser() === $this) {
            $post->setUser(null);
        }
    }
}
```

```php
// Post.php
class Post implements EntityInterface
{
    use EntityTrait;

    #[Column(name: 'user_id')]
    private string $user_id = '';

    #[BelongsTo(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'posts')]
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        if ($this->user === $user) {
            return;
        }

        $old = $this->user;
        $this->user = $user;

        // user_id を同期
        $this->user_id = $user?->id ?? '';

        // 片方だけの変更で済むように、相互更新は1回で止める
        if ($old !== null) {
            $old->removePost($this);
        }
        if ($user !== null) {
            $user->addPost($this);
        }
    }
}
```

ポイント:

- `addPost()` は先に配列に追加し、その後 `setUser()` を呼ぶ
- `setUser()` は同一参照なら何もしないガードでループを止める
- `in_array(..., true)` と `array_search(..., true)` で同一インスタンスの重複を防ぐ

### HasOne と BelongsToOne (1対1)

「一人のユーザーが一つのプロフィールを持つ」ような1対1の関係に使用します。
- 
- **HasOne**: 所有する側（外部キーを持たない側）に記述します。
    - `targetEntity`: 関連先のクラス名。
    - `mappedBy`: 関連先で自身を指しているプロパティ名。

- **BelongsToOne**: 所有される側（外部キーを持つ側）に記述します。
    - `targetEntity`: 関連先のクラス名。
    - `foreignKey`: データベース上の外部キーカラム名。
    - `inversedBy`: 関連先で自身を指しているプロパティ名。

```php
// User.php (主)
#[HasOne(targetEntity: Profile::class, mappedBy: 'user')]
private ?Profile $profile = null;

// Profile.php (従)
#[Column(name: 'user_id')]
private string $user_id = '';

#[BelongsToOne(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'profile')]
private ?User $user = null;
```




---

## サンプルコード

以下は、プライベートプロパティを使用した標準的なエンティティの構成例です。

```php
<?php

namespace App\Entity;

use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;
use App\Repository\UserRepository;

#[Table(name: 'users')]
#[Repository(UserRepository::class)]
class User implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'user_id')]
    private string $id = '';

    #[Column(name: 'user_name')]
    private string $name = '';

    #[Column(name: 'email_address')]
    private string $email = '';

    /**
     * 注意: DecaORMのエンティティプロパティは、
     * データベースとの一貫性のために string 型として定義します。
     */
}
```

## エンティティの操作

プロパティが `private` であっても、`EntityTrait` によって以下のように操作可能です。

```php
$user = new User();

// 値の設定 (setメソッドまたはマジックメソッド)
$user->set('name', 'テスト太郎');
// または
$user->name = 'テスト太郎'; 

// 値の取得 (getメソッドまたはマジックメソッド)
echo $user->get('name');
// または
echo $user->name;
```
