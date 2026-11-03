# Cooperative Management System — PHP Phase 2

A PHP 8+ / MySQL cooperative-management starter with login, role-based access, a dashboard, cooperative registration, member CRUD, profile/password updates, CSRF protection, prepared statements, and audit events. JavaScript is used for responsive navigation and safe delete confirmation.

## Requirements
- PHP 8.1+ with `pdo_mysql` enabled
- MySQL 8.0+ or a compatible MariaDB release
- Apache/Nginx or PHP's local development server

## Setup (Windows with XAMPP)
1. Copy the `cooperative-php-phase2` folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** from XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin` and import `database/schema.sql`.
4. If your MySQL account is not `root` with an empty password, set environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`, or update the defaults in `config/config.php`.
5. Create the first administrator password hash from Command Prompt (in a PHP-enabled terminal):
   ```bash
   php -r "echo password_hash('ReplaceWithAStrongPassword!', PASSWORD_DEFAULT), PHP_EOL;"
   ```
6. In phpMyAdmin, open the `cooperative_management` database, select the `users` table, and insert a row with:
   - `full_name`: your administrator name
   - `email`: your login email
   - `password_hash`: the generated hash (not the plain password)
   - `role`: `system_admin`
   - `status`: `active`
7. Visit `http://localhost/cooperative-php-phase2/public/login.php` and sign in. Change the temporary password after login if you used one.

Alternative local server: from this folder run `php -S 127.0.0.1:8000 -t public`, then visit `http://127.0.0.1:8000/login.php`. Ensure MySQL is running and the database credentials are configured.

## Roles
- `system_admin`: sees all cooperatives and members; can register cooperatives and members.
- `cooperative_manager`: sees and manages members for the assigned cooperative; can delete members within that cooperative.
- `staff`: sees members for the assigned cooperative and can create/update member records.
- `auditor`: read-focused access intended for review (some forms are still available in this starter; refine the permission matrix before production use).

## Included modules
- `public/login.php`, `public/logout.php`: session-based authentication and sign-out
- `public/index.php`: cooperative/member dashboard and recent members
- `public/cooperatives.php`: cooperative directory and registration
- `public/members.php`, `public/member-form.php`: searchable member directory and create/update
- `public/reports.php`: filter membership by name, status, cooperative, and join date; export all matching records to CSV. Cooperative-scoped users only see their assigned cooperative, and exports exclude national IDs and birth dates.
- `public/users.php`, `public/user-form.php`: system administrators can create and update accounts, assign cooperative roles, disable access, and reset passwords. The last active system administrator and the signed-in administrator's own role/access cannot be disabled or changed through this screen.
- `public/profile.php`: update account details and password
- `database/schema.sql`: cooperatives, users, members, and audit logs

## Security and production notes
- Passwords use PHP's `password_hash()` / `password_verify()`; database access uses PDO prepared statements; forms use CSRF tokens; output is escaped.
- Configure HTTPS, secure cookie settings, backups, least-privilege database credentials, rate-limiting/lockout, and proper production error logging before deployment.
- Restrict web access to the `public/` directory. Keep `config/`, `database/`, and `app/` outside the document root in production.
- Validate local legal/data-protection requirements before storing national IDs or other personal information.
- The dashboard uses a Google Fonts import; the rest of the UI remains functional if that font is unavailable.
