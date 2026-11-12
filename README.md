# denosyscore/session

> **Read-only split.** This package is developed in [denosyscore/framework](https://github.com/denosyscore/framework) under `packages/session`. Open issues and pull requests there.

Session management and handlers

## Installation

```bash
composer require denosyscore/session
```

## Quick start

Store per-visitor data between requests in files, a database, cookies or memory.

<!-- quickstart -->
```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Denosys\Encryption\Encrypter;
use Denosys\Session\Handlers\FileSessionHandler;
use Denosys\Session\Store;

$path = sys_get_temp_dir() . '/my-app-sessions';
@mkdir($path, 0700, true);
$handler = new FileSessionHandler($path, minutes: 120);
$encrypter = new Encrypter(Encrypter::generateKey());  // optional: encrypt stored payloads

// First request: start, write and save.
$session = new Store('app_session', $handler);
$session->setEncrypter($encrypter);
$session->start();
$session->put('cart', ['sku-1', 'sku-2']);
$session->flash('status', 'Item added');
$session->save();
$id = $session->getId();  // send this in the session cookie

// Next request: the same id restores the data.
$next = new Store('app_session', $handler, $id);
$next->setEncrypter($encrypter);
$next->start();
print_r($next->get('cart'));        // ['sku-1', 'sku-2']
echo $next->getFlash('status'), "\n";  // Item added
```

## Included Modules

- src/Session/*

## Development

composer validate --strict
find src -type f -name '*.php' -print0 | xargs -0 -n1 php -l


## Compatibility and releases

The Composer requirements accept database 0.2, 0.3, and 0.4, plus HTTP
0.2 and 0.3. CI installs the supported combinations on PHP 8.2 and 8.5. After CI passes on `main`,
create an annotated semantic version tag from that reviewed commit and push
it. The Release workflow publishes a GitHub Release. Confirm the matching
version is available in the Composer package index before updating consumers.
