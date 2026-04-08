# DecaORM

DecaORM is a simple, lightweight **data-mapper ORM** for PHP 8. It uses PHP 8 **attributes** to map entity classes to database tables and provides flexible data access via the **repository pattern**.

### Support

PHP: 8.1, 8.2, 8.3, 8.4  
Databases: SQLite, MySQL, PostgreSQL

---

## Features

- **Attribute mapping** — Define mapping with attributes on the entity: `#[Table]`, `#[Column]`, `#[Id]`, etc.
- **Repository pattern** — Data access logic lives in repositories for clearer, maintainable code.
- **Relations** — One-to-one, one-to-many, many-to-many, and **polymorphic** (`#[MorphTo]`, `#[MorphToOne]`) via `#[HasOne]`, `#[HasMany]`, `#[BelongsTo]`, `#[BelongsToOne]`, `#[ManyToMany]`.
- **Lazy loading** — Call `load()` inside a getter so the relation is loaded on first access.
- **Batch loading** — Load relations for many entities in one query to avoid N+1.
- **Identity map** — Ensures a single in-memory instance per primary key.
- **Dirty tracking** — Only changed fields are updated, reducing unnecessary UPDATEs.
- **Lifecycle** — `#[CreatedAt]` and `#[UpdatedAt]` for automatic timestamps.
- **Hydrator** — Default `AttributeHydrator` plus support for custom hydrators.
- **Explicit design** — Behavior is predictable from reading the code.
- **Repository hooks** — Optional `RepositoryHooksInterface` for cross-cutting rules (tenant scope, soft delete, optimistic locking); see [repository-hooks-en.md](docs/repository-hooks-en.md).

### Not supported

- **Unit of Work (UoW)** — No automatic save ordering or deferred flush. You must save in dependency order (e.g. parent before children).
- **Cascade delete** — Deleting a parent does not delete related children; delete them explicitly.
- **Eager loading** — Relations are not loaded automatically. Use `load()` (or lazy loading in getters) when needed.


### License

MIT License


### Installation

Install with Composer:

```bash
composer require wscore/decaorm
```

### Documentation

- [Entity mapping (entity-en.md)](docs/entity-en.md)
- [SQL builder (sql-en.md)](docs/sql-en.md)
- [Repository hooks (repository-hooks-en.md)](docs/repository-hooks-en.md)

**Japanese:** [README.md](README-ja.md) | [Entity mapping](docs/entity-ja.md) | [SQL builders](docs/sql-ja.md) | [Repository hooks](docs/repository-hooks-ja.md)

---

## Quick start

### 1. Define an entity

Use attributes from `WScore\DecaORM\Attribute` and implement `EntityInterface` with `EntityTrait`.

```php
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;

#[Table(name: 'users')]
#[Repository(UserRepository::class)]
class User implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'user_id')]
    private ?int $id = null;

    #[Column(name: 'name')]
    private string $name = '';

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

See [entity-en.md](docs/entity-en.md) for property types and attributes.

### 2. Implement a repository

Extend `AbstractRepository` for your entity.

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

### 3. Basic CRUD

```php
$pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
$manager = OrmManager::initialize($container);
$userRepo = new UserRepository($manager);

// Create
$user = new User();
$user->fill(['name' => 'Deca Taro']);
$user->save();  // INSERT; ID is auto-generated
echo $user->getId();

// Read
$user = $userRepo->findById(1);
if ($user) {
    echo $user->getName();
}

// Update
$user->setName('Deca Jiro');  // or setRaw('name', 'Deca Jiro')
$user->save();  // UPDATE (ID present)

// Delete
$user->delete();
```

---

## Relations

Relations are **not** loaded automatically. Call `load()` explicitly or use lazy loading in getters.

### Parent entity (e.g. User)

```php
class User implements EntityInterface
{
    // One-to-many: targetEntity = related class, mappedBy = property name on the other side
    #[HasMany(targetEntity: Post::class, mappedBy: 'user')]
    private ?array $posts = null;

    public function getPosts(): EntityCollection
    {
        return $this->load('posts');
    }

    /**
     * @param EntityCollection<Post>|null $posts
     */
    public function setPosts(?EntityCollection $posts): void
    {
        $this->associate('posts', $posts);
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

### Child entity (e.g. Post)

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
    private ?int $user_id = null;  // FK column for User

    #[Column(name: 'title')]
    private string $title = '';

    // Many-to-one: foreignKey = FK property, inversedBy = property on the parent
    #[BelongsTo(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'posts')]
    private ?User $user = null;
}
```

### Lazy loading

Calling `load($relationName)` in a getter loads the relation on first access and returns the cached value afterward.

```php
$user = $userRepo->findById(1);
$posts = $user->load('posts');  // SELECT runs here
$posts = $user->load('posts');  // Returns cached value
```

### Associating relations with `associate()`

Use the public API `associate($relationName, $targetOrTargets)` to set relations. DecaORM updates FKs and inverse references accordingly.

- **BelongsTo / BelongsToOne / HasOne**: pass a single entity or `null`.
- **MorphTo / MorphToOne**: pass a single entity or `null`.
- **HasMany / ManyToMany**: pass an `EntityCollection` or iterable, or `null`.

**Note:** `associate()` only updates in-memory links. For ManyToMany, call the repository’s `syncManyToMany($entity, $relationName)` to persist the join table.

```php
$post->associate('user', $user);
$user->associate('roles', $roleCollection);
```

### Batch loading (avoiding N+1)

Load a relation for many entities in one query.

```php
$users = $userRepo->sqlQuery()
    ->whereIn('user_id', [1, 2, 3, 4, 5])
    ->getResult();

$posts = $users->load('posts');  // One query for all users' posts

foreach ($users as $user) {
    foreach ($user->getPosts() as $post) {
        echo $post->getRaw('title');
    }
}
```

### EntityCollection

Use the collection for filtering, batch loading, and saving.

```php
$users = $userRepo->sqlQuery()->...->getResult();
$posts = $users->load('posts');
$comments = $posts->load('comments');
$posts->save();
$comments->save();
```

### Many-to-many

Many-to-many uses a join table. Specify the **table and column names** in the `#[ManyToMany]` attribute (no separate entity/repository for the join table).

```php
class User implements EntityInterface
{
    /** @var EntityCollection<Role>|null */
    #[ManyToMany(
        targetEntity: Role::class,
        joinTable: 'user_role',
        foreignKey: 'user_id',
        inverseForeignKey: 'role_id'
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
```

**Loading:** use `$user->load('roles')` or batch load: `$users->load('roles')`.

**Syncing:** use `ManyToManyTrait` in the repository and call `syncManyToMany()` after changing the relation on the entity. It will INSERT/DELETE rows in the join table as needed.

```php
use WScore\DecaORM\Trait\ManyToManyTrait;

class UserRepository extends AbstractRepository
{
    use ManyToManyTrait;
}

$user->getRoles()->add($role1);
$user->getRoles()->add($role2);
$user->getRoles()->delEntity($role3);
$userRepo->syncManyToMany($user, 'roles');
```

### Polymorphic (Morph) relations

When a child row can point to **more than one parent type** (e.g. a comment on either a post or a video), use:

- **Child:** `#[MorphTo]` (many-to-one) or `#[MorphToOne]` (one-to-one on the FK side) with `foreignKey`, `typeColumn` (discriminator stored in the DB), and `typeMap` (discriminator string → entity class). Optional `inversedBy` matches the parent’s `#[HasMany]` / `#[HasOne]` property name.
- **Parent:** unchanged `#[HasMany]` / `#[HasOne]` with `mappedBy` set to the child’s morph property name.

Loading the morph parent from the child returns a generic **`Collection`**, not `EntityCollection`, because parent instances may belong to different classes. Parents are resolved **per child row**. There is no extra method on `RepositoryInterface`; inverse queries are built in `MappedByQuery` using the usual repository API.

See **README-ja.md** (Japanese) for a longer explanation and examples.

---

## Saving and dependency order

DecaORM has **no Unit of Work**. You must **save in dependency order** (e.g. parent before children).

- Create entities and associate them in memory in any order.
- Save **parent first** so its ID is set; then save children (FKs are set automatically for BelongsTo/HasMany).
- Use **transactions** when saving multiple entities.

Example with transaction:

```php
OrmManager::transaction(function () use ($userRepo, $postRepo) {
    $user = new User();
    $user->setName('John Doe');

    $post = new Post();
    $post->setTitle('My Post');

    $user->setPosts(new EntityCollection([$post], $postRepo));

    $userRepo->save($user);   // Parent first
    $postRepo->save($post);   // Then children
});
```

### Default container (once at app bootstrap)

```php
use WScore\DecaORM\OrmManager;
$manager = OrmManager::initialize($container);
```

### Per-request container (e.g. multi-tenant)

`OrmManager` resolves `PDO::class` and repository entries from the single container passed to `initialize()`. After you know the tenant, build a container that already holds that tenant’s `PDO` (and repositories), then call `OrmManager::initialize($container)` for that request—or inject an `OrmManager` constructed for that container. For more than one connection in the same process, use separate `OrmManager` instances (each has its own identity map and dirty tracker); nested container scopes are not supported.

---

## Summary of limitations

1. **Save order** — No UoW; save parents before children.
2. **Transactions** — Use them when saving multiple entities.
3. **Relations** — Never auto-loaded; call `load()` when needed.
4. **Foreign keys** — Use DB constraints for integrity.
5. **New vs update** — Determined by presence of ID; you can also call `insertEntity()` or `updateEntity()` explicitly.
