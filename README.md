# Summeet ⛰️

A dating & meeting app for people who'd rather connect on a mountain trail than in a
swipe deck. Built with plain PHP, HTML, CSS and JS — no framework, no build step.

## How it's different from a normal dating app

- **No swiping.** People suggest hikes; you browse them and **ask to enter** a hike
  you like. The organizer approves or declines who joins.
- **Profiles are upfront, not gamified**: age, gender, whether someone is open to
  dating or only meeting/hiking friends, a free-text bio, and a **political
  orientation slider** (Radical left ↔ Alt-right) that's visible on their profile
  and next to their join requests.
- **Hikes have a real group size**, from an intimate 2–3 up to bigger groups —
  the organizer sets the minimum and maximum.

## Requirements

- PHP 7.4+ (PHP 8.x recommended) with the `pdo_mysql` extension, which ships
  enabled by default in almost every PHP install.
- A running **MySQL or MariaDB** server.
- No Composer, no npm required.

## Database setup

1. Create the database once (any MySQL/MariaDB client works, e.g. `mysql -u root -p`):

   ```sql
   CREATE DATABASE summeet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Open `config/db.php` and set your connection details — either edit the
   defaults directly, or set environment variables (which take priority if
   present): `SUMMEET_DB_HOST`, `SUMMEET_DB_PORT`, `SUMMEET_DB_NAME`,
   `SUMMEET_DB_USER`, `SUMMEET_DB_PASS`. Out of the box it assumes
   `127.0.0.1:3306`, database `summeet`, user `root`, no password — typical
   for a local install, but change the user/password for anything beyond
   local testing.

3. That's it — tables (and, on the very first run, demo data) are created
   automatically the first time the app connects.

## Running it locally

From inside the `summeet/` folder, use PHP's built-in server:

```bash
php -S localhost:8000
```

Then open **http://localhost:8000** in your browser.

The first request will create all tables and fill them with a handful of
demo users and hikes so the app isn't empty. Demo accounts:

| username        | password     |
|------------------|--------------|
| marta_trails     | summeet123   |
| leo_summits      | summeet123   |
| sofia_w          | summeet123   |
| dan_ridge        | summeet123   |
| iris_vega        | summeet123   |

Or just click **"Create your profile"** on the homepage to make your own account.

## Deploying on a normal Apache/Nginx + PHP host

1. Upload the whole `summeet/` folder to your web root (or a subfolder).
2. Make sure the `pdo_mysql` PHP extension is enabled and the host can reach
   your MySQL/MariaDB server.
3. Set the credentials in `config/db.php` (or the `SUMMEET_DB_*` env vars) to
   your production database.
4. Point your browser at `index.php`. Tables are created automatically on
   first load — no separate migration step needed.

## Project structure

```
summeet/
├── index.php              Landing page
├── register.php           Sign up (profile fields incl. political slider)
├── login.php / logout.php
├── profile.php             View & edit your own profile
├── hikes.php               Browse/search all upcoming hikes (list or map view)
├── create_hike.php         Suggest a new hike (incl. drawing its route on a map)
├── hike.php                Hike detail — "ask to enter", approve/decline requests
├── requests.php            Inbox of pending requests + your outgoing requests
├── config/
│   ├── db.php               PDO/MySQL connection + schema creation
│   └── seed.php             Demo data seeded on first run
├── includes/
│   ├── bootstrap.php        Session start + require db/functions
│   ├── functions.php        Helpers (auth, labels, spot counting, etc.)
│   ├── header.php / footer.php
├── assets/
│   ├── css/style.css
│   └── js/main.js
└── public/
    └── uploads/              (scaffolded for future use, e.g. profile photos)
```

## Maps

Summeet uses **Leaflet.js with OpenStreetMap tiles** (no API key needed) for
everything map-related:

- **Suggesting a hike**: the organizer clicks points directly on a map to draw
  the actual trail, start to finish. That drawn route is stored and is exactly
  what gets shown everywhere else. Publishing a hike requires at least 2 points.
- **Hike detail page**: shows the organizer's drawn route as a line with
  green (start) / red (end) markers.
- **Browse hikes page**: has a toggle at the top — **📋 Description** (the
  original card grid) or **🗺️ Map** (every upcoming hike's route plotted on
  one shared map; click a route or marker for a popup with details and a link
  to the hike).

### If the map doesn't show up

Leaflet and the OSM tiles are loaded from public CDNs at runtime, so **the
device running the browser** (not the server) needs internet access to reach
`unpkg.com` / `cdn.jsdelivr.net` (the app tries unpkg first, then
automatically falls back to jsDelivr) and `openstreetmap.org`. If both are
blocked — a strict corporate firewall, an offline machine, certain ad/script
blockers — every map box will show a "Map couldn't load" message instead of
silently staying blank, so that's the first thing to check. A couple of
other quick checks:

- Open the browser's dev console (F12) on a page with a map and look for
  red network errors — that will usually name the exact blocked domain.
- Make sure you're loading the app through `http://localhost:8000` (or your
  real server), not by double-clicking `hikes.php` as a local file — PHP has
  to actually run for the pages (and their data) to render at all.
- Hard-refresh (Ctrl/Cmd+Shift+R) after updating files, in case an older
  cached copy of a page or `assets/css/style.css` is being served.

## Notes on the political orientation field

It's stored as an integer from **-100 (Radical left) to +100 (Alt-right)**, set
via a slider, and shown to other users as a coarse label (Radical left / Left /
Centrist / Right / Alt-right) rather than the raw number — the point is to give
people a quick, self-described signal, not a precise score.

## Ideas for extending it

- Photo uploads for profiles (the `public/uploads` folder is already scaffolded).
- Filtering the hikes list by the organizer's intent or political label.
- Email/SMS notifications when a request is approved.
- A simple messaging thread once a request is approved.
