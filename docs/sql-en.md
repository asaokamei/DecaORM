# SQL builder manual

DecaORM’s SQL builder lets you build type-safe, flexible SQL for queries and updates.

---

## Contents

- [Query (SELECT)](#query-select)
- [Raw SELECT and FROM](#raw-select-and-from)
- [DISTINCT, GROUP BY, HAVING, FOR UPDATE](#distinct-group-by-having-for-update)
- [Resetting appended clauses (`clear*`)](#resetting-appended-clauses-clear)
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
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->getResult();
```

### Method reference

| Method | Description |
|--------|-------------|
| `select(string ...$columns)` | **Replace** the SELECT column list (not append; no `clearSelect()` — call `select()` again) |
| `addSelect(string ...$columns)` | Append escaped columns to the SELECT list |
| `selectRaw(string $expression, array $bindings = [])` | Append a raw SELECT expression (after existing columns) |
| `from(string $table)` | FROM clause (usually set automatically) |
| `fromRaw(string $fragment, array $bindings = [])` | FROM clause from a raw fragment (e.g. derived table) |
| `where(string $column, mixed $value, string $operator = '=')` | Add WHERE condition |
| `whereIn(string $column, array $values)` | WHERE IN |
| `whereRaw(string $sql_snippet, array $bindings = [])` | Raw WHERE fragment |
| `joinRaw(string $raw_join_sql)` | Add JOIN |
| `withRaw(string $cte_sql)` | WITH (CTE) |
| `distinct(bool $on = true)` | `SELECT DISTINCT` (default off) |
| `groupBy(string ...$columns)` | GROUP BY (repeatable; columns are appended) |
| `having(string $column, mixed $value, string $operator = '=')` | HAVING condition (AND-combined) |
| `havingRaw(string $sql_snippet, array $bindings = [])` | Raw HAVING fragment |
| `orderBy(string $column, string $direction = 'ASC')` | Safe ORDER BY |
| `orderByRaw(string $sqlSnippet)` | Raw ORDER BY fragment |
| `clearWhere()` | Clear all WHERE conditions (see [Resetting appended clauses](#resetting-appended-clauses-clear)) |
| `clearJoin()` | Clear all JOIN clauses |
| `clearGroupBy()` | Clear all GROUP BY columns |
| `clearHaving()` | Clear all HAVING conditions |
| `clearOrderBy()` | Clear all ORDER BY expressions |
| `clearParameters()` | Clear the bind-parameter bag |
| `limit(?int $limit)` | LIMIT |
| `offset(?int $offset)` | OFFSET |
| `forUpdate(bool $on = true)` | Append `FOR UPDATE` (after LIMIT/OFFSET) |
| `getResult()` | Run query and get `EntityCollection` |
| `executeCountQuery()` | Run COUNT(*) and return count (int); clears `FOR UPDATE` on the internal clone |

### SELECT column list (`select` / `addSelect` / `selectRaw`)

The SELECT list follows a **replace vs append** split (unlike `where` / `orderBy`, which only append):

| Method | Behavior |
|--------|----------|
| **`select(...)`** | **Replaces** the entire column list on every call. Previous columns from `select()`, `addSelect()`, or `selectRaw()` are discarded. |
| **`addSelect(...)`** | Appends escaped column names. |
| **`selectRaw(...)`** | Appends a raw expression (same append semantics as `addSelect`). |

There is **no `clearSelect()`**. To drop all columns and start over, call **`select(...)`** with the new list (or `select()` with no arguments to clear to an empty list before `selectRaw`, as in `executeCountQuery()`).

`sqlQuery()` constructs `Query` with **`select("{$table}.*")`** already applied. To change columns, call **`select(...)`** first; using only `addSelect()` / `selectRaw()` keeps the default `table.*` and can produce `SELECT table.*, expr ...`.

```php
// Replace default table.* from sqlQuery()
$users = $repository->sqlQuery()
    ->select('u.id', 'u.name')   // replaces users.* (or users u.*)
    ->getResult();

// Append after an explicit select()
$users = $repository->sqlQuery()
    ->select('u.id')
    ->addSelect('u.name', 'u.email')
    ->selectRaw('COUNT(*) AS cnt', [])
    ->getResult();

// select() again replaces everything built so far
$builder = $repository->sqlQuery()
    ->select('u.id')
    ->addSelect('u.name')
    ->select('u.email');          // only u.email remains in the list
```

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
    ->orderBy('created_at', 'DESC')
    ->limit(20)
    ->offset(40)
    ->getResult();

// Count
$count = $repository->sqlQuery()
    ->where('status', 'active')
    ->limit(10)->offset(20)  // ignored for count
    ->executeCountQuery();
```

### Raw SELECT and FROM

Use **`selectRaw()`** and **`fromRaw()`** when you need expressions, scalar subqueries, or derived tables, but still want **one parameter bag**, **`whereIn()`**, and **`:_EXPAND_`** handling on the final SQL (same as `whereRaw` / `joinRaw`).

- **`selectRaw($expr, $bindings)`** — **Appends** after whatever `select()` already set. Call `select(...)` first if you must replace the default `table.*` from `sqlQuery()`; otherwise you can accidentally produce `SELECT *, expr ...`.
- **`fromRaw($fragment, $bindings)`** — Replaces the whole `FROM` body with your fragment (include parentheses and alias for a subquery, e.g. `(SELECT …) AS t`). **`:_EXPAND_`** markers inside the fragment are expanded; use `setParameters()` with the same rules as [IN clause expansion](#in-clause-and-array-expansion) (e.g. SQL `:_EXPAND_uid` + `['uid' => [1, 2, 3]]`).

```php
// Correlated scalar in SELECT list
$rows = $repository->sqlQuery()
    ->select('o.id', 'o.total')
    ->selectRaw(
        '(SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS line_count'
    )
    ->from('orders o')
    ->getResult();

// Derived table + IN expansion inside FROM
$rows = $repository->sqlQuery()
    ->select('sub.id')
    ->fromRaw('(SELECT id FROM users WHERE id IN (:_EXPAND_uid)) AS sub')
    ->setParameters(['uid' => $userIds])
    ->getResult();
```

UNION and other multi-statement shapes are still best written as a single raw SQL string executed with `fetch()` if the builder cannot express them cleanly.

### DISTINCT, GROUP BY, HAVING, FOR UPDATE

These clauses are emitted in SQL order: **WHERE → GROUP BY → HAVING → ORDER BY → LIMIT/OFFSET → FOR UPDATE**.

- **`distinct()`** — Adds `DISTINCT` after `SELECT`. Default is off (no `DISTINCT`). Use `distinct(false)` to turn it off again on the same builder.
- **`groupBy()`** — One or more column expressions per call; multiple calls append columns (e.g. `groupBy('a', 'b')->groupBy('c')` → `GROUP BY a, b, c`).
- **`having()` / `havingRaw()`** — Same placeholder style as `where()` / `whereRaw()`; bindings share the query’s parameter bag with WHERE. For aggregates, `havingRaw('COUNT(*) > :n', [':n' => $min])` is portable across databases; relying on a SELECT alias in HAVING is not portable (e.g. strict SQL or PostgreSQL).
- **`forUpdate()`** — Row-level lock hint for **PostgreSQL, MySQL, etc.** It is **not** supported by SQLite in the same way; omit it when targeting SQLite. `executeCountQuery()` runs on a clone with **`forUpdate(false)`** so `COUNT(*)` does not keep a lock clause.

```php
// DISTINCT (e.g. after JOINs that duplicate parent rows)
$rows = $repository->sqlQuery()
    ->select('u.id', 'u.name')
    ->from('users u')
    ->joinRaw('INNER JOIN orders o ON o.user_id = u.id')
    ->distinct()
    ->getResult();

// GROUP BY + HAVING
$stats = $repository->sqlQuery()
    ->select('status', 'COUNT(*) AS cnt')
    ->from('users')
    ->groupBy('status')
    ->havingRaw('COUNT(*) >= :min_cnt', [':min_cnt' => 5])
    ->orderBy('status')
    ->getResult();

// FOR UPDATE (inside a transaction, PostgreSQL / MySQL)
$users = $repository->sqlQuery()
    ->where('id', $id)
    ->limit(1)
    ->forUpdate()
    ->getResult();
```

### Resetting appended clauses (`clear*`)

Several builder methods **append** fragments on each call (`where`, `joinRaw`, `groupBy`, `having`, `orderBy`, and their `*Raw` variants). To drop accumulated clauses and start over on the same builder instance, use the matching **`clear*`** method.

| Method | Clears | Available on |
|--------|--------|--------------|
| `clearWhere()` | WHERE conditions | `Query`, `Update`, `Delete` |
| `clearSet()` | SET clauses | `Update` only |
| `clearJoin()` | JOIN clauses | `Query` only |
| `clearGroupBy()` | GROUP BY columns | `Query` only |
| `clearHaving()` | HAVING conditions | `Query` only |
| `clearOrderBy()` | ORDER BY expressions | `Query` only |
| `clearParameters()` | Bind-parameter bag | `Query`, `Update`, `Delete` |

**Notes:**

- **`clearWhere()` / `clearHaving()` / `clearSet()`** remove SQL fragments only. Bind values from earlier calls may remain until **`clearParameters()`** (or a new builder).
- Clauses that **replace** rather than append do not need a `clear*` helper: e.g. **`select()` replaces the column list** (there is no `clearSelect()`), `from()` / `fromRaw()` replace FROM, `limit(null)` / `offset(null)` remove LIMIT/OFFSET, `forUpdate(false)` turns off `FOR UPDATE`.
- Typical use: repository hooks or shared query setup add defaults; application code clears one clause and re-applies it, or clones a query and adjusts one part (see `executeCountQuery()`, which calls `select()` to replace columns, then `selectRaw('COUNT(*) …')`).

```php
// Replace ORDER BY on a query that already had sorting
$users = $repository->sqlQuery()
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->clearOrderBy()
    ->orderBy('id', 'ASC')
    ->getResult();

// Drop a JOIN added earlier, then attach a different one
$rows = $repository->sqlQuery()
    ->joinRaw('INNER JOIN orders o ON o.user_id = users.id')
    ->clearJoin()
    ->joinRaw('LEFT JOIN profiles p ON p.user_id = users.id')
    ->getResult();

// Reset WHERE on Update (Update/Delete also use WhereTrait)
$repository->sqlUpdate()
    ->set('status', 'inactive')
    ->where('last_login', '2020-01-01', '<')
    ->clearWhere()
    ->setId(1)
    ->execute();
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
| `data(array $data)` | **Replace** the INSERT column map (same contract as Update `data()`) |
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

// Set data later (replaces the column map; safe for reusing one Insert instance)
$insert = $repository->sqlInsert([]);
$insert->data([
    'name' => 'Bob',
    'email' => 'bob@example.com',
]);
$insert->execute();
```

`data()` on **Insert** and **Update** shares one rule: **bulk-assign the writable columns for this statement** (replace, not append). On Update, use `set()` / `setRaw()` to append SET fragments after `data()`.

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
| `set(string $column, mixed $value)` | Append SET |
| `setRaw(string $sqlSnippet, array $bindings = [])` | Append raw SET (e.g. SQL functions) |
| `data(array $data)` | **Replace** the SET column map (primary key excluded on `Update`) |
| `clearSet()` | Clear all SET clauses |
| `setId(int\|string $id)` | WHERE by primary key |
| `where(string $column, mixed $value, string $operator = '=')` | Add WHERE |
| `whereIn(string $column, array $values)` | WHERE IN |
| `whereRaw(string $sql_snippet, array $bindings = [])` | Raw WHERE |
| `clearWhere()` | Clear all WHERE conditions |
| `clearParameters()` | Clear the bind-parameter bag |
| `getSql()` | Get generated SQL |
| `getParameters()` | Get bind parameters |
| `execute()` | Execute (WHERE required) |

### SET clause (`set` / `data` / `clearSet`)

| Method | Behavior |
|--------|----------|
| **`set()` / `setRaw()`** | Append SET fragments |
| **`data()`** | **Replace** the SET column map (same contract as Insert `data()`; `Update::data()` omits the primary key) |
| **`clearSet()`** | Clear all SET fragments |

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
| `clearWhere()` | Clear all WHERE conditions |
| `clearParameters()` | Clear the bind-parameter bag |
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

- Placeholders from `where()` / `having()` / `set()` are derived from column name and a counter.
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

### 6. SQL log parameter masking

If you configure `OrmManager` with `setSqlParamMasker()`, only `params` in SQL logs are masked.
The SQL text itself (`sql`) is kept as-is, so query analysis remains possible while reducing sensitive data leakage.

```php
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Sql\KeyBasedSqlParamMasker;

$manager = OrmManager::initialize($container)
    ->setLogger($logger)
    ->setSqlParamMasker(new KeyBasedSqlParamMasker([
        'password', 'token', 'secret', 'authorization', 'api_key', 'email',
    ]));
```

- Mask targets are determined by parameter key name (case-insensitive).
- Applied recursively to nested arrays.
- If `setSqlParamMasker()` is not set, behavior remains unchanged (no masking).

---

## Summary

- **Query:** build SELECT, get entities with `getResult()`; optional `distinct()`, `groupBy()`, `having()` / `havingRaw()`, `forUpdate()`.
- **Insert:** build INSERT, run with `execute()`.
- **Update:** build UPDATE (WHERE required), run with `execute()`.
- **Delete:** build DELETE (WHERE required), run with `execute()`.
- **IN expansion:** use `whereIn()` or the `:_EXPAND_` marker with `setParameters()`.
- **Safety:** use placeholders for values; keep table/column names in code.
