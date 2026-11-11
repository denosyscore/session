# Changelog

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
