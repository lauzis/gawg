# GAWG — GiveAway Winner Generator

## What is it?
A WordPress plugin for running giveaways: create a giveaway entry, collect participant UUIDs via external forms or links, and pick a winner at random.

## Who's it for?
Generally, it's for me; it's a weekend project that is both useful and interesting.  
It could be useful for anyone running small giveaways on a WordPress site without needing a third-party service.

## What it does
- **Giveaway post type** — each giveaway is a WordPress post with a title and a unique reference UUID.
- **Auto-generated UUID** — a v4 UUID is created automatically when a giveaway is published and shown in a sidebar meta box for easy copying.
- **Admin UI** — a dedicated GAWG menu in the WordPress admin with:
  - **Self Tests** page — run built-in verification checks (e.g. UUID generation) directly from the admin panel.
  - **Help** page — step-by-step instructions for creating and managing giveaways.

## Requirements
- WordPress 6.0+
- PHP 8.0+

## Installation
1. Upload the `gawg` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **GAWG** in the admin menu to get started.

## Usage
1. Go to **GAWG → Add New Giveaway** and give the giveaway a title.
2. Click **Publish**. A unique UUID is generated automatically and displayed in the **Reference UUID** box.
3. Share the UUID or an entry link with participants so they can submit entries.
4. Use the giveaway list to manage and review all active giveaways.

## Current State
The plugin is in early development (v1.0.0). The core giveaway structure and UUID generation are in place. Entry collection and winner-drawing functionality are planned for upcoming releases.

## Development
This project is maintained with the assistance of [Claude Code](https://claude.ai/code) and [CodeRabbit](https://coderabbit.ai).
