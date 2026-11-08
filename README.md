# STS Instagram Feed

Fetches your Instagram Business posts through the Facebook Graph API, refreshes the access token automatically, and exposes a PHP function for your theme. It does not output any markup itself.

## Usage
```php
$posts = sts_fetch_instagram_posts( 6 ); // up to 6 latest posts
```
Render the result in your theme as you like.

## Setup
1. Create a Facebook App and an Instagram Business account linked to a Facebook Page.
2. Open **Settings > Instagram API** and enter the Instagram Business ID, an access token (with `instagram_basic` and `instagram_manage_insights`), and the Facebook App ID and App Secret.
3. With the App ID and Secret set, a daily cron job refreshes the token before it expires.

Admin hints are in Hebrew.

## Requirements
WordPress 5.0+, PHP 7.4+. WooCommerce is not needed.

## Security note
The token and app secret are stored in the WordPress options table. Treat database access accordingly.

## Status

Version 1.0.0. Provided as-is, under GPL-2.0-or-later (see `LICENSE`).

Setup, customization and support are available from [Shay Tech Solutions](https://shaytechsolutions.com).
