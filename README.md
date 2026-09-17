# AVR (Clean COD Build)

This is a clean rewrite of the AVR site with Cash on Delivery only. It keeps the same features, admin, chatbot, and design direction, but simplifies the code and file structure.

Structure required by the request is followed. Database remains `avr_db` but payment tables and online payment code are removed.

How to run (XAMPP on Windows):

1. Create database and import `database/avr_db.sql` (or keep your existing `avr_db`).
2. Copy images:
   - Copy the folder `picture products` from your old project into `/avr/` (so images resolve via `/avr/picture products/...`).
   - Copy `logo.png` to `/avr/assets/images/logo.png` (we also keep the original at project root for fallback if desired).
   - Optionally copy background images from `picture products/bg/` and save one as `/avr/assets/images/bg.jpg`.
3. Set DB credentials in `config/db.php` if your port/user differ.
4. Visit `http://localhost/avr/index.php`.

Notes:
- Checkout uses COD only; no online payment endpoints are included.
- The chatbot is bundled and points to `/avr/orders.php?count=pending` for pending counts.
- Admin pages: `/avr/admin/` (login as an existing admin from your users table).
