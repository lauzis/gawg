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
- **`[gawg_form]` shortcode** — embeds an AJAX entry form on any page or post; collects an email address, optionally links to the giveaway rules (via `rules_url` or `rules_post_id`), prevents duplicate entries per giveaway, and displays a configurable HTML success message (`success_message`).
- **`gawg/form` Gutenberg block** — a dynamic block that wraps the `[gawg_form]` shortcode; configure the giveaway and rules URL from the block's Inspector Controls panel and see a live server-side preview in the editor.
- **Spam protection** — two layers of defence on every form submission:
  - **Honeypot** — a hidden field invisible to real users; if a bot fills it in, the submission is silently rejected server-side.
  - **Google reCAPTCHA v2** — optional; when both a Site Key and Secret Key are saved on the Settings page the "I'm not a robot" checkbox widget is automatically rendered in the form and the response is verified server-side. When no keys are configured reCAPTCHA is skipped entirely.
- **Admin UI** — a dedicated GAWG menu in the WordPress admin with:
  - **Participants** — list and manage all participant posts.
  - **Giveaways** — list and manage all giveaway taxonomy terms.
  - **Self Tests** page — run built-in verification checks (e.g. UUID generation) directly from the admin panel.
  - **Help** page — step-by-step instructions for creating giveaways, adding participants, and embedding the entry form.
  - **Settings** page — configure plugin-wide options such as Google reCAPTCHA keys.

## Requirements
- WordPress 6.0+
- PHP 8.0+

## Installation
1. Upload the `gawg` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **GAWG** in the admin menu to get started.

## Usage
1. Go to **GAWG → Giveaways** and click **Add New Giveaway**. Give the giveaway a name and save it. A unique UUID is automatically assigned and shown in the **Reference UUID** field when you edit the term.
2. Embed the entry form using the **Giveaway Form** block (search for it in the block inserter) or via the shortcode:
   ```
   [gawg_form uuid="<giveaway-uuid>" rules_url="https://example.com/rules"]
   ```
   With the block, select the giveaway and rules URL in the Inspector Controls panel; the editor shows a live preview of the form.
   Optional shortcode attributes:
   - `rules_post_id` — post ID of a rules page (alternative to `rules_url`).
   - `success_message` — custom HTML shown after a successful submission (default: a translatable thank-you message).
3. Visitors submit the form with their email address. Duplicate entries for the same giveaway are rejected with an inline message.
4. Go to **GAWG → Participants** to view all entries, filtered by giveaway if needed.

## Current State
The plugin is in early development (v1.0.0). Giveaways are modelled as a custom taxonomy and participants as a custom post type, both with auto-generated reference UUIDs. The `[gawg_form]` shortcode and the `gawg/form` Gutenberg block both enable front-end AJAX entry collection; both share the same rendering code so changes to the shortcode output apply to both automatically. Every form includes a honeypot field that silently rejects bot submissions server-side. Optional Google reCAPTCHA v2 (checkbox) integration is also available — configure it via **GAWG → Settings** and it is activated automatically when both keys are present. Winner-drawing functionality is planned for a future release.

## Development
This project is maintained with the assistance of [Claude Code](https://claude.ai/code) and [CodeRabbit](https://coderabbit.ai).
