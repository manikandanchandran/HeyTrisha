# Database Setup Files

This directory contains database setup files for the HeyTrisha API.

## Files

- **`setup.sql`** - Complete SQL script to create database and tables manually
- **`migrations/`** - Laravel migration files (use `php artisan migrate`)

## Quick Start

### Option 1: SQL Script (Fastest)
```bash
mysql -u root -p < database/setup.sql
```

### Option 2: Laravel Migrations (Recommended)
```bash
php artisan migrate
```

## Database Name

Default database name: `heytrisha_api`

You can change this in:
- SQL script: Edit `CREATE DATABASE` statement
- Laravel: Edit `.env` file `DB_DATABASE` value

## Required Tables

1. **sites** - Main table for WordPress site registrations
2. **migrations** - Laravel migration tracking table

See `DATABASE_SETUP.md` for detailed instructions.


