# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- リレーションアトリビュート（`HasMany`, `BelongsTo`, `HasOne`, `BelongsToOne`, `ManyToMany`）に `targetScope` 設定を追加しました。ターゲット側リポジトリのメソッドで関連取得クエリにスコープを適用できます。`sourceFilter` と同じく、メソッドシグネチャは `(Query $query, EntityInterface|EntityCollection $source): void` です。

### Changed
- リレーションアトリビュート（`HasMany`, `BelongsTo`, `HasOne`, `BelongsToOne`, `ManyToMany`）の `apply` 設定を `sourceFilter` に名称変更しました。過去互換性を保持するため `apply` も引き続き利用可能です。

## [0.5.8] - 2026-07-16

### Added
- `EntityTrait` に `isDirty()` メソッドを追加しました。エンティティのプロパティが最後にロードまたは保存されたときから変更されているかどうかを確認できます。
- `DirtyTracker::isDirty()` を追加し、スナップショットとの比較による変更検知ロジックを実装しました。

## [0.5.7] - 2026-07-07

### Added
- `QueryBuilder::select` および `orderBy` において、`column AS alias` 形式（`AS` なしの空白区切りも含む）のエイリアス指定をサポートしました。識別子のクォート処理も適切に行われます。

## [0.5.6] - 2026-06-24

### Added
- Added `QueryPdo`, a SELECT query builder that executes via PDO only (no `RepositoryInterface`). Supports `fetchAll()`, `fetchStream()`, `getPdoStatement()`, `executeCountQuery()`, and `paginate()` with associative rows.
- Optional `SqlExecutor` injection on `QueryPdo` for SQL logging.

### Changed
- Extended `PaginatedResult::getItems()` to return `EntityCollection|array`, so `Query::paginate()` and `QueryPdo::paginate()` share the same result type.
- Extracted `QueryBuilder::toCountSubquery()` for shared COUNT wrapper logic used by `Query` and `QueryPdo`.
- `PaginatedResult::getLastPage()` now returns `0` when `perPage` is `0`.

### Tests
- Added `QueryPdoTest` covering fetch, stream, pagination, count queries, and optional `SqlExecutor` usage.

## [0.5.5] - 2026-06-19

### Fixed
- Fixed `HasMany`/`HasOne` `mappedBy` resolution for inverse `BelongsTo`/`BelongsToOne` with `ownerKey`, so reverse loading now matches by `ownerKey` instead of always using parent primary key.

### Tests
- Added regression tests for `ownerKey != PK` reverse-loading cases: `HasMany(mappedBy: BelongsTo)` and `HasOne(mappedBy: BelongsToOne)` batch loading behavior, including duplicate `ownerKey` first-parent resolution.

## [0.5.4] - 2026-06-17

### Added
- Added pagination support with `Query::paginate()` and `PaginatedResult`.
- Added `apply` option to `ManyToMany` attribute, enabling source-repository apply methods to customize target query loading in the same style as `HasMany`/`HasOne`.

### Fixed
- Improved `KeyBasedSqlParamMasker` to mask sensitive values even when using ORM-generated placeholders like `password_0` or `set_password_1`.
- Fixed a bug where `NULL` values from the database were ignored during hydration.
- Prevented unsaved changes (dirty entities) from being overwritten during re-fetching via identity map.

### Changed
- Expanded `psr/container` requirement to `^1.0 || ^2.0` and adjusted test fixture container signatures for PSR-11 1.x/2.x compatibility.

### Documentation
- Added documentation for pagination in both English and Japanese SQL guides.

### Tests
- Added a `ManyToMany` apply test case to verify target-query filtering behavior via repository apply method.

## [0.5.3] - 2026-06-09

### Changed
- Standardized SQL builders (`Query`, `Insert`, `Update`, `Delete`) to resolve table names via `Repository::getTableName()` instead of direct hydrator access.

### Breaking
- Added `getTableName(): string` to `RepositoryInterface`; custom repository implementations must provide this method.

## [0.5.2] - 2026-06-09

### Added
- Added `whereNotIn` support for SQL `NOT IN` conditions with placeholder expansion and empty-array handling.
- Added `RETURNING` clause support to `Insert`.

### Changed
- Enhanced `lastInsertId` behavior to cooperate with `RETURNING` results.
- Improved `where` / `having` handling for `NULL` and array values.
- Extended `forUpdate` signature to `forUpdate(bool $on = true, bool $noWait = false, bool $skipLocked = false)` and support appending `NOWAIT` / `SKIP LOCKED`.

### Documentation
- Documented `whereNotIn` usage and behavior in English and Japanese SQL guides.
