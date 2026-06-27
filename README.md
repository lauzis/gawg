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
- **Extra entries via invite links** — after submitting the entry form each participant receives a personal invite link (`?gwag-giveaway={uuid}&invite={participant-uuid}`). Two bonus mechanisms are available, each configurable on the Settings page:
  - **Unique visit bonus** — any unique IP address that visits the site via the link increments the inviting participant's entry count by the configured amount (default +1); repeat visits from the same IP are ignored.
  - **Registration bonus** — when a visitor arrives via an invite link a short-lived cookie records the attribution. If that visitor then registers for the same giveaway, the inviter is automatically awarded a configurable bonus (default +1). The bonus is awarded only once per referred registration.
  Entry counts are shown in the Participants admin list and in the "Entries & Invite Links" panel on each participant's edit screen.
- **Draw Winner** — a dedicated admin page (**GAWG → Draw Winner**) for running the lottery: select an active giveaway, view a masked participant list (first char + `****` + last char + @domain), configure shuffle count and delay, then click **Shuffle & Pick Winner** to animate through the list and select a random winner server-side. The winner's post ID is saved to the giveaway term and the masked email is displayed. The winner also appears as a read-only field on the giveaway's taxonomy edit screen.
- **Admin UI** — a dedicated GAWG menu in the WordPress admin with:
  - **Participants** — list and manage all participant posts.
  - **Giveaways** — list and manage all giveaway taxonomy terms. Each term's edit screen shows a read-only **Winner** field once a winner has been drawn.
  - **Draw Winner** page — select a giveaway, shuffle participants, and pick a winner at random.
  - **Self Tests** page — run built-in verification checks (e.g. UUID generation) directly from the admin panel.
  - **Help** page — step-by-step instructions for creating giveaways, adding participants, and embedding the entry form.
  - **Settings** page — configure plugin-wide options: Google reCAPTCHA keys, extra entries for unique visit (default 1), and extra entries for registration after visit (default 1).

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
4. After a successful submission the participant sees their personal invite link. Each unique IP that visits via that link awards the inviting participant a configurable bonus (default +1). If that visitor later registers for the same giveaway, the inviter receives an additional configurable bonus (default +1).
5. Go to **GAWG → Participants** to view all entries and their per-giveaway entry counts, filtered by giveaway if needed.

## Current State
The plugin is in early development (v1.0.0). Giveaways are modelled as a custom taxonomy and participants as a custom post type, both with auto-generated reference UUIDs. The `[gawg_form]` shortcode and the `gawg/form` Gutenberg block both enable front-end AJAX entry collection; both share the same rendering code so changes to the shortcode output apply to both automatically. Every form includes a honeypot field that silently rejects bot submissions server-side. Optional Google reCAPTCHA v2 (checkbox) integration is also available — configure it via **GAWG → Settings** and it is activated automatically when both keys are present. After submitting the form each participant receives a personal invite link; unique IP visits via that link increment the participant's entry count for the giveaway (IP deduplication is enforced server-side). Visitors who arrive via an invite link and then register earn the inviter a second configurable bonus via cookie-based attribution. Both bonus amounts are configurable on the Settings page (defaults: +1 each). The **Draw Winner** page (**GAWG → Draw Winner**) allows selecting an active giveaway, viewing participants with masked emails, running an animated shuffle, and picking a random winner server-side; the winner is stored on the giveaway term and displayed read-only on its edit screen.

## Development
This project is maintained with the assistance of [Claude Code](https://claude.ai/code) and [CodeRabbit](https://coderabbit.ai).
