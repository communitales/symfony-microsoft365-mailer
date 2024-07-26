Microsoft 365 Mailer
====================

Provides Microsoft 365 integration for Symfony Mailer.

Configuration example:

```env
# API
MAILER_DSN=microsoft365+api://CLIENT_ID:CLIENT_SECRET@default?tenant_id=TENANT_ID&username=USERNAME
```

where:
 - `CLIENT_ID` is your Microsoft 365 API client ID
 - `CLIENT_SECRET` is your Microsoft 365 API client secret
 - `TENANT_ID` is your Microsoft 365 API tenant ID
 - `USERNAME` is your Microsoft 365 API username

Resources
---------

* [Contributing](https://symfony.com/doc/current/contributing/index.html)
* [Report issues](https://github.com/symfony/symfony/issues) and
  [send Pull Requests](https://github.com/symfony/symfony/pulls)
  in the [main Symfony repository](https://github.com/symfony/symfony)
