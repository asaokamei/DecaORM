DecaORM
=======

シンプルで軽量なPHP製ORMライブラリです。
PHP 8のアトリビュート（Attribute）を活用したデータマッピングと、リポジトリパターンによるデータアクセスを提供します。

## 特徴

*   **Attribute Mapping**: Doctrineスタイルのアトリビュートを使用して、エンティティとデータベーステーブルをマッピングします。
*   **Hydrator**: 配列データとオブジェクト間の相互変換をサポートします。
*   **Repository**: データアクセスロジックを分離するためのリポジトリ基盤を提供します。

## インストール

Packagist未登録のため、簡単にはインストールできません！

```shell
composer require wscore/deca-orm
```

## 使い方

### 1. エンティティの定義

`WScore\DecaORM\Attribute` 名前空間のアトリビュートを使用して、エンティティクラスにメタデータを定義します。
`EntityInterface` を実装し、`EntityTrait` を使用することで基本的な機能を利用できます。

```php
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\GeneratedValue;
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
    private ?int $user_id = null;

    #[Column(name: 'name')]
    private string $name;
}
```


### 3. リポジトリの利用

データアクセスを行うためのリポジトリクラスを作成する場合、`AbstractRepository` を継承するか、`RepositoryTrait` を使用して実装します。

```php
use DateTimeImmutable;
use PDO;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\RepositoryTrait;
use WScore\DecaORM\AbstractRepository;

class UserRepository extends AbstractRepository
{
    public function __construct(PDO $pdo, HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = $hydrator ?? new AttributeHydrator(User::class);
        $this->now = new DateTimeImmutable();
    }
}

```
### 2. Hydratorの使用 (自作Hydrator)

Attributeを利用する場合は`AttributeHydrator`を利用できます。

ほんの少しでも動作を早くしたい場合は、`Hydrator`を自作してください。

例：

```php
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\HydratorTrait;

class UserHydrator implements HydratorInterface
{
    use HydratorTrait;

    public function isPkAutoNumber(): bool
    {
        return true;
    }

    public function getEntityClass(): string
    {
        return User::class;
    }
    # implement all other methods.
}

class UserRepository extends AbstractRepository
{
    use RepositoryTrait;

    public function __construct(PDO $pdo, HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = new UserHydrator()
        $this->now = new DateTimeImmutable();
    }
}
```
