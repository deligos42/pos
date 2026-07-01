# POS

Simple PHP/MySQL point-of-sale system.

## Local Setup

1. Create a MySQL database named `pos_system`.
2. Import `database.sql`.
3. For an existing installation, also apply `scripts/001_hardening_schema.sql`.
4. Copy `.env.example` to `.env` and set your database credentials.
5. Serve the project from XAMPP/Apache and visit `index.php`.

Default admin account from `database.sql`:

```text
username: admin
password: admin123
```

Change the default password before using real data.

## Security Notes

- Forms and AJAX sale completion use CSRF tokens.
- Destructive actions use POST requests instead of delete links.
- User uploads under `assets/profile_photos` deny PHP execution.
- Debug files, `.env`, SQL dumps, and logs are blocked by `.htaccess`.
- Schema changes belong in SQL files under `scripts/`, not in page requests.

## Docker Build & Test

Build the image locally and run a container:

```bash
docker build -t pos-app .
docker run -p 8080:80 --rm pos-app
```

To inspect enabled Apache modules inside the running container:

```bash
docker run --rm pos-app bash -c "ls /etc/apache2/mods-enabled && apachectl -M | grep mpm || true"
```

