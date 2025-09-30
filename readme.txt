=== Storage Unit Manager ===
Contributors: selfstorage-cyprus
Tags: storage, units, management, rental, occupancy, email, notifications, vat, invoices, stripe, payments, customers
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 3.1.3c
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Comprehensive storage management for WordPress: units, pallets, contacts, invoicing (with VAT), Stripe payments, email automation, and a powerful Customers module (admin + frontend).

== Description ==

[cite_start]**Storage Unit Manager** is a complete solution for self-storage businesses to manage their inventory of storage units and pallets directly from the WordPress dashboard. [cite: 2]

[cite_start]It features a secure, role-based frontend application for staff, robust billing and invoicing with VAT support, and seamless online payments via Stripe. [cite: 6, 8]

### Core Features
- [cite_start]**Unit & Pallet Management** — Define names (A1, A2…), size, square meters, pricing, and custom identifiers. [cite: 3]
- [cite_start]**Occupancy & Periods** — Track occupied/available status, manage rental dates, and monitor payment status (paid, unpaid, overdue). [cite: 4]
- [cite_start]**Contact Management** — Store primary and secondary contact information, including phone, WhatsApp, and email addresses. [cite: 5]
- [cite_start]**Advanced Billing** — A precise billing calculator determines occupied months for accurate invoicing, with full support for VAT (configurable rate, company Tax ID). [cite: 6]
- [cite_start]**Professional PDF Invoices** — Automatically generate branded PDF invoices showing subtotal, a dedicated VAT line item, and the grand total. [cite: 7] [cite_start]The system prioritizes Dompdf for generation and includes a reliable fallback to TCPDF. [cite: 7, 18]
- [cite_start]**Stripe Payments** — Allow customers to pay invoices online for both units and pallets using a secure Stripe Elements interface. [cite: 8]
- [cite_start]**Email Automation** — Send automated 15-day and 5-day rental expiration reminders to customers and admins. [cite: 9]
- [cite_start]**Staff Frontend Application** — A mobile-ready frontend accessible via shortcode provides role-based access for staff to manage inventory on the go. [cite: 10]
- [cite_start]**Customers Module (optional)** — A dedicated module for centralized customer management, including an admin screen and a private staff frontend (`[sum_customers_frontend_cssc]`). [cite: 14]

### REST API for Invoices
- [cite_start]**Endpoint:** `POST /wp-json/sum/v1/invoice` [cite: 11]
- [cite_start]**Body:** `{ "customer_id": 123 }` [cite: 11]
- [cite_start]**Authentication:** Requires a valid `X-WP-Nonce` header. [cite: 11]
- [cite_start]**Usage:** This stable endpoint is used by the frontend "Generate Full Invoice" button to ensure reliability. [cite: 12, 30]

== Installation ==

1.  [cite_start]Upload the `storage-unit-manager` folder to your `/wp-content/plugins/` directory and activate the plugin through the 'Plugins' menu in WordPress. [cite: 13]
2.  [cite_start]Navigate to **Storage Units → Settings** to configure user roles, email templates, VAT settings, and Stripe API keys. [cite: 13]
3.  [cite_start]Use the **Create Frontend Page** buttons in the settings to automatically generate the necessary pages for your staff. [cite: 13]
4.  [cite_start](Optional) Enable the **Customers** module and add the `[sum_customers_frontend_cssc]` shortcode to a new page to deploy the customer management frontend. [cite: 14]

== Changelog ==

= 3.1.3c - 2025-09-30 =
* **Fix (Unit Frontend):** Refactored the Storage Unit card view to align with the modern Pallet and Customer card design, resolving major UI inconsistencies and improving usability. A single, unified stylesheet now powers all frontend modules.
* **Fix (Customer Frontend):** Resolved a data persistence issue where `phone` and `whatsapp` numbers were not saving correctly when editing a customer from the frontend modal.
* **Fix (Customer Frontend):** Corrected a data fetching bug where `phone` and `whatsapp` numbers were not being displayed on customer cards, even when present in the database.
* **Fix (Customer PDF):** Resolved an issue in the consolidated Customer Invoice PDF where the "Qty (Months)" column was not being populated correctly.
* **Improvement (Customer PDF):** The consolidated Customer Invoice PDF generator now uses the advanced template, including full VAT calculations, billing period logic, and professional branding, mirroring the single Pallet invoice functionality.
* **Improvement (Customer Frontend):** Added a visual indicator (animated red dot) on customer cards to provide an at-a-glance notification for accounts with unpaid rentals.
* **Tweak (Backend):** Hardened data sanitization and validation across the Unit, Pallet, and Customer admin screens to improve security and data integrity.

= 3.1.3 – 2025-09-28 =
* [cite_start]**Fixed:** PDF generation reliability (writes to `/uploads/invoices`, DOMPDF primary + TCPDF fallback). [cite: 20]
* [cite_start]**Fixed:** Company header details in PDFs now correctly read from plugin settings. [cite: 20]
* [cite_start]**Changed:** Consolidated invoices correctly aggregate all UNPAID units + pallets for the target customer. [cite: 21]
* [cite_start]**Changed:** Tightened identity matching to use `customer_id` or `primary_contact_email` exclusively, preventing cross-customer data bleed. [cite: 22, 23]

= 3.1.2 =
* [cite_start]**Feature:** Migrated invoice generation to a stable REST API endpoint (`/sum/v1/invoice`) for improved reliability. [cite: 30]
* [cite_start]**Feature:** Added "Generate Full Invoice" button to the Customer Details modal. [cite: 31]
* [cite_start]**Refactor:** Extracted invoice logic into `class-customer-invoice-handler.php` for better separation of concerns. [cite: 33]
* [cite_start]**Fix:** Resolved JavaScript ReferenceError in `customers-frontend.js`. [cite: 34]
* [cite_start]**Fix:** Corrected SQL formatting to prevent `dbDelta()` warnings during schema updates. [cite: 35]

(Older changelog entries remain the same)

== Upgrade Notice ==

= 3.1.3 – 2025-09-28 =
* **Fixed:** PDF generation reliability (writes to `/uploads/invoices`, DOMPDF primary + TCPDF fallback, HTML dump on failure).
* **Fixed:** Company header now reads from `{prefix}_storage_settings` (name, address with line breaks, phone, email, logo).
* **Changed:** Consolidated invoices correctly aggregate **all UNPAID** units + pallets for the target customer.
* **Changed:** **Strict identity matching** — uses `customer_id` (if column exists), else **`primary_contact_email`** (exact, case-insensitive). **Secondary contacts and name-based matches are not used** to prevent cross-customer bleed.
* **Improved:** Price detection — use dedicated price column when available; otherwise fall back to the **last numeric value** in the row (supports legacy schemas).
* **Improved:** Defensive SQL preparation and optional debug logging (respects `WP_DEBUG`).

== Upgrade Notice ==

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
