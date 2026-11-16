# Sign-Now

Document e-signing platform: upload a PDF, place signature and form fields, invite recipients, and collect signatures with live dashboard updates.

## Repository layout

Everything lives in [`sign-now/`](sign-now/) — a Laravel (PHP 8.3) + MySQL + Redis app with a Node.js Socket.IO server for real-time updates. Fully Dockerized.

## Quick start

```bash
cd sign-now
cp .env.example .env                       # then fill in your own values
cp laravel-app/.env.example laravel-app/.env
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=AdminSeeder
```

Open http://localhost:8027. Full instructions, services and ports are in [`sign-now/README.md`](sign-now/README.md).

## Tests

```bash
cd sign-now
docker compose exec app php artisan test
```

## License

MIT — see [LICENSE](LICENSE).
