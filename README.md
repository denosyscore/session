# denosyscore/session

Session management and handlers

## Status

Initial extraction snapshot from cfxprimes-core as of 2026-02-14.

## Installation

composer require denosyscore/session

## Included Modules

- src/Session/*

## Development

composer validate --strict
find src -type f -name '*.php' -print0 | xargs -0 -n1 php -l

## CI Workflows

- CI: composer validation + PHP syntax lint on push and pull requests.
- Release: GitHub release publication on semantic version tags.
- Dependabot: weekly Composer dependency update checks.
