# Changelog

## 0.20.0 – 2026-11-13

- Released with the framework's 0.20 line. Requires its DenoSys dependencies
  at `^0.20`; no other changes.

## 0.19.0 – 2026-11-13

- Released with the framework's 0.19 line. Requires its DenoSys dependencies
  at `^0.19`; no other changes.

## 0.18.0 – 2026-11-13

- Released with the framework's 0.18 line. Requires its DenoSys dependencies
  at `^0.18`; no other changes.

## 0.17.0 – 2026-11-12

- `DatabaseSessionHandler::destroyUserSessions()` keeps the excluded session
  with the immutable query builder.

## 0.16.0 – 2026-11-12

- Released with the framework's 0.16 line; no changes to this package.

## 0.15.0 – 2026-11-12

- Released with the framework's 0.15 line; no changes to this package.

## 0.14.0 – 2026-11-12

- Released with the framework's 0.14 line; no changes to this package.

## 0.13.0 – 2026-11-12

- Released with the framework's 0.13 line; no changes to this package.

## 0.12.0 – 2026-11-12

- Released with the framework's 0.12 line; no changes to this package.

## 0.11.1 – 2026-11-12

### Fixed

- Requirements on other DenoSys packages are `^0.11`. In 0.11.0 they were
  still `^0.10`, which for a 0.x version allows only 0.10 releases, so 0.11.0
  packages resolved their siblings to 0.10 or could not be installed. Use
  0.11.1 instead of 0.11.0.

## 0.11.0 – 2026-11-12

- **Breaking:** `Denosys\Session\SessionServiceProvider` moved to the framework as `Denosys\Framework\Providers\SessionServiceProvider`. Packages no longer ship service providers; the framework wires packages together.
- No longer requires `denosyscore/http`; requires container and PSR-7/15 packages.

## 0.10.0 – 2026-11-12

### Changed

- Released from the [denosyscore/framework](https://github.com/denosyscore/framework)
  monorepo, where this package now lives under `packages/session`. All DenoSys
  packages share one version line, so the version moves from v0.2.6 to
  0.10.0. There are no API changes in this release.
- Requirements on other DenoSys packages are `^0.10`.

## 0.2.6 – 2026-11-11

- Accept `denosyscore/http` 0.4, which removes classes this package never used.
  CI tests it with database 0.5.0.

## 0.2.5 – 2026-11-11

- Accept `denosyscore/database` 0.5. The database session handler reads, writes,
  destroys and collects sessions correctly against 0.5.0.
- Require `psr/log`, which the session handlers import. Without it,
  `DatabaseSessionHandler` failed because `Psr\Log\NullLogger` was missing
  whenever no logger was passed.

## 0.2.4

- Accept database 0.4 while retaining the supported 0.2 and 0.3 lines.
- Verify the new database version with HTTP 0.3.1 in CI on PHP 8.2 and 8.5.
