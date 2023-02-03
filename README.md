Composer Audit
==============

Audit your Composer dependencies for security vulnerabilities, uses
data from [FriendsOfPHP/security-advisories][]. Compatible with Composer 1 and 2.

This Composer plugin allows you to audit your dependencies for security
vulnerabilities *without* sending your lock file to an
[external service][security.symfony.com] or using
[closed source software](https://github.com/symfony/cli/issues/37).

Note this command is *distinct* from the `audit` command built into Composer ≥ 2.4.

Installation
------------

This plugin can either be installed as a dependency in your project or “globally”
so that it is always available on your machine.

### Install as a development dependency

```sh
composer require --dev cs278/composer-audit ^1
```

### Install globally

```sh
composer global require cs278/composer-audit ^1
```

Usage
-----

### Audit dependencies

This will audit all locked dependencies from `composer.lock`.

If your package does not have a `composer.lock` file (e.g. because it’s a
library) the installed packages, located in `vendor/composer/installed.json`
will be validated instead.

```sh
composer security-audit
```

### Audit non development dependencies

Only audit your production dependencies from `composer.lock`, this option only
works when there is a `composer.lock` file.

```sh
composer security-audit --no-dev
```

### Update security advisories database

You can force an update of the security advisories database using the `--update`
option, without this option being supplied the database will be downloaded if it
does not exist or it’s more than an hour old. For example:

```sh
composer security-audit --update
```

Configuration
-------------

Composer Audit can be configured using the [`extra`][composer.json extra] property
in your `composer.json` file, all configuration should be supplied under the
`composer-audit` key.

```json
{
    ...
    "extra": {
        ...
        "composer-audit": {
            "option1": "super"
        },
        ...
    },
    ...
}
```

### Ignoring an advisory

Currently only filtering advisories by CVE is possible, further options are planned.

#### Ignoring an advisory by CVE

You are able to ignore warnings about an advisory by filtering based on its CVE
reference, this is useful if you decide the risk is acceptable or not applicable
and you cannot otherwise upgrade the package to resolve the problem.

```json
{
    ...
    "extra": {
        ...
        "composer-audit": {
            "ignore": [
                {"type": "cve", "value": "CVE-2000-1234567"},
                {"type": "cve", "value": "CVE-2000-7654321"}
            ]
        },
        ...
    },
    ...
}
```

Example
-------

```sh
# Require a vulnerable package
composer require symfony/http-foundation 2.0.4

# Require Composer Audit
composer require --dev cs278/composer-audit ^1

composer security-audit
Found 9 advisories affecting 1 package(s).

composer://symfony/http-foundation (2.0.4)
* Request::getClientIp() when the trust proxy mode is enabled
* CVE-2012-6431: Routes behind a firewall are accessible even when not logged in
* CVE-2013-4752: Request::getHost() poisoning
* CVE-2014-5244: Denial of service with a malicious HTTP Host header
* CVE-2014-6061: Security issue when parsing the Authorization header
* CVE-2015-2309: Unsafe methods in the Request class
* CVE-2018-11386: Denial of service when using PDOSessionHandler
* CVE-2018-14773: Remove support for legacy and risky HTTP headers
* CVE-2019-18888: Prevent argument injection in a MimeTypeGuesser
```

Hyperlinks will be rendered to the appropriate CVE and advisory where available.

[composer.json extra]: https://getcomposer.org/doc/04-schema.md#extra
[FriendsOfPHP/security-advisories]: https://github.com/FriendsOfPHP/security-advisories
[security.symfony.com]: https://security.symfony.com/
