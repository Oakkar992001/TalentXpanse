# Backups and restore drills

TalentXpanse stores account and marketplace records in MySQL and user files under Laravel storage. Back up both independently and store copies outside the application server.

## Database backup

`backend/deploy/scripts/backup-mysql.sh` creates a compressed, GPG-encrypted MySQL backup and removes expired local copies. It intentionally requires a public-key recipient rather than accepting an unencrypted backup.

On the server, install `mysqldump` and GnuPG, then configure a root-owned cron entry similar to:

```cron
0 2 * * * APP_ROOT=/var/www/talentxpanse/current/backend BACKUP_DIRECTORY=/var/backups/talentxpanse BACKUP_GPG_RECIPIENT=operations@example.com BACKUP_RETENTION_DAYS=30 /var/www/talentxpanse/current/backend/deploy/scripts/backup-mysql.sh >> /var/log/talentxpanse-backup.log 2>&1
```

Synchronize the resulting `.gpg` files to a separate encrypted object-storage bucket with restricted access. Do not keep the only backup on the web server.

## Upload storage

Back up `backend/storage/app` independently. If you move uploads to private object storage, enable the provider’s versioning and lifecycle policy, then include that bucket in the quarterly restore drill.

## Restore drill

Perform restores in a separate staging database only. Never test a backup by restoring it over production.

1. Create an empty staging database.
2. Decrypt and restore a selected backup with the private key held by the operations team.
3. Restore the matching uploads snapshot.
4. Run `php artisan marketplace:operations-check --strict` and log in with a staging-only account.
5. Record the backup date, restore duration, data checked, operator, and result.

Run and document this drill at least quarterly and after any major database or storage change.

## Windows / XAMPP recovery protection

After a local recovery, do not overwrite the only working copy of `C:\xampp\mysql\data`. Keep a dated snapshot and verify a fresh export can be restored to a separate database.

`backend/deploy/scripts/backup-mysql.ps1` supports a daily Windows Task Scheduler job for XAMPP or another local MySQL/MariaDB server. Create a MySQL defaults file outside the repository (for example `C:\secure\talentxpanse-backup.cnf`) with restricted permissions:

```ini
[client]
host=127.0.0.1
port=3307
user=talentxpanse_backup
password=use-a-dedicated-long-random-password
```

Create a dedicated database user with read/lock/trigger/event access appropriate for `mysqldump`; do not run scheduled backups as the application database owner where avoidable. Then schedule PowerShell daily:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File D:\Project\TalentXpanse\backend\deploy\scripts\backup-mysql.ps1 -DefaultsFile C:\secure\talentxpanse-backup.cnf -Database talentxpanse -BackupDirectory D:\TalentXpanseBackups -GpgRecipient operations@example.com
```

Use `-GpgRecipient` after importing the operations public key so the retained copies are encrypted. Store a second encrypted copy outside the local PC. Run a restore drill after XAMPP recovery, then monthly while the project is in beta.
