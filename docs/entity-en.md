# Entity mapping

In DecaORM, an **entity** is a PHP class that represents a row in a database table. Mapping is defined with PHP 8 **attributes** for table name and columns.

---

## Basic rules

- **Property types** — All mapped properties must be **string** (for consistent handling and conversion inside the ORM).
- **Interface** — Implement `WScore\DecaORM\Contracts\EntityInterface`.
- **Trait** — Use `WScore\DecaORM\Trait\EntityTrait` to get the default implementation.

---

## Attribute reference

### Class-level attributes

| Attribute | Required | Description |
| :--- | :---: | :--- |
| `#[Table(name: '...')]` | **Yes** | Database table name. |
| `#[Repository(ClassName::class)]` | **Yes** | Default repository class for this entity. |

### Property-level attributes

| Attribute | Required | Description |
| :--- | :---: | :--- |
| `#[Id]` | **Yes** | Marks the property as the primary key. |
| `#[Column(name: '...')]` | No | Column name. Omit if it matches the property name. |
| `#[GeneratedValue]` | No | Use when the ID is auto-generated (e.g. AUTO_INCREMENT). |
| `#[CreatedAt]` | No | Column that gets the creation timestamp. |
| `#[UpdatedAt]` | No | Column that gets the last-update timestamp. |

---

## Relation attributes

Relations between entities are defined with these attributes:

- `#[HasOne(targetEntity: ..., mappedBy: ...)]`
- `#[HasMany(targetEntity: ..., mappedBy: ...)]`
- `#[BelongsTo(targetEntity: ..., foreignKey: ..., inversedBy: ...)]`
- `#[ManyToMany(targetEntity: ..., joinTable: ..., foreignKey: ..., inverseForeignKey: ...)]`
- `#[MorphTo(foreignKey: ..., typeColumn: ..., typeMap: [...], inversedBy: ...)]` — polymorphic many-to-one (child holds FK + type discriminator).
- `#[MorphToOne(foreignKey: ..., typeColumn: ..., typeMap: [...], inversedBy: ...)]` — polymorphic one-to-one on the FK side.

### HasMany and BelongsTo (one-to-many)

Typical “one parent, many children” relation (e.g. one User, many Posts).

- **HasMany** (on the parent):
  - `targetEntity`: Related class.
  - `mappedBy`: Name of the property on the child that points back to this entity.
- **BelongsTo** (on the child):
  - `targetEntity`: Related class.
  - `foreignKey`: Name of the FK property (database column).
  - `inversedBy`: Name of the property on the parent that holds the collection.

You need both the FK property and the relation property on the child:

```php
// User.php (parent)
#[HasMany(targetEntity: Post::class, mappedBy: 'user')]
private ?array $posts = null;
```

```php
// Post.php (child)
#[Column(name: 'user_id')]
private string $user_id = '';

#[BelongsTo(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'posts')]
private ?User $user = null;
```

### Lazy loading

Relations are not loaded automatically. Call `load($relationName)` in a getter so the relation is loaded on first access and cached afterward.

```php
// User.php
public function getPosts(): EntityCollection
{
    return $this->load('posts');
}

public function getProfile(): ?Profile
{
    return $this->load('profile');
}
```

You can also call `load()` directly:

```php
$user->load('posts');
$user->load('profile');
```

### Setting relations with `associate()`

Use the public API `associate($relationName, $targetOrTargets)` so that FKs and inverse references stay in sync.

- **BelongsTo / BelongsToOne / HasOne**: pass a single entity or `null`.
- **MorphTo / MorphToOne**: pass a single entity or `null` (FK, type column, and relation property are updated).
- **HasMany / ManyToMany**: pass `EntityCollection`, iterable, or `null`.

Example in setters:

```php
// Post.php (BelongsTo)
public function setUser(?User $user): void
{
    $this->associate('user', $user);
}

// User.php (HasMany)
public function setPosts(?EntityCollection $posts): void
{
    $this->associate('posts', $posts);
}

// User.php (ManyToMany)
public function setRoles(?EntityCollection $roles): void
{
    $this->associate('roles', $roles);
}
```

Or call `associate()` directly:

```php
$post->associate('user', $user);
$user->associate('posts', $postCollection);
```

**Note:** `associate()` only updates in-memory links. To persist ManyToMany, call the repository’s `syncManyToMany($entity, $relationName)`.

### addHasMany / removeHasMany

To add or remove a single child in a HasMany collection, use `addHasMany($relationName, $child)` and `removeHasMany($relationName, $child)`. The trait updates the FK and inverse reference.

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

### HasOne and BelongsToOne (one-to-one)

Use for “one user has one profile” style relations.

- **HasOne**: On the side that does **not** hold the FK (e.g. User).
  - `targetEntity`: Related class.
  - `mappedBy`: Property on the other side that points to this entity.
- **BelongsToOne**: On the side that **holds** the FK (e.g. Profile).
  - `targetEntity`: Related class.
  - `foreignKey`: FK column name.
  - `inversedBy`: Property on the other side.

```php
// User.php (owner)
#[HasOne(targetEntity: Profile::class, mappedBy: 'user')]
private ?Profile $profile = null;

// Profile.php (owned)
#[Column(name: 'user_id')]
private string $user_id = '';

#[BelongsToOne(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'profile')]
private ?User $user = null;
```

### MorphTo and MorphToOne (polymorphic)

Use when one child can reference **more than one parent type** (e.g. a comment on a post or a video). On the parent, use normal `#[HasMany]` / `#[HasOne]` with `mappedBy` pointing at the child’s morph property.

- **MorphTo**: `foreignKey`, `typeColumn`, `typeMap` (DB string → entity class), optional `inversedBy`.
- **MorphToOne**: same shape; pairs with `#[HasOne]` on the parent when appropriate.

Loading the parent from the child returns a **`Collection`**, not `EntityCollection`, because parent classes may differ. See **README.md** for details and **README-ja.md** for longer examples.

---

## Working with entities

### EntityCollection

Methods that return multiple entities usually return an `EntityCollection`. Use it for batch loading, filtering, and saving.

```php
$users = $userRepo->find('active', 'status');  // find(value, column name)

$users->add($newUser);
$posts = $users->load('posts');   // Batch load to avoid N+1
$posts->load('comments');         // Load nested relations

$user1 = $users->findById(1);
if ($users->hasEntity($deleteUser)) {
    $users->delEntity($deleteUser);
}
$names = $users->getValues('name');
$users->sort('birthday');
$users->sort(fn($a, $b) => $a->status <=> $b->status);
$userByGroup = $users->groupBy('status'); // array of status => EntityCollection
```

### EntityHandler

For more complex operations (loading nested relations, replicating, bulk save), use the entity’s handler:

```php
$userHandler = $user->getHandler();
$userHandler->load('posts.comments');
$newUserHandler = $userHandler->replicate();  // Clone entity and HasMany/HasOne relations

$newUserHandler->save();  // Save entity and those relations
```

#### replicate()

Copies the entity and its HasMany and HasOne related entities only.

#### save() (on handler)

Saves the entity and its HasMany, HasOne, and ManyToMany **links**. ManyToMany only updates the join table; related entities are not saved.
