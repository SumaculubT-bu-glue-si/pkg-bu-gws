# Business Unit Google Workspace (GWS) Package

A Laravel package for integrating Google Workspace user and org unit sync, with GraphQL and REST API support.

---

## Features

- Sync Google Workspace users to your local database
- Batch and real-time sync options
- Artisan commands for manual sync
- Webhook endpoint for Google Workspace events
- Uses host app’s `.env` and config for credentials
- Redis caching and monitoring support

---

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Google Workspace service account with Directory API access
- Domain-wide delegation enabled
- Redis (for caching)

---

## Installation

1. **Require the package via Composer:**
   ```bash
    {
      "minimum-stability": "dev",
      "repositories": [
        {
          "type": "vcs",
          "url": "https://github.com/izuminaoki2025/pkg-bu-gws.git"
        }
      ],
      "require": {
        "bu/gws": "dev-main"
      }
    }
   ```

2. **Install required package**
   ```bash
    composer require google/apiclient
   ```

3. **Publish config:**
   ```bash
    php artisan vendor:publish --provider="Bu\Gws\Providers\GwsServiceProvider"

      or

    cp -f vendor/bu/gws/routes/api.php routes/api.php
    cp -f vendor/bu/gws/graphql/schema.graphql graphql/schema.graphql
    cp -f vendor/bu/gws/src/Providers/GoogleWorkspaceServiceProvider.php app/Providers/GoogleWorkspaceServiceProvider.php
    cp -f vendor/bu/gws/AppServiceProvider.php app/Providers/AppServiceProvider.php
   ```

4. **Add credentials to your host app’s `.env`:**
   ```
   Ask the Be Unique IT Engineers for this credentials
   ```

5. **Place your Google service account JSON key in `storage/app/google-workspace-key.json` (or your chosen path).**

---

## Usage

### 1. Sync Users via Artisan Command

Run from your host Laravel project root:

```bash
php artisan gws:sync-users yourdomain.com --all
```

### 2. Webhook Endpoint

Google Workspace can POST events to:
```
POST /api/gws/webhook
```
Configure this endpoint in your Google Workspace admin console.

## Troubleshooting

- **Client initialization errors:**  
  Check your `.env` and credentials file path. See `storage/logs/laravel.log` for details.
- **Google API errors:**  
  Ensure your service account has Directory API access and domain-wide delegation.
- **Command not found:**  
  Make sure your provider registers the command in `$this->commands([...])`.

---

## Development

- Commands are in `src/Console/Commands`
- Services are in `src/Services`
- API routes in `routes/api.php`
- Webhook controller in your host app (`App\Http\Controllers\GoogleWorkspaceWebhookController`)

---

## License

MIT

---

## Support

Open an issue or contact the package maintainer.