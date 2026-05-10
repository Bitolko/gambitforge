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

## Frontend Setup

From the sibling frontend directory:

```bash
cd ../gambitforge-web
npm install
npm run dev
```

Default frontend URL:

```txt
http://127.0.0.1:5173
```

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
