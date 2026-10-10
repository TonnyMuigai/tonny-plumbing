# TONNY Plumbing & Sanitation — Starter MVP

A PHP 8.2+ / MySQL starter for TONNY's sewerage, exhauster and plumbing-supplies business.

## Included in this stage
- Responsive public homepage and service-booking form
- Booking saved in MySQL using PDO prepared statements
- WhatsApp handoff to `+254723777601` with a prefilled booking message
- Admin login, dashboard, booking list and status updates
- Database schema for products, customers, bookings, quotes, invoices, payments and notification logs
- CSRF protection, password hashing, session hardening, output escaping
- M-Pesa integration placeholder (do not accept real payments until Safaricom Daraja credentials and callback verification are configured)

## Requirements
- PHP 8.2+ with PDO MySQL
- MySQL 8+ or MariaDB
- Composer (optional, for PDF generation in the next stage)
- Local server such as XAMPP, Laragon, MAMP, or PHP's built-in server

## Quick start (XAMPP example)
1. Copy this folder into `htdocs/tonny-plumbing`.
2. Create a MySQL database and import `database/schema.sql`.
3. Copy `.env.example` to `.env` and set database credentials. This starter reads `.env` itself; keep it outside the public web root in production.
4. Set `APP_URL` to your local URL, e.g. `http://localhost/tonny-plumbing/public`.
5. Generate an admin password hash:
   ```bash
   php -r "echo password_hash('ChangeThisPassword!', PASSWORD_DEFAULT), PHP_EOL;"
   ```
6. Insert the admin user (replace the hash):
   ```sql
   INSERT INTO admin_users (name,email,password_hash,role)
   VALUES ('TONNY Admin','admin@tonny.local','PASTE_HASH_HERE','admin');
   ```
7. Visit `http://localhost/tonny-plumbing/public/`.

### Using PHP's built-in server
From the project root:
```bash
php -S 127.0.0.1:8000 -t public
```
Set `APP_URL=http://127.0.0.1:8000`, then visit `http://127.0.0.1:8000`.

## Admin
Visit `/admin/login.php` (relative to the public URL). Never use a default password in production. Create a unique admin password and remove any test users.

## Important deployment notes
- Put the project outside the public web root where possible; expose only `public/`.
- `.env` must never be publicly accessible. Configure your server to deny dotfiles.
- Use HTTPS in production.
- Add rate limiting / CAPTCHA to public forms before production.
- The WhatsApp button opens a chat; it does not send messages automatically. Background WhatsApp messages require the WhatsApp Business Platform or an authorized provider.
- M-Pesa is scaffolded only. Before collecting money, implement Daraja STK Push, validate callback signatures/metadata, use idempotency, and reconcile transactions.
- Tax rates and invoice numbering should be confirmed with your accountant and Kenyan requirements before production.
- PDF output is the next implementation stage; the database schema is ready for document records.
- Tailwind CDN is used for local prototyping. For production, compile Tailwind locally using the official CLI/build pipeline.

## Build stages
1. Foundation, schema, homepage, booking form, WhatsApp handoff, admin booking management (included).
2. Product catalogue/cart, inventory, customer accounts.
3. Quote builder, quote acceptance, invoice and receipt PDFs.
4. Safaricom Daraja M-Pesa, verified callbacks and payment reconciliation.
5. Email/WhatsApp notifications, audit logs, backups, tests and production deployment.
