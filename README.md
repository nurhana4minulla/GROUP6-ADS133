# Prime Pool Peninsula | ADS133 Group 6

The public website and staff operations system use vanilla PHP, PDO, MySQL or MariaDB, vendored Bootstrap CSS, and plain JavaScript. The included project photographs and logos are from the supplied Prime Pool Peninsula materials.

## Run the demo on Windows with XAMPP

1. Install XAMPP with PHP 8.2 or later and start **MySQL** in the XAMPP Control Panel. XAMPP bundles MariaDB. For a standalone MySQL server, use MySQL 8.0.16 or later. Its default local port is 3306. Apache can remain off when using the PHP server below.
2. Clone this repository and open a PowerShell window in the cloned folder.

   ```powershell
   git clone https://github.com/nurhana4minulla/GROUP6-ADS133.git
   cd GROUP6-ADS133
   ```

3. If MySQL uses a different username, password or port, copy `config.example.php` to `config.local.php` and edit the local copy. The default is the standard local XAMPP root account with no password. Setup needs permission to create the project database and apply schema upgrades. For a deployed instance, use a restricted database account for the running app. The local file is ignored by Git.
4. Create a local demo database with company content, future appointment slots, and two test accounts. Keep the passwords printed by setup. Run the server in this same PowerShell window so it uses the demo database.

   ```powershell
   $env:PRIME_POOL_DATABASE = 'prime_pool_ads_demo'
   C:\xampp\php\php.exe scripts/setup.php --demo-accounts
   ```

5. Start the application and open [the company website](http://127.0.0.1:8096/) or [staff sign-in](http://127.0.0.1:8096/admin.php).

   ```powershell
   C:\xampp\php\php.exe -d upload_max_filesize=8M -d post_max_size=10M -S 127.0.0.1:8096 -t public
   ```

Use the local PHP executable for your installation if it is in a different location. It needs PDO MySQL, mbstring and sessions. Live gateway delivery also uses cURL. For an Apache deployment, use `public/` as the document root. The demo keeps its UI assets in this checkout, so it works offline once the code is cloned and the local database is running.

### Test accounts

| Email | Access |
| --- | --- |
| `demo.owner@example.test` | Staff operations and the Accounts screen |
| `demo.staff@example.test` | Bookings, projects, content, and messaging tools |

The setup command prints a different random password for each account on each new database. Rerunning it keeps the existing passwords. If you forget one, open a second PowerShell window in the repo, select the same database with `$env:PRIME_POOL_DATABASE = 'prime_pool_ads_demo'`, and run `C:\xampp\php\php.exe scripts/reset.php demo.owner@example.test` or substitute the staff email. Enter the code on the staff sign-in page.

Already cloned the repo? Run `git pull` first. Use fictional clients and projects in the demo database. The demo account setup enables simulated messaging only. To apply schema upgrades to an existing regular installation, rerun setup with its owner email. To create a regular installation, use `php scripts/setup.php admin@example.test` with your regular database configuration and omit the demo flag.

### Presentation flow

- Open the company site, browse the projects and book an open appointment with a fictional client.
- Sign in as staff. Search for the generated form number. Approve the request, or decline it and show the time returning to public availability.
- Print from the request page or select multiple requests in the inbox. Choose **Save as PDF** in the browser’s print dialog.
- Record the meeting as **Inquiry only** or **Wants to proceed**. For a proceeding client, optionally schedule a site visit and create an ongoing project, then mark it completed.
- In **Website CMS**, create a portfolio item, upload a JPEG, PNG or WebP, publish it, then open the public portfolio. Unpublish it to show the public visibility rule.
- Run the worker below and refresh **Messages**. Instant messages will show **Simulated** in the demo. Future reminders stay queued until due.

```powershell
C:\xampp\php\php.exe scripts/messages.php
C:\xampp\php\php.exe tests/smoke.php
C:\xampp\php\php.exe tests/database.php
```

If you run the worker or database check from a second PowerShell window, set `PRIME_POOL_DATABASE` there to the same demo database before running those commands.

### Live messaging configuration

Demo mode is the default and records simulations without contacting a provider. Databases initialized with demo accounts stay in simulation mode. To enable real delivery, set `message_mode` to `live`, configure an HTTPS `message_gateway` URL and a bearer `message_token` in `config.local.php`. The worker sends JSON with `channel` (`sms` or `viber`), `to` (international mobile number), and `text`, plus an `Idempotency-Key` header. A successful HTTP response records gateway acceptance. Configure the gateway to translate this contract to your chosen provider and to honor the idempotency key. Schedule the worker every minute using Windows Task Scheduler or an equivalent scheduler. Reminders target 9:00 a.m. Philippine time on the preceding calendar day. A reminder expires at midnight before the scheduled day, so a missed run will not send a stale day-before notice. Preferred contact is recorded on the form; automated notifications use Viber if selected and SMS otherwise.

### PHP API

Base URL `http://127.0.0.1:8096/api.php`. Responses use `{"data": ...}` or `{"error": "..."}`. Call `GET ?resource=session` for a session cookie and CSRF token. Preserve the cookie and send the token in `X-CSRF-Token` for writes. Login with `POST ?resource=session` and JSON `email` and `password`, then use the new CSRF token returned after login.

| Resource | Operations |
| --- | --- |
| company, service, portfolio | Public GET for published items. Staff GET with `manage=1`, POST to create, PATCH with `id` to edit selected fields, DELETE with `id` to remove |
| availability | Public GET for open future slots |
| booking | Public POST with `slot_id`, `client_kind`, `client_name`, `mobile`, `contact_method`, `site_area`, `project_type`, `nature` and conditional fields |
| slots | Staff GET, POST or PATCH by `id` with `starts_at` (local `YYYY-MM-DDTHH:mm`) and `is_open` (`"1"` or `"0"`) |
| requests | Staff GET by `id` or list with `q`, `date`, `status`, `period`, `page`. PATCH by `id` with a decision or `action: "meeting"` |
| projects | Staff GET. POST/PATCH with the originating request `id`, `project_status`, `started_on` and optionally `completed_on`, `project_notes` |

For content writes send `title`, `slug`, `description`, `published` (`"1"` or `"0"`) and optional `category`, `location`, `project_date` and `sort_order`. Example public checks:

```powershell
curl.exe "http://127.0.0.1:8096/api.php?resource=portfolio"
curl.exe "http://127.0.0.1:8096/api.php?resource=availability"
```

For a full HTTP workflow check, use a disposable database, set `PRIME_POOL_TEST_PASSWORD` in your terminal and run `php tests/workflow.php http://127.0.0.1:8096 demo.owner@example.test`. The check writes fictional requests and projects.

The [database design](docs/database.md) describes the EER model, workload, keys, cardinalities, functional dependencies, and normalization through BCNF and 4NF. [Asset provenance](docs/assets.md) identifies the supplied company materials used in the site.
