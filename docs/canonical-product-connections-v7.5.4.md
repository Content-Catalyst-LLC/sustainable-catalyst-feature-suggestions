# Canonical Product Connections — v7.5.4

Each connection has one canonical identity and two evidence sources:

1. **Console product:** the authoritative public identity, family, label, visibility, and support routing.
2. **Active WordPress plugin:** the installed implementation, installed version, activation state, plugin file, slug, and text domain.
3. **GitHub repository:** the source repository, latest release, release date, commit, and repository update time.

## Administrator workflow

Open **Product Support → Plugin Discovery**. In **Canonical product connections**, locate the console product, select any currently active site or network plugin, enter its GitHub repository URL, and choose **Save connection**. Use **Sync GitHub now** to verify the connection immediately.

Inactive plugins do not appear in the dropdown. Mapping identifiers remain governed aliases and the physical WordPress plugin folder is never renamed.

## Automatic GitHub updates

Add these constants outside version control, normally in `wp-config.php` or the hosting environment:

```php
define('SCFS_GITHUB_TOKEN', 'github-token-for-private-repositories');
define('SCFS_GITHUB_WEBHOOK_SECRET', 'a-long-random-webhook-secret');
```

Public repositories do not require `SCFS_GITHUB_TOKEN`, though an authenticated token increases GitHub API rate limits.

Configure the GitHub webhook payload URL as:

```text
https://YOUR-SITE.example/wp-json/scfs/v1/product-registry/github/webhook
```

Use `application/json`, enter the same webhook secret, and enable repository push and release events. A valid webhook triggers an immediate product sync. WordPress also polls connected repositories hourly as a fallback.

## Console behavior

The latest GitHub release becomes the public Release Console version. The active plugin remains the installed version. When the installed version is lower, the console state becomes **Update available**. The footer's `./repository` link resolves to the connected GitHub repository rather than `/support/releases/`.


## GitHub credentials

Private-repository access can now be configured from **Support & Feedback → GitHub Connection**. The saved token is encrypted and is never displayed after saving. Use **Test repository access** to check one mapped product, including Sustainable Catalyst Contact and Engagement Platform, before running a full synchronization.

## Console footer destinations

Repository and support footer destinations are edited from **Support & Feedback → Release Console Copy → Footer links**. The repository destination can remain blank to follow the canonical `product-support-feedback` GitHub mapping.
