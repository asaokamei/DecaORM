# Repository hooks

Composable extension points for **cross-cutting persistence rules** (tenant scoping, soft delete, optimistic locking, etc.) **without** deep repository inheritance trees.

## Contract

- **`WScore\DecaORM\Contracts\RepositoryHooksInterface`** defines the hook methods.
- Concrete repositories assign an implementation to **`protected ?RepositoryHooksInterface $hooks = null`**. If unset, **`NoOpHooks`** is used.

## Lifecycle (naming)

| When | Method | Typical use |
|------|--------|-------------|
| After a SELECT `Query` is built, before execution | `beforeQuery(Query $query)` | Tenant filters, “active only” for soft delete, etc. |
| After INSERT column data is prepared, before execution | `beforeInsert(EntityInterface $entity, array &$data)` | Default column values, etc. |
| After INSERT runs | `afterInsert(EntityInterface $entity)` | Optional follow-up |
| After an `Update` is built (PK WHERE set), before execution | `beforeUpdate(Update $update, EntityInterface $entity, array $data, ?array $snapshot)` | Extra `WHERE` for optimistic locking, etc. |
| After UPDATE runs, before the dirty-tracker snapshot is refreshed | `afterUpdate(EntityInterface $entity, ?PDOStatement $updateStatement = null)` | Align in-memory fields; inspect `rowCount()` |
| After a `Delete` is built, before execution | `beforeDelete(Delete $delete, EntityInterface $entity)` | Block physical delete, etc. |
| After DELETE runs | `afterDelete(EntityInterface $entity)` | Optional follow-up |

**`before*`** runs **immediately before** the corresponding SQL is executed; **`after*`** runs **after** execution.

### Why `beforeInsert` looks different

- **SELECT / UPDATE / DELETE** use **builder objects** (`Query`, `Update`, `Delete`) that you augment with clauses.
- **INSERT** is centered on a **column map** (`$data`: column name => value) produced for the statement. Hooks usually **mutate that map**, so **`array &$data` by reference** is the natural fit.
- Passing an `Insert` builder for symmetry is possible, but **the array-centric flow covers most cases**, which is why the signature stays as it is.

### `afterUpdate`’s optional `?PDOStatement`

This is the statement returned from the repository `execute()` call for the UPDATE (or `null` if not available). Use **`PDOStatement::rowCount()`** when you care about affected rows (e.g. stale optimistic lock → 0 rows). Hooks that do not need it may ignore the argument.

### `applyHooksToQuery(Query $query)`

Invoked automatically from **`sqlQuery()`** and **`Query::newQuery()`**. If you construct **`new Query($repository)`** yourself, call **`$repository->applyHooksToQuery($query)`** when you need the same global filters.

## Ordering multiple hooks

**`WScore\DecaORM\Persistence\CompositeHooks`** runs each hook method **in array order**. When combining tenant scoping and soft-delete filters, register hooks in an order that makes sense for your team (AND semantics are usually unchanged, but readability benefits from a fixed order).

## Sample implementations (`src/Persistence/`)

| Class | Role |
|-------|------|
| **`NoOpHooks`** | No-op default; subclass to override only what you need. |
| **`CompositeHooks`** | Compose multiple hooks. |
| **`TenantScopeHooks`** | `beforeQuery`: filter by a scope column (e.g. `tenant_id`). |
| **`SoftDeleteHooks`** | `beforeQuery`: e.g. `deleted_at IS NULL`. Optional guard against physical delete. |
| **`VersionColumnHooks`** | Optimistic locking (`WHERE version = ?` and `version = version + 1`). See below. |

## `VersionColumnHooks` expectations

1. **Map the version field with `#[Column]`** so it appears in the dirty-tracker snapshot; otherwise the expected version is missing.

2. **Do not put the version column in the UPDATE diff `$data`** — the hook bumps the version in SQL. If version appears in the diff, the hook **throws by design** (avoids conflicting SET clauses).

3. **`afterUpdate` bumps the entity’s version in memory** — the database row is already incremented, but the PHP object does not auto-refresh. The next step is **`DirtyTracker::takeEntity()`**, which snapshots the entity; **without aligning the in-memory version**, the next save would use a wrong expected version.

4. **Zero affected rows and `OptimisticLockException`** — if no row matches the version predicate, `rowCount()` may be `0`. By default the hook **throws** (can be turned off via constructor option).

## When presets are not enough

You can still **override `insertEntity` / `updateEntity` / `sqlInsert` / etc.** on your repository when hook-based presets do not fit.

## See also

- `WScore\DecaORM\Trait\RepositoryTrait` — where hooks are invoked
- `WScore\DecaORM\Contracts\RepositoryHooksInterface`
- `WScore\DecaORM\Contracts\OptimisticLockException`
