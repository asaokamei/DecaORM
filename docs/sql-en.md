# SQL builder manual

DecaORM’s SQL builder lets you build type-safe, flexible SQL for queries and updates.

---

## Contents

- [Query (SELECT)](#query-select)
- [Insert (INSERT)](#insert-insert)
- [Update (UPDATE)](#update-update)
- [Delete (DELETE)](#delete-delete)
- [IN clause and array expansion](#in-clause-and-array-expansion)
- [Important notes](#important-notes)

---

## Query (SELECT)

Get a Query instance from the repository with `sqlQuery()`.

### Basic usage

```php
$users = $repository->sqlQuery()
    ->where('status', 'active')
    ->orderBy('created_at DESC')
    ->limit(10)
    ->getResult();
```

### Method reference

| Method | Description |
|--------|-------------|
| `select(string ...$columns)` | SELECT columns |
| `from(string $table)` | FROM clause (usually set automatically) |
| `where(string $column, mixed $value, string $operator = '=')` | Add WHERE condition |
| `whereIn(string $column, array $values)` | WHERE IN |
| `whereRaw(string $sql_snippet, array $bindings = [])` | Raw WHERE fragment |
| `joinRaw(string $raw_join_sql)` | Add JOIN |
| `withRaw(string $cte_sql)` | WITH (CTE) |
| `orderBy(string $column)` | ORDER BY |
| `limit(?int $limit)` | LIMIT |
| `offset(?int $offset)` | OFFSET |
| `getResult()` | Run query and get `EntityCollection` |
| `executeCountQuery()` | Run COUNT(*) and return count (int) |

### Examples

```php
// Multiple WHERE conditions
$users = $repository->sqlQuery()
    ->where('status', 'active')
    ->where('age', 25, '>=')
    ->getResult();

// WHERE IN
$userIds = [1, 2, 3, 4, 5];
$users = $repository->sqlQuery()
    ->whereIn('id', $userIds)
    ->getResult();

// Complex WHERE (e.g. OR)
$users = $repository->sqlQuery()
    ->where('status', 'active')
    ->whereRaw('(age > :min_age OR score > :max_score)', [
        ':min_age' => 25,
        ':max_score' => 90,
    ])
    ->getResult();

// JOIN
$users = $repository->sqlQuery()
    ->select('u.*', 'p.name AS profile_name')
    ->from('users u')
    ->joinRaw('LEFT JOIN profiles p ON u.id = p.user_id')
    ->where('u.status', 'active')
    ->getResult();

// WITH (CTE)
$users = $repository->sqlQuery()
    ->withRaw("recent_orders AS (SELECT user_id, amount FROM orders WHERE order_date > '2024-01-01')")
    ->select('u.*', 'ro.amount')
    ->from('users u')
    ->joinRaw('LEFT JOIN recent_orders ro ON u.id = ro.user_id')
    ->getResult();

// Pagination
$users = $repository->sqlQuery()
    ->where('status', 'active')
    ->orderBy('created_at DESC')
    ->limit(20)
    ->offset(40)
    ->getResult();

// Count
$count = $repository->sqlQuery()
    ->where('status', 'active')
    ->limit(10)->offset(20)  // ignored for count
    ->executeCountQuery();
```

---

## Insert (INSERT)

Get an Insert builder with `sqlInsert()`.

### Basic usage

```php
$insert = $repository->sqlInsert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'status' => 'active',
]);
$insert->execute();
```

### Method reference

| Method | Description |
|--------|-------------|
| `data(array $data)` | Set insert data |
| `getSql()` | Get generated SQL |
| `getParameters()` | Get bind parameters |
| `execute()` | Execute the SQL |

### Examples

```php
// Simple INSERT
$repository->sqlInsert([
    'name' => 'Alice',
    'email' => 'alice@example.com',
])->execute();

// Set data later
$insert = $repository->sqlInsert([]);
$insert->data([
    'name' => 'Bob',
    'email' => 'bob@example.com',
]);
$insert->execute();
```

---

## Update (UPDATE)

Get an Update builder with `sqlUpdate()`.

**Important:** UPDATE **requires** at least one WHERE condition. Otherwise a `RuntimeException` is thrown.

### Basic usage

```php
$update = $repository->sqlUpdate();
$update->set('name', 'Jane Doe')
    ->setId(1)  // WHERE by primary key
    ->execute();
```

### Method reference

| Method | Description |
|--------|-------------|
| `set(string $column, mixed $value)` | Add SET |
| `setRaw(string $sqlSnippet, array $bindings = [])` | Raw SET (e.g. SQL functions) |
| `data(array $data)` | Set multiple columns (primary key is excluded) |
| `setId(int\|string $id)` | WHERE by primary key |
| `where(string $column, mixed $value, string $operator = '=')` | Add WHERE |
| `whereIn(string $column, array $values)` | WHERE IN |
| `whereRaw(string $sql_snippet, array $bindings = [])` | Raw WHERE |
| `getSql()` | Get generated SQL |
| `getParameters()` | Get bind parameters |
| `execute()` | Execute (WHERE required) |

### Examples

```php
// Update by primary key
$repository->sqlUpdate()
    ->set('name', 'Alice')
    ->setId(1)
    ->execute();

// Multiple columns
$repository->sqlUpdate()
    ->data([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'status' => 'active',
    ])
    ->setId(2)
    ->execute();

// SQL function (setRaw)
$repository->sqlUpdate()
    ->setRaw('updated_at = CURRENT_TIMESTAMP')
    ->set('name', 'Charlie')
    ->setId(3)
    ->execute();

// Complex WHERE
$repository->sqlUpdate()
    ->set('status', 'inactive')
    ->where('last_login', '2020-01-01', '<')
    ->whereIn('id', [10, 20, 30])
    ->execute();

// data() excludes primary key from SET
$repository->sqlUpdate()
    ->data([
        'id' => 999,   // Not included in SET
        'name' => 'David',
    ])
    ->setId(4)  // Used in WHERE
    ->execute();
```

---

## Delete (DELETE)

Get a Delete builder with `sqlDelete()`.

**Important:** DELETE **requires** at least one WHERE condition. Otherwise a `RuntimeException` is thrown.

### Basic usage

```php
$delete = $repository->sqlDelete();
$delete->setId(1)
    ->execute();
```

### Method reference

| Method | Description |
|--------|-------------|
| `setId(int\|string $id)` | WHERE by primary key |
| `where(string $column, mixed $value, string $operator = '=')` | Add WHERE |
| `whereIn(string $column, array $values)` | WHERE IN |
| `whereRaw(string $sql_snippet, array $bindings = [])` | Raw WHERE |
| `getSql()` | Get generated SQL |
| `getParameters()` | Get bind parameters |
| `execute()` | Execute (WHERE required) |

### Examples

```php
// Delete by primary key
$repository->sqlDelete()
    ->setId(1)
    ->execute();

// Multiple conditions
$repository->sqlDelete()
    ->where('status', 'inactive')
    ->where('last_login', '2020-01-01', '<')
    ->execute();

// WHERE IN
$repository->sqlDelete()
    ->whereIn('id', [10, 20, 30])
    ->execute();
```

---

## IN clause and array expansion

When you pass an array to an IN clause, DecaORM expands it into placeholders so you can safely use a variable number of values without SQL injection.

### Using `whereIn()`

```php
$userIds = [1, 2, 3, 4, 5];
$users = $repository->sqlQuery()
    ->whereIn('id', $userIds)
    ->getResult();
```

Internally this becomes:

```sql
-- Generated SQL
WHERE id IN (:_EXPAND_id_0_0, :_EXPAND_id_0_1, :_EXPAND_id_0_2, :_EXPAND_id_0_3, :_EXPAND_id_0_4)

-- Parameters
[
    '_EXPAND_id_0_0' => 1,
    '_EXPAND_id_0_1' => 2,
    '_EXPAND_id_0_2' => 3,
    '_EXPAND_id_0_3' => 4,
    '_EXPAND_id_0_4' => 5,
]
```

### Manual expansion (`:_EXPAND_` marker)

In places where `whereIn()` is not available (e.g. inside a JOIN), use a placeholder with the `:_EXPAND_` prefix and pass the array via `setParameters()`.

```php
$userIds = [1, 2, 3];

$query = $repository->sqlQuery()
    ->select('u.*', 'o.amount')
    ->from('users u')
    ->joinRaw('LEFT JOIN orders o ON u.id = o.user_id AND o.user_id IN (:_EXPAND_user_id)')
    ->whereIn('u.id', $userIds)
    ->setParameters(['user_id' => $userIds]);

$users = $query->getResult();
```

Rules:

1. **Placeholder:** use `:_EXPAND_user_id` (with colon).
2. **Parameter key:** in `setParameters()` use `user_id` (no colon, no `_EXPAND_` prefix).
3. The array is expanded into multiple placeholders automatically.

### How expansion works

```php
// 1. Use :_EXPAND_user_id in SQL
->joinRaw('... AND user_id IN (:_EXPAND_user_id)')

// 2. Pass array in setParameters()
->setParameters(['user_id' => [1, 2, 3]])

// 3. Result
// SQL: ... AND user_id IN (:_EXPAND_user_id_0, :_EXPAND_user_id_1, :_EXPAND_user_id_2)
// Params: ['_EXPAND_user_id_0' => 1, '_EXPAND_user_id_1' => 2, '_EXPAND_user_id_2' => 3]
```

### Empty array

If you pass an empty array to `whereIn()`, a condition that returns no rows is used:

```php
$users = $repository->sqlQuery()
    ->whereIn('id', [])
    ->getResult();
// Generated: WHERE (1 = 0)
```

### Multiple IN clauses

Each IN is expanded separately; placeholder names do not collide.

```php
$query = $repository->sqlQuery()
    ->whereIn('user_id', [1, 2, 3])
    ->whereIn('category_id', [10, 20, 30])
    ->getResult();
```

---

## Important notes

### 1. UPDATE/DELETE require WHERE

UPDATE and DELETE must have at least one WHERE condition. Otherwise `RuntimeException` is thrown.

```php
// ❌ Error: no WHERE
$repository->sqlUpdate()
    ->set('name', 'Alice')
    ->execute();  // RuntimeException

// ✅ Correct
$repository->sqlUpdate()
    ->set('name', 'Alice')
    ->setId(1)
    ->execute();
```

### 2. Update: primary key is never in SET

`Update::data()` never puts the primary key into the SET clause.

```php
$repository->sqlUpdate()
    ->data([
        'id' => 999,   // Omitted from SET
        'name' => 'Alice',
    ])
    ->setId(1)  // Used in WHERE
    ->execute();
```

### 3. Placeholder naming

- Placeholders from `where()` / `set()` are derived from column name and a counter.
- For manual IN expansion use the `:_EXPAND_` prefix.
- In `setParameters()` use the name **without** the `_EXPAND_` prefix.

### 4. SQL injection prevention

- **Table and column names:** always from your code, never from user input.
- **Values:** always use placeholders.
- With `whereRaw()` / `setRaw()`, use bound parameters.

```php
// ❌ Dangerous
$column = $_GET['column'];
$query->whereRaw("{$column} = 'value'");

// ✅ Safe: column from code
$query->where('status', 'active');

// ✅ Safe: value in placeholder
$query->whereRaw('created_at > :date', [':date' => $userInput]);
```

### 5. When expansion runs

Calling `getSql()` or `getParameters()` triggers IN expansion. Results are cached, so repeated calls return the same SQL/params.

```php
$query = $repository->sqlQuery()->whereIn('id', [1, 2, 3]);

$sql1 = $query->getSql();       // Expansion runs
$sql2 = $query->getSql();       // Cached

$params1 = $query->getParameters();  // Uses cache if already expanded
```

---

## Summary

- **Query:** build SELECT, get entities with `getResult()`.
- **Insert:** build INSERT, run with `execute()`.
- **Update:** build UPDATE (WHERE required), run with `execute()`.
- **Delete:** build DELETE (WHERE required), run with `execute()`.
- **IN expansion:** use `whereIn()` or the `:_EXPAND_` marker with `setParameters()`.
- **Safety:** use placeholders for values; keep table/column names in code.
