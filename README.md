# MoWallet

A single-owner finance record application for multiple businesses and personal finances. Built on the existing PHP starter with MySQL, plain CSS, and JavaScript. No Composer or Node build is required to run the application.

## Requirements

- PHP 8.2+ with PDO MySQL, BCMath, mbstring, GD (PNG/JPEG/WebP), and sessions.
- MySQL 8+ or MariaDB 10.4+ with InnoDB.
- Apache with mod_rewrite (XAMPP works), or the PHP development server.

## Start locally

1. Start Apache and MySQL in XAMPP.
2. Copy `.env.example` to `.env` if `.env` does not exist. Set your database credentials and use a dedicated `DB_DATABASE=mowallet`. Leave `APP_URL` empty for local XAMPP use.
3. Run `php bin/console migrate` from this folder. This creates the configured database if needed and applies pending migrations; it does not reset existing records.
4. Open **http://localhost/mowallet/public/** and create your owner account. There is no default password. The setup route closes after the first owner is created, and the database allows only one owner.

Alternatively:

```sh
php -S localhost:8000 -t public public/router.php
```

Then open http://localhost:8000. The local database has already been migrated in this workspace. No test transactions or test owner are placed in it.

## Everyday use

1. Add each business under **Businesses & personal**. A **Personal** record is included to start.
2. Create **Account books** for cash, bank accounts, or mobile wallets. A **Cash** book is included. Enter each book's balance before the first recorded transaction as its opening balance. Books can hold transactions from multiple businesses; business attribution belongs to each income/expense record.
3. Adjust the shared **Categories**. A category can be income, expense, or both. Names and notes can be entered in English or Myanmar. User-entered names are kept exactly as entered when the interface language changes.
4. Add **Transactions** for received income and paid expenses. Amounts are positive MMK values with up to three decimal places, up to 999,999,999,999.999 per record. Expenses reduce the selected book. Transfers move money between two books and preserve their combined balance.
5. Add **People**, then record **Payables & receivables** for unpaid sales, bills, and other amounts due. Choose the business/personal record and the income/expense category that will apply when paid.
6. Open a balance and record a partial or full payment. This creates its cash transaction automatically. Do not separately enter the same payment in Transactions. **Undo payment** removes the cash entry and reopens that amount. A payment cannot exceed the unpaid balance.
7. Review the dashboard and **Reports & analysis** using date, business, book, category, and type filters. Transactions also support notes/person search and pagination. CSV export follows the selected filters, includes a UTF-8 BOM for Myanmar text, and neutralizes spreadsheet formulas in text fields.

## Import phone contacts

Open **People → Import contacts**. You can select contacts directly on a supported phone browser, or upload a **vCard (`.vcf` or `.vcard`)** file exported/shared by your phone's Contacts app. Transfer that file to your computer if you are using MoWallet on desktop. Review the preview, uncheck anyone you do not want to add, then choose **Import selected contacts**. No real contacts are imported by application setup or tests.

Direct selection uses the browser's Contact Picker API and requests only names and telephone numbers after you press the button. It is available in supported phone browsers such as Chrome on Android, in a secure top-level page (HTTPS for remote access). Desktop Firefox and unsupported browsers use the file method. A phone's `localhost` refers to the phone itself, so use the computer's reachable server address when accessing MoWallet from another device; plain HTTP over a LAN does not enable the native picker. File upload works with the current local XAMPP setup.

Each batch supports **500 contacts / 2 MB**. Extra phone numbers go into Notes. Email addresses, postal addresses, photos, and the original contact notes are ignored. Contacts with matching case-insensitive names and normalized primary telephone numbers are skipped; matching treats Myanmar digits and common `09` / `+959` / `00959` formats consistently. Existing people, notes, and financial records are not overwritten. Entries over the field limits are skipped with a count. You can edit imported people afterward.

Preview drafts contain only parsed names/numbers, are kept in your authenticated session for up to 30 minutes (up to five drafts), and are removed on successful import, explicit discard, or logout. Uploaded files are not retained. Row selections survive reloads/language switching in the current tab; the browser stores only the selected row indices, without contact names or phone numbers. This is a one-time import, not ongoing phone contact synchronization.

## How amounts are calculated

Amounts are stored in MySQL `DECIMAL(22,3)` columns and calculated with PHP BCMath decimal strings so fractions remain exact. Forms reject more than three decimal places instead of silently rounding them. All monetary displays and CSV amounts include three decimal places. Run `php bin/console migrate` when updating an existing installation; existing whole amounts are preserved as `.000`. Rolling back the decimal migration retains these columns to prevent losing fractional values.

- This is a **cash basis record app**: unpaid balances do not count as income or expenses until their payments are recorded. It does not implement invoices, inventory valuation, accrual accounting, tax reporting, loan amortization, or a double-entry general ledger.
- Every balance payment becomes income for a receivable or an expense for a payable. There is no separate loan-principal classification in this version.
- Income minus expenses is the reported net income. Opening balances and transfers are excluded from this calculation.
- Current book balance = opening balance + received income − paid expenses − outgoing transfers + incoming transfers, through today's date in **Asia/Yangon**. Future-dated entries enter the current book balance on their record date. A negative calculated balance is allowed.
- Unpaid balances show all recorded payments, including future-dated payments. Use actual payment dates when recording settlements.
- Debt status is calculated from remaining balance and due date: unpaid, partial, overdue, or settled. Dashboard unpaid totals cover all dates for the selected business, independently of the cash-flow date filter.
- Edits and deletions recalculate balances from the records. Records referenced by transactions or balances cannot be deleted. Categories cannot change to a type incompatible with existing entries.
- After payments exist, a balance's person, business, direction, and category cannot change unless those payments are undone. The original amount cannot be reduced below the amount already paid.

## Language and appearance

The header switches between **English** and **Myanmar**. Settings provides light/dark themes and compact/comfortable density. Appearance preferences are stored in the current browser; language is stored in the session. Mobile navigation is an accessible drawer, and wide tables scroll inside their panels.

The logo-inspired design uses emerald actions, navy navigation and headings, mint-tinted surfaces, aligned financial figures, and compact shared controls across every page. Dark mode pairs navy surfaces with mint accents. Compact is the default for this design; an older saved density is reset once on the first visit, and you can still choose Comfortable in Settings. On phones, filters fold into a disclosure and open into a two-column grid; desktop filter fields scroll independently while Apply and Reset remain visible. Transaction, directory, unpaid-balance, and payment-history lists prioritize names, amounts, and status in screen-fitting rows. Detailed report tables and contact previews retain contained horizontal scrolling. The navigation drawer starts with links and has no repeated brand/profile header. Stylesheets and scripts have modification-based version URLs so browser caches pick up design changes.

Inter and Noto Sans Myanmar are requested from Google Fonts. Offline, the system/Myanmar font fallback is used; install Noto Sans Myanmar locally if necessary.

## Logo and favicon

In **Settings → Logo & favicon**, choose an app logo, a browser favicon, or both, preview them, then press **Save branding**. Square PNG, JPG, and WebP images are accepted, up to 2 MB per image and 16–2048 pixels per side. Transparent PNG is suitable for logos. The app preserves aspect ratio on a transparent square canvas and stores a 256 px PNG logo and a 64 px PNG favicon. SVG and ICO uploads are not accepted; upload a PNG version instead.

The logo appears in the top bar and on the login/setup screen. The favicon appears on browser tabs across the application. Use **Reset logo** or **Reset favicon** to restore either built-in asset without resetting the other. Each save/reset uses a new versioned image URL so refreshed pages load the latest image rather than an older browser-cached version.

Branding images are stored in the `app_branding` database table and included in full database backups. No public upload directory is needed. Image writes require the owner session and CSRF protection, while the validated image endpoints are public so branding works before login. All selected images are validated and re-encoded before any saved image changes.

## Account and deployment

Passwords are hashed, writes use CSRF protection and prepared SQL, financial forms reject repeated submissions, and login is limited to 10 attempts per IP per 15-minute window. Sessions expire after two hours of inactivity. Change the owner password in Settings; this invalidates other signed-in sessions. There is no public registration or email password-reset service.

For deployment, point the web document root to `public/`, use HTTPS, set `APP_DEBUG=false`, and configure a dedicated database user with appropriate permissions. Complete initial owner setup before exposing a new installation. Keep `.env`, database backups, and application source outside the served public directory. The retained `starter/` folder is the original generic template and is not used by MoWallet.

## Backup

CSV export is a transaction export, **not a full backup**. Use phpMyAdmin's database export or `mysqldump` to back up the entire `mowallet` database, including people, debts, categories, books, owner credentials, and migrations. Store backups outside the web root and restore into a separate database before switching `.env` to it. Keep `.env` separately and protect it because it contains database credentials.

Never run `migrate:fresh` on a database with records you want to keep: it drops every table. Use `php bin/console migrate` for routine schema updates.

## Verification

```sh
php tests/run.php
```

The suite creates a randomly named `mowallet_test_*` database, tests financial calculations and authenticated HTTP flows on an isolated PHP server, and removes that database afterward. It requires PHP cURL and permission to create/drop its temporary database. It never resets the application database.

Optional browser checks require Playwright with Chromium installed:

```sh
node tests/browser.cjs
```

Set `PLAYWRIGHT_MODULE` to an existing Playwright/Playwright Core module directory if it is not available by package name. Set `PHP_BINARY` if `php` is not on PATH. This script runs the isolated suite, checks actual browser flows at 1440, 1280, 1024, and 390 px, checks both languages/themes and density controls, writes screenshots to ignored `storage/testing/`, then removes the test database. `php tests/run.php --keep` retains its isolated database for manual debugging; remove it afterward with `php tests/cleanup.php <printed-test-database>`.
