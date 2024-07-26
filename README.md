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

```yaml
# config/services.yaml
services:
    Communitales\Symfony\Component\Mailer\Bridge\Microsoft365\Transport\Microsoft365TransportFactory:
        tags: ['mailer.transport_factory']
```

Register App for Microsoft credentials
--------------------------------------

* [Quickstart: Register an application with the Microsoft identity platform](https://learn.microsoft.com/en-us/entra/identity-platform/quickstart-register-app?tabs=certificate)

Add the API-Permissions:

* Microsoft Graph
    * Mail.ReadWrite
    * Mail.Send

Resources
---------

* [Contributing](https://symfony.com/doc/current/contributing/index.html)
* [Report issues](https://github.com/symfony/symfony/issues) and
  [send Pull Requests](https://github.com/symfony/symfony/pulls)
  in the [main Symfony repository](https://github.com/symfony/symfony)
