# FreshCart - Full-Stack Online Grocery Store

FreshCart is a comprehensive, production-ready online grocery marketplace built with PHP, MySQL, and Bootstrap 5. It features a responsive design, AJAX-powered shopping cart, secure user authentication, and a full administrative dashboard.

## Features
- **Responsive UI**: Works perfectly on mobile, tablet, and desktop.
- **User Authentication**: Secure registration and login (password hashing).
- **Product Catalog**: Filter products by category or search by name.
- **Shopping Cart**: Add items without page reload (AJAX), update quantities, and remove items.
- **Order Management**: Transactional checkout system with order history.
- **Admin Dashboard**: Manage inventory (CRUD), view sales stats, and monitor orders.

## Prerequisites
- **Web Server**: Apache (XAMPP, WAMP, or MAMP recommended)
- **PHP**: Version 7.4 or higher
- **Database**: MySQL/MariaDB

## Installation Steps

1.  **Clone or Download**:
    Place the project folder into your server's root directory (e.g., `C:/xampp/htdocs/freshcart`).

2.  **Database Setup**:
    - Open **phpMyAdmin**.
    - Create a new database named `grocery_store`.
    - Select the database and go to the **Import** tab.
    - Choose the file `database/schema.sql` from the project folder and click **Go**.

3.  **Configuration**:
    - Open `config/db.php`.
    - Update the `$username` and `$password` if your local MySQL settings are different (defaults are `root` and empty).

4.  **Run the App**:
    - Start Apache and MySQL in your XAMPP/WAMP panel.
    - Navigate to `http://localhost/freshcart/index.php` in your browser.

## Vercel Deployment
This project includes a Vercel PHP router in `api/index.php` and `vercel.json`.
For a live deployment, configure these Vercel environment variables before using
the app with a hosted MySQL database:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `DB_PORT` (optional)

Local XAMPP still works with the default `localhost`, `grocery_store`, `root`,
and empty password settings.

## Credentials
- **Customer**: Register a new account via the UI.
- **Admin**:
    - **Email**: `admin@grocery.com`
    - **Password**: `admin123`

## Project Structure
- `index.php`: Main landing page with featured items.
- `products.php`: Searchable product listing.
- `cart.php`: User cart management.
- `checkout.php`: Process order.
- `admin/`: Restricted folder for store management.
- `config/`: Database connection logic.
- `assets/`: CSS, JS, and UI enhancement files.

## Troubleshooting
- **Database Error**: Ensure the database name in `config/db.php` matches what you created in phpMyAdmin.
- **Images not loading**: The app uses Unsplash CDN for images. Ensure you have an active internet connection.
- **Permission Denied**: If running on Linux/Mac, ensure the web server has read/write permissions for the project folder.
