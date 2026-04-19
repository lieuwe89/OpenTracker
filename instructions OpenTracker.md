# Project: Local Analytics & Uptime Monitor WordPress Plugin (MVP)

## Goal
Build a WordPress plugin that tracks web traffic locally and monitors website uptime. The database stores all data locally in custom tables.

## Core Features for the Minimum Viable Product

### 1. Data Collection (Tracking)
* Add a small JavaScript file to the front-end of the website.
* Count the number of visits and specific page views.
* Measure user retention per page. Use a 'heartbeat' mechanism. This is a periodic signal. The browser sends it to the server and confirms the visitor still has the page open.
* Record the traffic origin via the HTTP referrer. This tells you which link the visitor clicked to reach your site.

### 2. Data Storage (Database)
* Create custom database tables upon plugin installation. Do not store this data in standard WordPress tables like wp_options. Standard tables become slow if you fill them with analytics data.
* Anonymize all IP addresses. The system does this before it saves them.

### 3. Dashboard (Admin Interface)
* Build a clean page in the WordPress admin menu.
* Display the collected statistics. Show the total number of visitors, average time on page, and top traffic sources.

### 4. Uptime Monitor
* Use WP-Cron. This is the built-in task scheduler in WordPress.
* Set up a task. This task sends a request to the website's homepage every five minutes.
* Check the HTTP status code of that request. A code 200 means success. Save any error codes.
* Display the current status and any recent downtime in the dashboard.

### 5. Automated Reporting and Data Cleanup
* Create a scheduled WP-Cron task. This task runs once a month.
* The task generates a summary of the analytics data.
* The plugin emails this summary to the site administrator. It uses the `wp_mail()` function. The email includes a CSV file attachment with the raw data.
* The task deletes all raw analytics data older than 30 days. This deletion happens after the email sends successfully.

## Technical Requirements
* Use standard WordPress security functions for all database queries.
* Ensure the tracking script does not slow down the page load time for the visitor. Use asynchronous requests for tracking.
