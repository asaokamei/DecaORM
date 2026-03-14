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

### Lazy Loading（遅延読み込み）

リレーションは自動では読み込まれません。ゲッター内で `load($relationName)` を呼ぶと、そのプロパティへ初回アクセスしたときだけ DB から取得し、以降はキャッシュされた値が返ります。`EntityTrait` が `EntityActionsTrait` を利用しているため、`load()` はそのまま利用できます。

```php
// User.php
public function getPosts(): EntityCollection
{
    return $this->load('posts');
}

public function getProfile(): ?Profile
{
    $this->load('profile');
    return $this->profile;
}
```

### associate() による関連づけ

リレーションを設定するときは、公開 API の `associate($relationName, $targetOrTargets)` を使います。FK の更新や逆参照の整合はトレイト側で行われるため、自前で双方向の更新やループ防止を書く必要はありません。

- **BelongsTo / BelongsToOne / HasOne**: 第2引数は単一エンティティまたは `null`
- **HasMany / ManyToMany**: 第2引数は `EntityCollection` または `iterable` または `null`

setter から呼び出す例です。

```php
// Post.php（BelongsTo）
public function setUser(?User $user): void
{
    $this->associate('user', $user);
}

// User.php（HasMany）
public function setPosts(?EntityCollection $posts): void
{
    if ($posts === null) {
        $this->posts = null;
        return;
    }
    $this->associate('posts', $posts);
}

// User.php（ManyToMany）
public function setRoles(?EntityCollection $roles): void
{
    if ($roles === null) {
        $this->roles = null;
        return;
    }
    $this->associate('roles', $roles);
}
```

直接 `associate()` を呼ぶこともできます。

```php
$post->associate('user', $user);
$user->associate('posts', $postCollection);
```

**補足**: `associate()` はエンティティのメモリ上での関連づけのみです。ManyToMany の中間テーブルへ反映するには、リポジトリの `syncManyToMany($entity, $relationName)` を別途呼んでください。

### addHasMany / removeHasMany

HasMany のコレクションに1件だけ追加・削除する場合は、`addHasMany($relationName, $child)` と `removeHasMany($relationName, $child)` が使えます。こちらもトレイトで提供され、FK と逆参照が更新されます。

```php
// User.php
public function addPost(Post $post): void
{
    $this->addHasMany('posts', $post);
}

public function removePost(Post $post): void
{
    $this->removeHasMany('posts', $post);
}
```

### HasOne と BelongsToOne (1対1)

「一人のユーザーが一つのプロフィールを持つ」ような1対1の関係に使用します。

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
