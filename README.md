# Password Vault

A secure, modern, fully responsive password manager built with PHP, MySQL, JavaScript, and CSS.

## Features

- **Secure Authentication** — bcrypt password hashing, CSRF protection, login attempt limiting, session management
- **Password Vault** — Create, read, update, delete password entries with full CRUD
- **Encryption at Rest** — AES-256-CBC encryption for all stored passwords
- **Password Generator** — Configurable generator with character type controls and strength meter
- **Categories & Tags** — Organize entries with custom categories and tags
- **Search & Filter** — Full-text search, filter by category, sort by multiple fields
- **Favorites & Archive** — Star important entries, archive unused ones
- **Import / Export** — CSV export and import
- **Activity Logging** — Full audit trail of all sensitive actions
- **Responsive Design** — Mobile-first, works on 320px to 1920px screens
- **Dark / Light Theme** — Persisted in localStorage
- **Glassmorphism UI** — Modern, premium, RGB-themed design with smooth animations

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- mod_rewrite (Apache) or equivalent
- OpenSSL extension (for encryption)

## Installation

### 1. Clone or download the files

Place the project in your web server's document root (e.g., `htdocs`, `www`, or a subdomain).

### 2. Create the database

Run the SQL file to create the database and tables:

```bash
mysql -u root -p < database.sql
```

Or import `database.sql` via phpMyAdmin or your preferred MySQL client.

### 3. Configure environment

Copy `.env.example` to `.env` and update the values:

```bash
cp .env.example .env
```

Edit `.env` with your database credentials:

```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=password_manager
DB_USER=root
DB_PASS=your_password

SESSION_LIFETIME=1800
MAX_LOGIN_ATTEMPTS=5
LOGIN_TIMEOUT=900
```

### 4. Set permissions

Ensure the web server has write access if you plan to use file-based features.

### 5. Start the application

Open your browser and navigate to the project URL. You will be redirected to the login page.

### 6. Register

Click "Create Account" and fill in the registration form. Use a strong master password — it is used for encryption and cannot be recovered.

## Security Notes

- All passwords are encrypted using AES-256-CBC before being stored in the database
- Account passwords are hashed with bcrypt (cost 12)
- SQL injection is prevented through prepared statements
- XSS is prevented through output escaping
- CSRF tokens protect all state-changing requests
- Login attempts are rate-limited
- Sessions have a configurable timeout
- Plaintext passwords are never logged

## Folder Structure

```
password-manager/
├── api/
│   ├── auth.php          # Authentication endpoints
│   ├── entries.php       # Password entry CRUD
│   ├── search.php        # Search endpoint
│   └── settings.php      # Settings & categories endpoints
├── assets/
│   └── default-favicon.svg
├── config/
│   ├── auth.php          # Authentication logic
│   ├── database.php      # Database connection
│   └── security.php      # Encryption, validation, CSRF
├── css/
│   └── style.css         # Complete stylesheet
├── includes/
│   ├── footer.php        # Footer template
│   ├── functions.php     # Helper functions
│   ├── header.php        # HTML head template
│   ├── navbar.php        # Top navigation bar
│   └── sidebar.php       # Sidebar navigation
├── js/
│   └── app.js            # Vanilla JavaScript application
├── .env.example          # Environment configuration template
├── .htaccess             # Apache security rules
├── add-entry.php         # Add password entry page
├── dashboard.php         # Main dashboard
├── database.sql          # Database schema
├── edit-entry.php        # Edit password entry page
├── forgot-password.php   # Password reset request page
├── index.php             # Entry point
├── login.php             # Login page
├── logout.php            # Logout handler
├── profile.php           # User profile page
├── register.php          # Registration page
├── reset-password.php    # Password reset page
├── settings.php          # User settings page
├── vault.php             # Password vault listing
└── view-entry.php        # Single entry view
```

## Keyboard Shortcuts

- `Ctrl + K` — Focus search bar
- `Ctrl + N` — Add new entry
- `Escape` — Close modal

## License

MIT
