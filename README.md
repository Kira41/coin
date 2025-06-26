# Coin Dashboard

This project is a simple PHP web interface that stores data in a database and exposes two API endpoints. It includes sample pages, a SQL schema and seed data for demonstration.

## Prerequisites

- PHP 7.4 or later
- SQLite (used by default) or MySQL

## Setup

1. Create a new SQLite database file and apply the schema:

   ```bash
   sqlite3 coin_db.db < create_tables.sql
   ```

   If you prefer MySQL, run the SQL files on your MySQL server instead.

2. (Optional) Populate the database with example entries:

   ```bash
   sqlite3 coin_db.db < seed_data.sql
   ```

## Configuration

The PHP scripts read database settings from environment variables:

- `DB_DSN`  – DSN for the PDO connection (defaults to `sqlite:coin_db.db`).
- `DB_USER` – Username for the database (empty by default).
- `DB_PASS` – Password for the database (empty by default).

## Running the server

From the project root, start PHP's built-in web server:

```bash
php -S localhost:8000
```

Then open `http://localhost:8000/index.html` in your browser.

## API Endpoints

- **getter.php** – Returns dashboard data from the database as JSON.
- **setter.php** – Accepts JSON via POST and updates the database with the provided values.

These endpoints are used by `script.js` to load and save information displayed on the dashboard pages.
