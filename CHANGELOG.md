# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Added pagination support with `Query::paginate()` and `PaginatedResult`.

### Fixed
- Fixed a bug where `NULL` values from the database were ignored during hydration.
- Prevented unsaved changes (dirty entities) from being overwritten during re-fetching via identity map.

### Documentation
- Added documentation for pagination in both English and Japanese SQL guides.

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
