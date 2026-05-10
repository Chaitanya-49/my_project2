# Clothing (Simple PHP Store)

A small PHP/MySQL clothing store demo. Built with plain PHP, minimal CSS, and a MySQL backend for products and users.

## Requirements

- PHP 7.4+ (or compatible)
- MySQL / MariaDB
- XAMPP (recommended for Windows)
- A web browser

## Quick Setup

1. Place the project folder in your web root (e.g., `C:\xampp\htdocs\clothing`).
2. Start Apache and MySQL (e.g., via XAMPP Control Panel).
3. Import the database: open `phpMyAdmin` and import `database.sql`.
4. Update database credentials in `includes/db.php` if needed.
5. Visit `http://localhost/clothing` in your browser.

## Project Structure (high level)

- `index.php` — Home / product listing
- `product.php` — Product detail page
- `admin.php`, `admin_edit_product.php` — Admin interfaces
- `cart.php`, `cart-panel.php`, `cart-count.php` — Cart functionality
- `includes/` — `db.php`, `header.php`, `footer.php`
- `css/style.css` — Styles
- `js/script.js` — Frontend scripts
- `images/` — Banners, product images, user images
- `database.sql` — Database seed

## Notes

- Default sample data is provided in `product_seed.php` and `database.sql`.
- For security, change any default credentials and sanitize inputs before production use.



- Run the site locally and verify pages load.
- Ask me to add a LICENSE or more detailed setup instructions if you like.
