# Recipe Schema Plugin (WordPress)

This repository contains a simple WordPress plugin that injects [Schema.org Recipe](https://schema.org/Recipe) JSON-LD into singular post/page views.

## Features

- Adds a **Recipe Schema** metabox to Posts and Pages in WP Admin.
- Stores recipe fields as post meta.
- Outputs JSON-LD `<script type="application/ld+json">` in `wp_head`.
- Requires core fields before outputting schema:
  - Recipe Name
  - Ingredients (one per line)
  - Instructions (one step per line)

## Installation

1. Copy `recipe-schema-plugin.php` into your WordPress plugins directory, for example:
   - `wp-content/plugins/recipe-schema-plugin/recipe-schema-plugin.php`
2. Activate **Recipe Schema Plugin** from the WordPress admin plugins page.

## Usage

1. Edit a post or page.
2. Fill out the **Recipe Schema** metabox fields.
3. Save/Update the post.
4. View the post on the front end and inspect page source for the JSON-LD script.

## Notes

- Time fields should use ISO 8601 duration format (`PT20M`, `PT1H`).
- Ingredients and instructions are split by newlines.
