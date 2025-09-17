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

1. **Add credentials to your `.env` (contact the Be Unique IT Engineer for these credentials):**
```bash
  # Google Workspace Configuration
  GOOGLE_WORKSPACE_CREDENTIALS_PATH=app/aims-(google_work_space_key).json
  GOOGLE_WORKSPACE_ADMIN_EMAIL=admin@bu.glue-si.com(admin_email)
  GOOGLE_WORKSPACE_DOMAIN=bu.glue-si.com(sample_subdomain)
  GOOGLE_WORKSPACE_SYNC_BATCH_SIZE=100(batch_size)
  GOOGLE_WORKSPACE_SYNC_DELAY=1(delay_in_seconds)

  # Google Workspace Security Settings
  GOOGLE_WORKSPACE_DEBUG_LOGGING=true (debug_logging)
  GOOGLE_WORKSPACE_TIMEOUT=60 (timeout_in_seconds)
  GOOGLE_WORKSPACE_APP_NAME="AssetWise"(app_name)
  GOOGLE_WORKSPACE_SSL_VERIFY= (ssl_verify)
  GOOGLE_WORKSPACE_WEBHOOK_SECRET=aims-dev-webhook-2025(webhook_secret)
```

2. **Place your Google service account JSON key in `storage/app/google-workspace-key.json` (or your chosen path).**

3. **Require the package via Composer:**
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

4. **Install required package**
```bash
  composer require google/apiclient
```

5. **Publish necessary files:**
```bash
  php artisan vendor:publish --provider="Bu\Gws\Providers\GwsServiceProvider" --force
```

---

## Usage

### 1. Sync Users via Artisan Command

Run from your host Laravel project root to sync all the users of this subdomain to database `employees` table:

```bash
  php artisan gws:sync-users bu.glue-si.com --all
```

### 2. Webhook Endpoint

Run Google Workspace end point to update the GWS for all operations from user insterface:
```bash
  php artisan queue:work --daemon
```

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