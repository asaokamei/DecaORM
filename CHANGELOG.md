# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
<<<<<<< HEAD
- Added pagination support with `Query::paginate()` and `PaginatedResult`.

### Fixed
- Improved `KeyBasedSqlParamMasker` to mask sensitive values even when using ORM-generated placeholders like `password_0` or `set_password_1`.
- Fixed a bug where `NULL` values from the database were ignored during hydration.
- Prevented unsaved changes (dirty entities) from being overwritten during re-fetching via identity map.
=======
- Added `apply` option to `ManyToMany` attribute, enabling source-repository apply methods to customize target query loading in the same style as `HasMany`/`HasOne`.
>>>>>>> ed1ab88 (feat: Add `apply` option to `ManyToMany` for custom target query filtering via repository methods)

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
