# ComfSci-The-Block-Management-System-for-BSCS-Students

## Project structure

- `index.php` - protected application entry point
- `auth/` - login, logout, registration, and session guards
- `admin/` - administrator-only management tools
- `config/` - shared database configuration
- `pages/` - student-facing dashboard pages and assets
- `assets/auth/` - login presentation assets
- `db_comfysci.sql` (in the workspace root) - database schema

Open the project root through XAMPP to sign in. The standalone dummy-account
form is available at `auth/register.php`, and the administrator management
page is available at `admin/management.php` after an admin signs in.