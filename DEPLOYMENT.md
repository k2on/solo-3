# DEPLOYMENT.md — Bible Reading Tracker

## Overview

A full-stack Bible reading tracker with CRUD operations, search, sorting, paging, image support, and statistics.

---

## Domain & Hosting

| Item | Detail |
|------|--------|
| **Domain Name** | https://solo3.cpsc3750.koon.us |
| **Registrar** | Namecheap |
| **Hosting Provider** | [Vercel](https://vercel.com) |
| **HTTPS** | Enabled automatically by Vercel |

## Tech Stack

| Layer | Technology |
|-------|------------|
| **Frontend** | Vanilla HTML/CSS/JS with Tailwind CSS (CDN) |
| **Backend** | PHP 8.x (Vercel Serverless Functions) |
| **Database** | PostgreSQL |
| **Deployment** | Vercel (Git integration) |

## Database

| Item | Detail |
|------|--------|
| **Type** | PostgreSQL |
| **Hosted On** | Neon |
| **Connection** | Via `SOLO3_DATABASE_URL_UNPOOLED` environment variable (connection string) |
| **Schema** | Auto-created on first request (see `api/index.php`) |
| **Seed Data** | 30 records auto-inserted if the `entries` table is empty |

### Database Schema

```sql
CREATE TABLE entries (
    id SERIAL PRIMARY KEY,
    date BIGINT NOT NULL,                -- timestamp in milliseconds
    book_index INTEGER NOT NULL,         -- 0-65 (Bible book index)
    chapter INTEGER NOT NULL,            -- chapter number
    image_url TEXT NOT NULL DEFAULT '',   -- URL for entry image
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

## Configuration & Secrets

All secrets are managed via **Vercel Environment Variables** (never committed to Git):

| Variable | Description |
|----------|-------------|
| `SOLO3_DATABASE_URL_UNPOOLED` | Full PostgreSQL connection string (`postgres://user:pass@host:port/dbname`) |

### Setting Environment Variables

1. Go to your Vercel project dashboard
2. Navigate to **Settings → Environment Variables**
3. Add `SOLO3_DATABASE_URL_UNPOOLED` with your PostgreSQL connection string
4. Redeploy the project

Alternatively, use the Vercel CLI:
```bash
vercel env add SOLO3_DATABASE_URL_UNPOOLED
```

## Project Structure

```
/
├── index.html          # Frontend SPA (HTML/CSS/JS)
├── api/
│   └── index.php       # PHP API (serverless function)
├── vercel.json         # Vercel routing configuration
└── DEPLOYMENT.md       # This file
```

## How to Deploy

### Initial Setup

1. **Create a PostgreSQL database** on your provider of choice (Neon, Supabase, Railway, etc.)
2. **Copy the connection string** (format: `postgres://user:password@host:port/dbname`)
3. **Push this repo to GitHub**
4. **Import the project into Vercel:**
   - Go to [vercel.com/new](https://vercel.com/new)
   - Select the GitHub repository
   - Under **Environment Variables**, add `POSTGRES_URL` with your connection string
   - Click **Deploy**
5. **Add your custom domain** in Vercel project settings → Domains
6. **Update DNS** at your registrar to point to Vercel (CNAME or A record)

### Updating the App

Simply push to the `main` branch. Vercel auto-deploys on every push:

```bash
git add .
git commit -m "Update feature"
git push origin main
```

Vercel will automatically build and deploy the new version.

### Local Development

To test locally, you can run the PHP built-in server (requires PHP 8.x with pdo_pgsql):

```bash
# Set the environment variable
export SOLO3_DATABASE_URL_UNPOOLED="postgres://user:password@host:port/dbname"

# Start PHP dev server
php -S localhost:8000
```

Then open `http://localhost:8000` in your browser.

## Features Checklist

- [x] **SQL Database** — PostgreSQL
- [x] **30+ Seeded Records** — Auto-seeded on first run
- [x] **Full CRUD** — Create, Read, Update, Delete entries
- [x] **Delete Confirmation** — Modal dialog before deletion
- [x] **Images** — Each record has an image (URL field + predefined selection)
- [x] **Broken Image Handling** — SVG placeholder on error
- [x] **Search / Filtering** — Search by book name
- [x] **Sorting** — By date, book, or chapter (ascending/descending)
- [x] **Paging** — Configurable page size (5, 10, 20, 50)
- [x] **Page Size Cookie** — Saved and restored via cookie
- [x] **Stats View** — Total records, current page size, chapters read, books touched, streak, avg chapter, OT/NT breakdown
- [x] **Responsive Design** — Mobile and desktop layouts
- [x] **User Feedback** — Toast notifications for success/error
- [x] **Empty States** — Graceful messages when no data
- [x] **Environment Variables** — Database credentials via `POSTGRES_URL`
- [x] **HTTPS** — Enabled by Vercel
