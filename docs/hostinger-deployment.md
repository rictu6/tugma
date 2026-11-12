# Hostinger production deployment

This procedure deploys a manually selected default-branch commit through a GitHub Actions `production` environment and SSH. It builds production dependencies and Vite assets on GitHub, uploads a release, runs migrations, and switches the `current` symlink. It never runs seeders or generates an application key. A failed migration can still alter the database, even if the new release is not activated.

## Before deploying

- The target-column migration adds nullable `accomplishments.target` if it is missing and preserves an existing column. Review and test it against a production-like database before deployment. Its rollback intentionally stops for manual review to avoid dropping pre-existing data. Deployment checks for the column after migrations; this is not a substitute for testing the full schema or verifying an existing column's definition.
- Run the full test suite and test the release and migrations against a production-like copy of the database. Existing project memory records test failures; this deployment workflow does not run tests and must not be treated as a CI gate.
- Verify Hostinger provides SSH, Bash, GNU `tar` and `mv -T`, symlinks, Composer 2, required PHP extensions, and a way to serve **only** the release's `public` directory. Hostinger states that Web, WordPress and Cloud hosting packages do not allow changing the home directory in the control panel. Ask Hostinger whether a supported, secure symlink mapping works on your plan, or use a VPS with a configurable document root. If this mapping cannot be established, stop; do not copy the application into `public_html` or use a PHP bridge exposing the source.
- Confirm both the web PHP runtime and the SSH PHP binary use PHP 8.3.36 or a later 8.3 patch. The server's PHP binary runs Composer's real platform check. The build runner must have PHP 8.3, Composer, and a Vite-compatible Node version; a missing or outdated runner tool fails the workflow.
- Configure HTTPS, a production database, restricted database credentials, reliable backups, and monitoring. Rehearse a database restore. Review the migrations for compatibility with the currently running release; migrations run **before** the symlink switch and can affect live requests. For incompatible changes, plan a maintenance window and a coordinated deployment instead of relying on the switch alone.

## Server setup

Choose a dedicated SSH account with the minimum permissions supported by your plan and restrict GitHub deployment-key access where possible. Protect the GitHub repository, its default branch, workflow changes and the `production` environment with appropriate reviewers and access controls. Use the same ownership model for SSH and the web PHP process, or arrange carefully restricted group permissions with the host. Releases are created with a restrictive umask; the web process must be able to read each release and `.env`, and write shared storage and each release's `bootstrap/cache`. Do not make them world-readable or world-writable.

The following is an example, not a Hostinger-specific path. Substitute your actual absolute path, PHP and Composer paths. The deploy path and binaries must contain only letters, digits, `/`, `.`, `_` and `-`, with no spaces or `..`. Keep the whole deploy directory outside every web-served directory:

```bash
DEPLOY_PATH=/home/ACCOUNT/tugma
PHP_BIN=/absolute/path/to/php83
mkdir -p "$DEPLOY_PATH/shared/storage" "$DEPLOY_PATH/releases" "$DEPLOY_PATH/incoming"
chmod 700 "$DEPLOY_PATH/shared"
cp /path/to/your/prepared-production-env "$DEPLOY_PATH/shared/.env"
chmod 600 "$DEPLOY_PATH/shared/.env"
"$PHP_BIN" -v
```

Set the domain's document root to `/home/ACCOUNT/tugma/current/public` using the host's supported configuration. Confirm the web server follows this symlink and that neither the deployment root nor `shared` is web-accessible. Provision the web-server mapping before activation and test it; the first deployment creates `current`. If your web process uses a different account, arrange secure group access instead of the example owner-only permissions and verify both `.env` and new release directories remain inaccessible to unrelated users. Do not remove or replace an existing `public_html` without backing it up and confirming your host supports the configuration.

Prepare `.env` privately on the server. At minimum configure `APP_NAME`, `APP_ENV=production`, `APP_DEBUG=false`, an existing stable `APP_KEY`, `APP_URL=https://your-domain`, the actual database credentials, and production mail settings. Set `SESSION_SECURE_COOKIE=true`, and use appropriate session, cache, queue and filesystem drivers. If generating the initial key for a **new** installation, run `php artisan key:generate --show` in a trusted environment and place the result only in the server `.env`; keep the same key across releases and backups. Never generate a new key during deployment or rotate it without planning for encrypted data and sessions. Do not commit `.env` or put it in GitHub variables.

Database-backed sessions/cache/queues require their migrations. Configure a persistent queue worker and restart it after activation if using asynchronous queues; schedule `php /home/ACCOUNT/tugma/current/artisan schedule:run` every minute only if scheduled tasks are needed. Confirm your Hostinger plan supports long-running workers, or choose a supported queue strategy. Workers can continue executing old code until restarted. Protect uploads, log files and backups, and decide retention separately.

## GitHub environment

Create an environment named `production`. Configure required reviewers and deployment-branch restrictions where available. The workflow accepts only the repository's default branch and requires the backup confirmation checkbox. Environment protection settings depend on your GitHub plan; if approvals are unavailable, restrict who can dispatch workflows.

Set these **environment variables**:

| Name | Value |
| --- | --- |
| `SSH_HOST` | Hostinger SSH hostname or IPv4 address, without protocol |
| `SSH_PORT` | SSH port from your Hostinger account |
| `SSH_USER` | SSH account username |
| `DEPLOY_PATH` | Absolute private deployment directory |
| `PHP_BIN` | Absolute path to the server's PHP 8.3 CLI executable |
| `COMPOSER_BIN` | Absolute path to a Composer PHP script or phar; it is executed by `PHP_BIN` |

Set these **environment secrets**:

| Name | Value |
| --- | --- |
| `SSH_PRIVATE_KEY` | Dedicated deployment private key, authorized on the server; no passphrase because the workflow is noninteractive |
| `SSH_KNOWN_HOSTS` | Complete OpenSSH known-hosts line for the exact hostname and port, independently verified against the host's published or support-confirmed fingerprint |

To prepare the known-hosts entry, collect the host public key from a trusted source, compare its fingerprint with the provider over an independent channel, and save the complete verified known-hosts line in the secret. For a non-default port, the line uses `[hostname]:port` as the host field. A key obtained by `ssh-keyscan` alone is **not** verified. Confirm your host allows connections from GitHub-hosted runner addresses; the workflow cannot work with an SSH firewall that only allows a fixed IP unless you provide a suitably secured runner/network arrangement.

## Deploy and verify

1. Commit and merge the reviewed changes to the default branch. Confirm the commit passed your independent tests and audit its pending migrations.
2. Take and verify a restorable production database backup and back up shared storage and the existing `.env`. Check free disk space. Confirm the live database is safe for the migrations.
3. In GitHub Actions, run **Deploy to Hostinger** on the default branch, check the backup confirmation, and approve the `production` environment if configured. Composer's production dependency audit must pass. Installation uses the lockfiles; package discovery and Filament assets are generated on the server after the shared environment is linked.
4. Verify the run succeeded, HTTPS and both application panels load, authentication works, uploads remain available, and critical indicator and dashboard pages work. Inspect server and Laravel logs without publishing secrets. Restart any workers using your host's supported process manager.

The workflow leaves releases and uploaded archives in the private deployment directory for inspection and rollback. Monitor disk usage and remove only verified obsolete releases and archives manually, never `current` or `shared`. If the job is interrupted, inspect the server and remove a stale `.deploy-lock` directory **only after confirming no deployment is running**. Failed releases remain for diagnosis. A second deployment cannot activate while the lock exists.

## Rollback

If the database remains compatible with an earlier release, an operator can switch `current` back to a known-good release with the host's supported atomic symlink procedure, then restart workers and verify the site. Keep the existing shared `.env` and storage. Switching code back does **not** reverse migrations; if the schema or data is incompatible, follow your tested database recovery plan instead. Database restores can discard changes since the backup, so coordinate maintenance and recovery with the data owner. Never run `migrate:fresh` or seed production as a rollback.
