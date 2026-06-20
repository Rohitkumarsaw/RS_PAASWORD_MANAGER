# RS PAASWORD MANAGER

A secure, modern, fully responsive password manager built with PHP, MySQL, JavaScript, and CSS. Features both user and admin panels with a glassmorphism UI.

## Features

- **Secure Authentication** — bcrypt password hashing, CSRF protection, login attempt limiting, session management
- **Password Vault** — Create, read, update, delete password entries with full CRUD
- **Encryption at Rest** — AES-256-CBC encryption for all stored passwords
- **Password Generator** — Configurable generator with character type controls and strength meter
- **Categories & Tags** — Organize entries with custom categories and tags
- **Search & Filter** — Full-text search, filter by category, sort by multiple fields
- **Favorites & Archive** — Star important entries, archive unused ones
- **Import / Export** — CSV, HTML, and PDF export
- **Credit Card Management** — Save and manage credit/debit card details
- **Password Health** — Scan and analyze password strength, reuse, and age
- **Activity Logging** — Full audit trail of all sensitive actions (user + admin)
- **Google Sign-In** — OAuth-based login with Google
- **Secure Sharing** — Share credentials via expiring time-limited links
- **TOTP 2FA** — Time-based one-time password for two-factor authentication
- **PIN Lock** — Quick-access PIN as a secondary unlock
- **Admin Panel** — Manage users, view all activity, and system administration
- **Responsive Design** — Mobile-first, works on 320px to 1920px+ screens
- **Dark / Light Theme** — Persisted in localStorage (separate for admin)
- **Glassmorphism UI** — Modern, premium design with smooth animations
- **Keyboard Shortcuts** — Ctrl+K search, Ctrl+N new entry, Escape close modal

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- mod_rewrite (Apache) or equivalent
- OpenSSL extension (for encryption)
- GD or Imagick extension (for PDF export)

## Installation

### 1. Clone or download the files

Place the project in your web server's document root (e.g., `htdocs\RS_PAASWORD_MANAGER`).

### 2. Create the database

Run the SQL file to create the database and tables:

```bash
mysql -u root -p < database.sql
```

Or import `database.sql` via phpMyAdmin or your preferred MySQL client.

For migration updates, run `database-migration.sql` after the base schema.

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

### 6. Register & make yourself admin

Click "Create Account" and fill in the registration form. Use a strong master password — it is used for encryption and cannot be recovered.

To grant admin access, run in MySQL:

```sql
UPDATE users SET is_admin = 1 WHERE email = 'your@email.com';
```

Then access the admin panel at `http://localhost/RS_PAASWORD_MANAGER/admin/`.

## Admin Panel

The admin panel provides:
- **Dashboard** — Overview of users, stored passwords, and saved cards
- **Users** — List, search, and manage registered users
- **Activity Log** — View all user activity with filters and export

Access: `/admin/` — requires `is_admin = 1` in the users table.

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
RS_PAASWORD_MANAGER/
├── admin/
│   ├── index.php           # Admin dashboard
│   ├── login.php           # Admin login
│   ├── logout.php          # Admin logout
│   ├── users.php           # User management
│   ├── user-detail.php     # User detail view
│   └── activity.php        # Activity log (admin)
├── api/
│   ├── auth.php            # Authentication endpoints
│   ├── cards.php           # Credit card CRUD + export
│   ├── credentials.php     # Credential CRUD
│   ├── entries.php         # Password entry CRUD + export
│   ├── health.php          # Password health analysis
│   ├── oauth.php           # Google OAuth + export
│   ├── pin.php             # PIN lock endpoints
│   ├── search.php          # Search endpoint
│   ├── settings.php        # Settings & categories endpoints
│   ├── shares.php          # Secure sharing endpoints
│   └── totp.php            # TOTP 2FA endpoints
├── assets/
│   └── default-favicon.svg
├── config/
│   ├── auth.php            # Authentication logic
│   ├── database.php        # Database connection
│   ├── security.php        # Encryption, validation, CSRF
│   └── totp.php            # TOTP configuration
├── css/
│   └── style.css           # Complete stylesheet (3085 lines)
├── includes/
│   ├── admin_footer.php    # Admin panel footer
│   ├── admin_header.php    # Admin panel HTML head + topbar
│   ├── admin_sidebar.php   # Admin sidebar navigation
│   ├── footer.php          # User footer template
│   ├── functions.php       # Helper functions
│   ├── header.php          # HTML head template
│   ├── navbar.php          # Top navigation bar (user)
│   └── sidebar.php         # Sidebar navigation (user)
├── js/
│   └── app.js              # Vanilla JavaScript application
├── lib/
│   └── tcpdf/              # TCPDF library for PDF export
├── .env.example            # Environment configuration template
├── .htaccess               # Apache security rules
├── activity.php            # Activity log (user)
├── cards.php               # Credit card management
├── dashboard.php           # Main dashboard
├── database.sql            # Database schema
├── database-migration.sql  # Schema migration updates
├── forgot-password.php     # Password reset request page
├── google-signin.php       # Google OAuth accounts page
├── index.php               # Entry point (redirect)
├── login.php               # Login page
├── logout.php              # Logout handler
├── password-health.php     # Password health scanner
├── profile.php             # User profile page
├── register.php            # Registration page
├── reset-password.php      # Password reset page
├── settings.php            # User settings page
├── share.php               # Secure credential share
├── trash.php               # Trashed/deleted entries
├── vault.php               # Password vault listing
└── README.md               # This file
```

## Keyboard Shortcuts

- `Ctrl + K` — Focus search bar
- `Ctrl + N` — Add new entry
- `Escape` — Close modal

## License

MIT
