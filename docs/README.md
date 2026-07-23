# Freelance Marketplace

Day 3 project foundation for a Myanmar freelance marketplace.

## Run locally

1. Create a MySQL database named `freelance_marketplace`.
2. In `backend`, run `php artisan migrate` and `php artisan serve`.
3. Copy `frontend/.env.example` to `frontend/.env`, then run `npm.cmd run dev` in `frontend`.

The frontend calls `GET /api/health` to confirm the Laravel API is available.
