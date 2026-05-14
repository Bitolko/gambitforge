# GambitForge

GambitForge is a Laravel + Vue chess platform prototype with authentication, multiplayer games, realtime move broadcasting, and a local-club tournament MVP.

## Stack

- Laravel API: `gambitforge-api`
- Vue frontend: `../gambitforge-web`
- Database: MySQL
- Realtime: Laravel Reverb
- Auth: Laravel Sanctum

## Demo Accounts

Run the database seeder to create demo users and a ready-made tournament.

All demo accounts use:

```txt
password123
```

Useful logins:

```txt
organizer@gambitforge.test
owner@gambitforge.test
ana@gambitforge.test
boris@gambitforge.test
mina@gambitforge.test
leo@gambitforge.test
```

The owner account has platform admin access to `/admin`.
The organizer account owns the seeded tournament and can start rounds, enter results, generate the next round, and finish the event.

## Admin Access

GambitForge has a minimal owner-only admin role for the `/api/admin/stats` endpoint and frontend `/admin` page.

After creating your owner account, promote it with:

```bash
php artisan tinker
```

```php
\App\Models\User::where('email', 'owner@example.com')->update(['role' => 'admin']);
```

## Backend Setup

From `gambitforge-api`:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

Recommended local `.env` database values:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gambitforge
DB_USERNAME=root
DB_PASSWORD=root123

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Start the API:

```bash
php artisan serve
```

Default API URL:

```txt
http://127.0.0.1:8000
```

Local frontend devsite:

```txt
http://dev.gambitforge.com:5174
```

Add this line to your Windows hosts file for the named local devsite:

```txt
127.0.0.1 dev.gambitforge.com
```

The API CORS config allows this fixed devsite origin. If you change the frontend host or port, update `CORS_ALLOWED_ORIGINS` in `.env` and run `php artisan config:clear`.

## Production Deployment Notes

These notes are for a future Railway, Render, or VPS deployment of the Laravel API.

Requirements:

- PHP `^8.3` as defined in `composer.json`
- Composer
- MySQL-compatible database
- A web server or process manager that runs Laravel from the `public` directory

Install dependencies:

```bash
composer install --no-dev --optimize-autoloader
```

Environment:

```bash
cp .env.example .env
php artisan key:generate
```

Set production values in `.env` on the host. Do not commit the real `.env`.

Important production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-api-domain.example
FRONTEND_URL=https://gambitforge.com
CORS_ALLOWED_ORIGINS=http://localhost:5173,https://gambitforge.com,https://www.gambitforge.com
BROADCAST_CONNECTION=log
QUEUE_CONNECTION=sync
```

Database setup:

```env
DB_CONNECTION=mysql
DB_HOST=your-database-host
DB_PORT=3306
DB_DATABASE=your-database-name
DB_USERNAME=your-database-user
DB_PASSWORD=your-database-password
```

Run migrations:

```bash
php artisan migrate --force
```

Optional seed data for demo/staging environments only:

```bash
php artisan db:seed --force
```

Optimize Laravel after environment variables are set:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Start command examples:

```bash
php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
```

For a VPS, prefer Nginx or Apache pointing at `public/` with PHP-FPM instead of `php artisan serve`.

Queue and Reverb notes:

- Initial deployment should keep `BROADCAST_CONNECTION=log` so broadcasts are logged rather than sent over WebSockets.
- Initial deployment can keep `QUEUE_CONNECTION=sync` for the current API.
- When enabling background queues later, run a worker such as `php artisan queue:work --tries=3`.
- When enabling realtime later, set up Reverb credentials, change `BROADCAST_CONNECTION=reverb`, restrict Reverb allowed origins, and run `php artisan reverb:start` as a separate managed process.

Security checklist:

- Keep `APP_DEBUG=false` in production.
- Generate `APP_KEY` on the server.
- Store real database, mail, Reverb, and third-party credentials only in the deployment platform environment.
- Never commit `.env` or secrets.

## Frontend Setup

From the sibling frontend directory:

```bash
cd ../gambitforge-web
npm install
npm run dev
```

Default frontend devsite URL:

```txt
http://dev.gambitforge.com:5174
```

The Vite dev server is pinned to port `5174` with `strictPort: true`, so it will stop with a clear error if the port is already in use instead of drifting to a new port and breaking API CORS.

The Vue API client expects Laravel at:

```txt
http://127.0.0.1:8000/api
```

## Reverb WebSockets

Realtime chess and tournament updates use Laravel Reverb. Start it in a separate terminal from `gambitforge-api`:

```bash
php artisan reverb:start --host=127.0.0.1 --port=8080
```

The frontend must have matching Vite Reverb values in `../gambitforge-web/.env`.

## Local Demo Flow

1. Start MySQL.
2. Start Laravel: `php artisan serve`.
3. Start Reverb: `php artisan reverb:start --host=127.0.0.1 --port=8080`.
4. Start Vue: `npm run dev` from `../gambitforge-web`.
5. Log in as `organizer@gambitforge.test` with `password123`.
6. Open the seeded `GambitForge Club Night Demo` tournament.
7. Copy the invite link, review standings, enter results, generate rounds, or finish the event.

Keep two browser sessions open with different demo users to see tournament joins, standings, results, generated rounds, and finished status update without refreshing.

## Tests

From `gambitforge-api`:

```bash
php artisan test
```

From `../gambitforge-web`:

```bash
npm run build
```
