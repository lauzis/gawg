# GAWG — GiveAway Winner Generator

## What is it?
A WordPress plugin for running giveaways: create a giveaway entry, collect participant UUIDs via external forms or links, and pick a winner at random.

## Who's it for?
Generally, it's for me; it's a weekend project that is both useful and interesting.  
It could be useful for anyone running small giveaways on a WordPress site without needing a third-party service.

## What it does
- **Giveaway taxonomy** — each giveaway is a custom taxonomy term (`gawg_giveaway`) with a unique reference UUID, used to group and identify a giveaway campaign.
- **Participant post type** — each participant is a WordPress post (`gawg_participant`) with a title and its own unique reference UUID for external identification.
- **Auto-generated UUIDs** — a v4 UUID is created automatically for both giveaway terms (on save) and participant posts (on publish), displayed in edit screens for easy copying.
- **Admin UI** — a dedicated GAWG menu in the WordPress admin with:
  - **Participants** — list and manage all participant posts.
  - **Giveaways** — list and manage all giveaway taxonomy terms.
  - **Self Tests** page — run built-in verification checks (e.g. UUID generation) directly from the admin panel.
  - **Help** page — step-by-step instructions for creating giveaways and adding participants.

## Requirements
- WordPress 6.0+
- PHP 8.0+

## Installation
1. Upload the `gawg` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **GAWG** in the admin menu to get started.

## Usage
1. Go to **GAWG → Giveaways** and click **Add New Giveaway**. Give the giveaway a name and save it. A unique UUID is automatically assigned and shown in the **Reference UUID** field when you edit the term.
2. Share the giveaway UUID or an entry link with your audience so they can participate.
3. Go to **GAWG → Add New** (Participants) to add a participant. Assign them to a giveaway via the **Giveaway** panel and click **Publish**. A unique participant UUID is generated automatically.
4. Use the **Participants** list to manage all entries, filtering by giveaway as needed.

## Current State
The plugin is in early development (v1.0.0). Giveaways are modelled as a custom taxonomy and participants as a custom post type, both with auto-generated reference UUIDs. Entry collection and winner-drawing functionality are planned for upcoming releases.

## Development
This project is maintained with the assistance of [Claude Code](https://claude.ai/code) and [CodeRabbit](https://coderabbit.ai).
