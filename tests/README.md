# Safe test execution

The ordinary suites run on SQLite `:memory:`. Their bootstrap replaces inherited
application database credentials in `getenv()`, `$_ENV`, and `$_SERVER` before
Laravel boots. It does not load the checkout's `.env` or cached application
configuration. A guarded Laravel connection factory rejects network databases,
SQLite files, URLs, sockets, and read/write/direct endpoint overrides before PDO
construction. Unmocked installer MySQL probes also fail closed.

Each process owns a randomly named temporary directory containing its environment
fixture, Laravel storage, installer state, and config/route/event cache paths.
Parallel workers and simultaneous runs cannot share these paths. Normal shutdown
removes the process directory; an abruptly killed process may leave its temporary
directory behind. No real checkout `.env` is rewritten or restored by tests.

```bash
composer test
composer test:parallel
composer test:serial
./scripts/test-fast.sh
```

These commands do not clear the application's config cache. HTTP requests must be
faked; unexpected Laravel HTTP requests are prohibited. The policy regressions in
`tests/Safety` run without booting Laravel or migrating databases.
Composer's process timeout is disabled for these test scripts so a long suite is
not terminated at the default 300 seconds.

## Optional MySQL overlap integration tests

The two overlap tests retain their original assertions in
`tests/Integration/MySql/ConcurrencyOverlapTest.php`. They are outside the ordinary
suite and require both the separate configuration and explicit opt-in:

```bash
ARK_TEST_MYSQL=1 \
ARK_TEST_MYSQL_HOST=127.0.0.1 \
ARK_TEST_MYSQL_PORT=13306 \
ARK_TEST_MYSQL_USERNAME=ark_test_runner \
ARK_TEST_MYSQL_PASSWORD='<disposable-instance-password>' \
ARK_TEST_MYSQL_DATABASE_PREFIX=ark_test_overlap \
composer test:mysql
```

Supply a dedicated disposable local MySQL instance and a dedicated `ark_test_*`
account. Never use a shop database server, production tunnel, or developer account.
Only literal loopback hosts are accepted. All settings are mandatory; there are
no `.env`, `DB_*`, root-account, or default-port fallbacks.

The account needs CREATE, migration/DML, and DROP privileges for the chosen
`ark_test_*` prefix. The runner generates a random database suffix for every test
and uses CREATE without IF NOT EXISTS: it cannot reuse an existing database. It
records the successfully created target in a private temporary context. Parent
and child helpers require this context and receive only an allowlisted process
environment, never inherited application credentials. Laravel connections must
match the owned database exactly. The runner drops only its newly created database
in `finally`, including on test failures. Hard termination can leave a disposable
database behind; clean it up on the disposable instance after confirming ownership.

Missing opt-in, unsafe configuration, or direct helper invocation is an error,
not a skipped or weakened test. MySQL requires its own execution evidence; a green
ordinary suite does not certify MySQL concurrency semantics.
