# Fullstack Engineer Assessment

This repository contains a PHP/Laravel solution for the Fullstack Engineer Assessment.

It includes:

1. **Task 1: Online Store API** with race-safe flash-sale order creation.
2. **Task 2: Hidden Item CLI** with coordinate output and bonus grid rendering.

---

## Requirements

- PHP 8.3+
- Composer
- SQLite extension enabled (`pdo_sqlite`, `sqlite3`)

---

## Setup

Install dependencies:

```bash
composer install
```

Create the environment file if it does not exist:

```bash
cp .env.example .env
```

On Windows CMD, use:

```cmd
copy .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Create the SQLite database file if it does not exist:

```bash
touch database/database.sqlite
```

On Windows CMD, use:

```cmd
type nul > database\database.sqlite
```

Run migrations:

```bash
php artisan migrate --force
```

Run the test suite:

```bash
php artisan test
```

Expected result:

```txt
11 passed
```

---

# Task 1: Online Store API

## Summary

The API supports product creation/listing and order creation. It is designed for a flash-sale scenario where many orders may try to buy the same discounted product concurrently.

The key safety rule is:

> Inventory quantity must never become negative.

## API Endpoints

List API routes:

```bash
php artisan route:list --path=api
```

Available endpoints:

```txt
GET      /api/products
POST     /api/products
GET      /api/products/{product}
POST     /api/orders
GET      /api/orders/{order}
```

All API responses use JSON.

## Race-Safe Inventory Strategy

Order creation uses an atomic conditional decrement:

```php
Product::query()
    ->whereKey($productId)
    ->where('inventory_quantity', '>=', $quantity)
    ->decrement('inventory_quantity', $quantity);
```

This is intentionally a single database update statement. Under concurrent requests, only transactions that still see enough inventory can decrement stock. If no row is updated, the order is rejected with `409 Conflict`.

The order service also retries transient concurrency errors such as SQLite write locks or database deadlocks. This makes the implementation resilient when multiple flash-sale workers write at the same time.

## Running the API Locally

Start the server:

```bash
php artisan serve
```

The API will usually be available at:

```txt
http://127.0.0.1:8000
```

## Manual API Test

### Create a product

```bash
curl -X POST http://127.0.0.1:8000/api/products \
  -H "Content-Type: application/json" \
  --data-raw '{"name":"Flash Sale Headphones","inventory_quantity":5,"price":250000,"discount_price":25000}'
```

Windows CMD version:

```cmd
curl -X POST http://127.0.0.1:8000/api/products -H "Content-Type: application/json" --data-raw "{\"name\":\"Flash Sale Headphones\",\"inventory_quantity\":5,\"price\":250000,\"discount_price\":25000}"
```

Expected response:

```json
{
  "message": "Product created successfully."
}
```

### List products

```bash
curl http://127.0.0.1:8000/api/products
```

### Create an order

Assuming the product ID is `1`:

```bash
curl -X POST http://127.0.0.1:8000/api/orders \
  -H "Content-Type: application/json" \
  --data-raw '{"items":[{"product_id":1,"quantity":2}]}'
```

Windows CMD version:

```cmd
curl -X POST http://127.0.0.1:8000/api/orders -H "Content-Type: application/json" --data-raw "{\"items\":[{\"product_id\":1,\"quantity\":2}]}"
```

Expected response:

```json
{
  "message": "Order created successfully."
}
```

The product inventory should decrease from `5` to `3`.

### Check product inventory

```bash
curl http://127.0.0.1:8000/api/products/1
```

### Test insufficient inventory

```bash
curl -i -X POST http://127.0.0.1:8000/api/orders \
  -H "Content-Type: application/json" \
  --data-raw '{"items":[{"product_id":1,"quantity":999}]}'
```

Windows CMD version:

```cmd
curl -i -X POST http://127.0.0.1:8000/api/orders -H "Content-Type: application/json" --data-raw "{\"items\":[{\"product_id\":1,\"quantity\":999}]}"
```

Expected response code:

```txt
409 Conflict
```

Expected message:

```json
{
  "message": "Insufficient inventory for product."
}
```

## Automated Task 1 Tests

Run all tests:

```bash
php artisan test
```

Run only the flash-sale race-condition test:

```bash
php artisan test --filter=FlashSaleConcurrencyTest
```

Run explicit API behavior tests:

```bash
php artisan test --filter=OrderApiTest
```

The flash-sale test starts multiple PHP worker processes against one shared SQLite database file. This reproduces a burst of concurrent orders and verifies that:

- only available stock can be sold,
- extra orders are rejected,
- inventory ends at zero, not negative,
- no unexpected database or application errors occur.

---

# Task 2: Hidden Item CLI

## Summary

The hidden item game uses this grid:

```txt
########
#......#
#.###..#
#...#.##
#X#....#
########
```

Symbols:

- `#` = obstacle
- `.` = clear path
- `X` = player's starting position
- `$` = probable hidden item location in the bonus output

## Interpretation

The assessment does not specify how `A`, `B`, and `C` are provided, so this solution accepts them as command-line options:

- `--up=A`
- `--right=B`
- `--down=C`

Coordinates are displayed as **1-based `(row, column)`** values.

Probable item locations are interpreted as every clear-path cell visited while following the required movement order:

```txt
Up/North A step(s) -> Right/East B step(s) -> Down/South C step(s)
```

Movement stops when the next cell is an obstacle or outside the grid.

## Run the CLI

```bash
php artisan hidden-item:solve --up=3 --right=4 --down=2
```

Expected output:

```txt
Hidden Item Solver
Start position: (5, 2)

Probable item coordinates, using 1-based (row, column):
- (4, 2)
- (3, 2)
- (2, 2)
- (2, 3)
- (2, 4)
- (2, 5)
- (2, 6)
- (3, 6)
- (4, 6)

Grid with probable item locations:
########
#$$$$$.#
#$###$.#
#$..#$##
#X#....#
########
```

## Automated Task 2 Tests

Run solver unit tests:

```bash
php artisan test --filter=HiddenItemSolverTest
```

Run CLI command tests:

```bash
php artisan test --filter=HiddenItemCommandTest
```

---

## Code Quality

The solution separates responsibilities into:

- Form request classes for API validation.
- Controllers for HTTP request/response handling.
- `OrderService` for race-safe order creation.
- Domain models for product/order relationships.
- Feature tests for API and concurrency behavior.
- `HiddenItemSolver` for grid movement logic.
- Artisan command tests for CLI behavior.

Code style can be checked with Laravel Pint:

```bash
vendor/bin/pint --test
```

On Windows CMD:

```cmd
vendor\bin\pint --test
```
