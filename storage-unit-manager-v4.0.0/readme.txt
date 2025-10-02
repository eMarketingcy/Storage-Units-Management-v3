=== Storage Unit Manager ===
Contributors: selfstorage-cyprus
Tags: storage, units, management, rental, occupancy, email, notifications, vat, invoices, stripe, payments, customers
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 4.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Comprehensive storage unit management for WordPress: units, pallets, contacts, invoicing (with VAT), Stripe payments, email automation, and an optional Customers module (admin + frontend).

== Description ==

**Storage Unit Manager** helps self-storage businesses manage units and pallets from WordPress.

### Core Features
- **Unit & Pallet Management** — names (A1, A2…), size, m², pricing, custom website name.
- **Occupancy & Periods** — occupied/available, rental dates, payment status.
- **Contacts** — primary + secondary contact info (phone, WhatsApp, email).
- **Billing** — month calculator, totals, **VAT support** (enable/disable, rate, company VAT/Tax ID).
- **PDF Invoices** — branded PDFs with subtotal, VAT line, grand total. TCPDF by default, **Dompdf optional** (falls back automatically).
- **Stripe Payments** — pay invoices online (units & pallets) via Stripe Elements. Signed links with token rotation.
- **Email Automation** — 15-day and 5-day reminders; admin + customer notifications.
- **Frontend (staff) App** — role-based access; filter/search; mobile ready.
- **Bulk Create** — quickly add A1–A50 etc.
- **Customers Module (optional)** — admin screen + private staff frontend via `[sum_customers_frontend_cssc]`.

### REST API for Invoices
- **Endpoint:** `POST /wp-json/sum/v1/invoice`
- **Body:** `customer_id` (int)
- **Auth:** send `X-WP-Nonce` (generated via `wp_create_nonce('wp_rest')`); permission callback is configurable in the module.
- **Notes:** Frontend “Generate Full Invoice” button calls this endpoint.

== Installation ==

1. Upload to `/wp-content/plugins/storage-unit-manager` and activate.
2. Go to **Storage Units → Settings** to configure roles, email, **VAT**, Stripe, etc.
3. Click **Create Frontend Page** if needed.  
4. (Optional) Enable the **Customers** module; add the customers page with `[sum_customers_frontend_cssc]`.

== Frequently Asked Questions ==

= Can I run the plugin without the Customers module? =
Yes. It is self-contained and optional.

= Where is the Customers admin screen? =
Under **Storage Units → Customers** (or a “Customers” top-level, depending on your admin setup). Slug: `sum_customers_cssc`.

= Do invoices include VAT? =
Yes—subtotal, VAT line, and grand total are shown on both the payment page and PDFs.

= What if Dompdf isn’t installed? =
The plugin uses **TCPDF** by default. If Dompdf is present, it will be used; otherwise it falls back automatically.

== Screenshots ==

1. Admin dashboard and unit cards
2. Bulk add units
3. Staff frontend app
4. Email settings
5. **VAT & Identity settings**
6. **PDF with VAT breakdown**
7. **Stripe payment UI**
8. **Customers admin list (current/past rentals)**
9. **Customers frontend (staff view)**

== Changelog ==

= 4.0.0 – 2025-10-01 =
* **Fixed:** Payment history now correctly displays the number of months paid when customers pay in advance (previously showed "1 month" regardless of actual payment period).
* **Fixed:** Advance payment period extensions now work correctly - when paying multiple months in advance, rental periods are extended by the correct number of months from their current end date.
* **Fixed:** Payment history detail modal now shows accurate "Paid Until" dates for each rental item after advance payments are processed.
* **Improved:** Enhanced `complete_payment()` method in payment history to properly track and store advance payment months.
* **Improved:** Better logging and debugging for advance payment processing to track period extensions.
* **Technical:** Updated payment handler to pass payment months parameter through the complete payment workflow.
* **Technical:** Period extension calculations now correctly use the advance payment months instead of defaulting to single month extensions.

= 3.1.3 – 2025-09-28 =
* **Fixed:** PDF generation reliability (writes to `/uploads/invoices`, DOMPDF primary + TCPDF fallback, HTML dump on failure).
* **Fixed:** Company header now reads from `{prefix}_storage_settings` (name, address with line breaks, phone, email, logo).
* **Changed:** Consolidated invoices correctly aggregate **all UNPAID** units + pallets for the target customer.
* **Changed:** **Strict identity matching** — uses `customer_id` (if column exists), else **`primary_contact_email`** (exact, case-insensitive). **Secondary contacts and name-based matches are not used** to prevent cross-customer bleed.
* **Improved:** Price detection — use dedicated price column when available; otherwise fall back to the **last numeric value** in the row (supports legacy schemas).
* **Improved:** Defensive SQL preparation and optional debug logging (respects `WP_DEBUG`).

== Upgrade Notice ==

= 4.0.0 =
Critical bug fixes for advance payment processing! This release ensures that when customers pay for multiple months in advance, the payment history correctly displays the payment period and rental end dates are properly extended. All advance payments made after this update will automatically extend rental periods by the correct number of months. Highly recommended update for all users accepting advance payments.

= 3.1.3 =
This release tightens identity matching for invoices to stop cross-customer bleed. If you previously relied on **secondary contact** emails or name-only matching, move the billing email to **`primary_contact_email`** on the unit/pallet rows (or ensure a correct `customer_id` column is present). PDFs are now more reliable and include correct company details from plugin settings.

= 3.1.2 =
* **Feature:** Invoice generation now runs through a **stable REST API endpoint** (`/sum/v1/invoice`) for reliability in modern frontends.
* **Feature:** Added **“Generate Full Invoice”** button to the Customer Details Modal, wired to the new REST endpoint.
* **Feature:** Stabilized `get_unpaid_invoices_for_customer` to prevent repeated fatals and edge-case nulls.
* **Refactor:** Extracted invoice logic into **`class-customer-invoice-handler.php`** and cleaned up `module.php` for clearer separation of concerns.
* **Fix:** Resolved **Uncaught ReferenceError** in `customers-frontend.js` by properly defining the `$error` scope and removing stray placeholders.
* **Fix:** Applied structural SQL formatting fixes to eliminate PHP warnings from **`dbDelta()`** during schema updates.
* **Known Issue (unresolved):** ❌ *“PDF file was not created at expected path …/uploads/invoices/…pdf.”*  
  Some environments produce an **empty (0-byte) PDF** on first run. See **Troubleshooting → PDF output is empty** below for mitigations (writable temp/font caches under `/uploads/`, correct Dompdf autoloader path, and automatic TCPDF fallback).

= 3.0.0 =
* **New Customers module (optional):**
  * Admin list with search, stats, **current/past** rentals (units & pallets).
  * Smart **sync** from Units/Pallets with de-duplication (email/phone/name).
  * **Daily auto-sync** via WP-Cron.
  * Frontend customers page via `[sum_customers_frontend_cssc]`.
  * Namespaced slugs & actions/endpoints with `_cssc` suffix to avoid conflicts.
* Payment & invoice pages show **subtotal, VAT, total** consistently.
* Dompdf integration path hardened with graceful fallback to TCPDF/HTML.
* Many small polish items and safer JS for Stripe Elements.

= 2.2.1 =
* **Bug fixes & stability:**
  * Fixed **VAT settings** save path and nonce handling on settings page.
  * Restored **payment button** behavior and null-safe event binding.
  * Resolved **Customers menu** visibility and 404 slug edge cases.
  * Improved **sync** accuracy (prevents duplicates; picks up new records reliably).
  * Better **error messaging** for the Dompdf downloader/installer and permissions.
  * Minor CSS fixes in PDFs; added company VAT/Tax ID to headers.

= 2.2.0 =
* Introduced full **VAT** system + company VAT/Tax ID.
* Detailed **PDF invoices** (subtotal/VAT/total).
* **Stripe** payments (units & pallets) with secure tokens + rotation.
* Pallet PDF generator; improved branding/styling.
* Safer JavaScript and robust nonce checks.

= 2.0.0 =
* Staff frontend, role-based access, email reminders (15/5 days), bulk operations, filters, stats.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 3.1.2 =
This release moves invoice generation to a **REST endpoint**, adds a **Generate Full Invoice** button, and fixes JS/dbDelta warnings. If PDFs come out **empty**, review the Troubleshooting notes below (writable caches, Dompdf autoloader path, fallback to TCPDF).

= 3.0.0 =
Major release adding the optional **Customers** module (admin + frontend) with sync and daily auto-sync. Review your roles/permissions and create the customers page with `[sum_customers_frontend_cssc]` if needed.

= 2.2.1 =
Recommended stability update—VAT saving, payment button, customers slug, and sync fixes.

= 2.2.0 =
Adds VAT, invoicing improvements, and Stripe payments. Backup before upgrading.

== Troubleshooting ==

= PDF output is empty (0 bytes) or “was not created at expected path” =
- Ensure these directories are **writable** by PHP and exist (the plugin will try to create them):  
  - `/wp-content/uploads/invoices/`  
  - `/wp-content/uploads/dompdf-temp/`  
  - `/wp-content/uploads/dompdf-fonts/`
- Confirm the **Dompdf autoloader path** is correct if you use a bundled library (constant or setting).  
- Use a **Unicode-capable font** (e.g., DejaVu Sans) if your invoices include Greek text.  
- On restrictive hosts, make sure Dompdf’s `chroot`, `tempDir`, and `fontCache` are **inside `/uploads/`**.  
- The plugin will **fallback to TCPDF** automatically if Dompdf cannot produce a non-empty file.  
- Check `wp-content/debug.log` for Dompdf/TCPDF errors and inspect the last rendered HTML (if the plugin writes a debug HTML file under `/uploads/invoices/__last_invoice.html`).

== Support ==

For support, contact eMarketing Cyprus or visit selfstorage.cy.
