# OpenTracker

A privacy-first WordPress plugin for local analytics and uptime monitoring. All data is stored in your own database — no third-party services, no external tracking.

## Features

### 📊 Visitor Analytics
- Track page views and unique visitors
- Measure time-on-page using a heartbeat mechanism (15-second intervals)
- Record traffic sources via HTTP referrer
- All IP addresses are anonymised before storage

### ⏱ Uptime Monitor
- Automatic health checks every 5 minutes via WP-Cron
- Logs HTTP status codes and response times
- Visual timeline in the dashboard showing the last 24 hours
- Downtime event log with error details

### 📈 Admin Dashboard
- Clean, modern interface inside the WordPress admin panel
- Stat cards: total visits, unique visitors, average retention, uptime percentage
- Interactive line chart (Chart.js) showing visits over time
- Top pages and top referrers tables
- Date range filter (7 / 30 / 90 days)

### 📧 Monthly Reports
- Automated email report sent to the site administrator
- Includes a CSV attachment with raw visit data
- Old data (30+ days) is cleaned up after each report

## Installation

### Option A: Upload via Admin Panel
1. Download `open-tracker.zip` from the [Releases](https://github.com/lieuwe89/OpenTracker/releases) page
2. Go to **Plugins → Add New → Upload Plugin**
3. Choose the zip file and click **Install Now**
4. Click **Activate**

### Option B: Manual Installation
1. Clone this repository:
   ```bash
   git clone https://github.com/lieuwe89/OpenTracker.git
   ```
2. Copy the `open-tracker/` directory into `wp-content/plugins/`
3. Activate the plugin in **Plugins → Installed Plugins**

## Usage

After activation, the plugin starts working immediately:

- **Dashboard**: Navigate to **OpenTracker** in the admin sidebar
- **Tracking**: The front-end script is automatically added to all public pages
- **Uptime checks**: Run every 5 minutes via WP-Cron
- **Reports**: Sent on the first day of each month

> **Note on WP-Cron**: WordPress cron only fires on page load. For accurate 5-minute uptime checks on low-traffic sites, add a real server cron job:
> ```
> */5 * * * * curl -s https://yoursite.com/wp-cron.php > /dev/null 2>&1
> ```

## Privacy

OpenTracker is designed with privacy in mind:

- **IP anonymisation**: The last octet (IPv4) or last 80 bits (IPv6) are zeroed before hashing. Raw IP addresses are never stored.
- **No cookies**: The tracking script does not set any cookies.
- **No external requests**: All data stays in your database. The only external resource is Chart.js loaded from a CDN for the admin dashboard.
- **Admin exclusion**: Logged-in administrators are not tracked.
- **Auto-cleanup**: Raw data older than 30 days is deleted after each monthly report.

## Database Tables

The plugin creates four custom tables (prefixed with your WordPress table prefix):

| Table | Purpose |
|---|---|
| `ot_visits` | Page views with referrer, anonymised IP hash, and user agent |
| `ot_heartbeats` | Retention pings linked to visits (one row per 15-second interval) |
| `ot_uptime_checks` | Uptime check history with status codes and response times |
| `ot_monthly_reports` | Archive of sent monthly reports |

## REST API

The plugin registers two public REST endpoints for the tracking script:

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/wp-json/open-tracker/v1/hit` | Records a page visit |
| `POST` | `/wp-json/open-tracker/v1/heartbeat` | Records a retention heartbeat |

## File Structure

```
open-tracker/
├── open-tracker.php              # Plugin entry point
├── assets/
│   ├── css/
│   │   └── ot-admin.css          # Dashboard styles
│   └── js/
│       ├── ot-tracker.js         # Front-end tracking script (~2 KB)
│       └── ot-admin.js           # Dashboard chart initialisation
├── includes/
│   ├── class-ot-database.php     # Table creation & IP anonymisation
│   ├── class-ot-plugin.php       # Main controller
│   ├── class-ot-rest-api.php     # REST API endpoints
│   ├── class-ot-tracker.php      # Front-end script enqueue
│   ├── class-ot-admin.php        # Admin menu & dashboard
│   ├── class-ot-stats.php        # Query helpers for analytics data
│   ├── class-ot-uptime.php       # WP-Cron uptime monitor
│   └── class-ot-reports.php      # Monthly report & data cleanup
└── templates/
    └── dashboard.php             # Admin dashboard template
```

## Requirements

- WordPress 5.9 or later
- PHP 7.4 or later
- MySQL 5.7 or later

## License

GPL-2.0-or-later
