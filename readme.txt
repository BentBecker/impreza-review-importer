=== Reviews Importer ===
Contributors: bentbecker
Tags: reviews, testimonials, importer, impreza, us_testimonial
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import testimonials (us_testimonial) from a JSON file and display them with shortcodes.

== Description ==

Reviews Importer lets you bulk-import reviews/testimonials into the UpSolution Impreza `us_testimonial` custom post type via a simple JSON file upload in the WordPress admin.

**Features**

* Upload a JSON file and map reviews to testimonial categories.
* Skip or overwrite duplicate entries (matched by title + author).
* Built-in shortcodes to display rating summaries, bars, and badges.
* Live shortcode preview inside the admin panel.
* Automatic updates from GitHub — no WordPress.org required.

**Shortcodes**

`[ri_rating_summary category="google"]`
Displays an overall star rating summary for a given category.

`[ri_rating_bars category="google"]`
Displays a breakdown of ratings (5★ → 1★) as progress bars.

`[ri_rating_badge category="google"]`
Displays a compact badge with the average rating and review count.

**JSON Schema**

```json
[
  {
    "review_id": "unique-id",
    "author":    "John Doe",
    "rating":    5,
    "text":      "Review text",
    "date":      "2025-06-01",
    "category":  "google"
  }
]
```

== Installation ==

1. Upload the `reviews-importer` folder to `/wp-content/plugins/`.
2. Activate the plugin in **Plugins → Installed Plugins**.
3. Navigate to **Reviews Importer** in the admin sidebar.
4. Upload a JSON file and click **Import Reviews**.

== Frequently Asked Questions ==

= Does this work without the Impreza theme? =
The importer targets the `us_testimonial` post type registered by Impreza/UpSolution. Without that post type the import will still run but the posts will not appear in Impreza elements.

= How do I update the plugin? =
Updates are delivered automatically via GitHub Releases. When a new release is published on GitHub, WordPress will show the standard update notice in your dashboard.

= Can I use a private GitHub repository? =
Yes. Add the following line to `wp-config.php`:
`define( 'RI_GITHUB_TOKEN', 'your_personal_access_token' );`

== Screenshots ==

1. Import tab — upload a JSON file and choose duplicate handling.
2. Shortcodes tab — copy shortcodes and see a live preview.

== Changelog ==

= 1.1.0 =
* Version bump — stable release of all features added since 1.0.7.

= 1.0.9 =
* Added `ri_has_content` custom field (value `1` or `0`) to every imported testimonial.
  Use this in Impreza element conditions to show/hide the content block for star-only reviews:
  Conditions → Custom field "ri_has_content" equals "1".

= 1.0.8 =
* Security: removed error-suppression operator (@) from ini_set call.
* Security: added 5 MB payload size cap on AJAX import to prevent memory exhaustion.

= 1.0.7 =
* Added GitHub auto-updater.
* Added readme.txt and CHANGELOG.md.

= 1.0.6 =
* Renamed plugin from Yooker Reviews Importer to Reviews Importer.
* Fixed UTF-8 BOM in all PHP files causing "unexpected output" on activation.

= 1.0.5 =
* Added live shortcode preview panel in admin.
* Added frontend.css enqueued on admin preview.

= 1.0.4 =
* Added rating-badge shortcode template.

= 1.0.3 =
* Added rating-bars shortcode template.

= 1.0.2 =
* Added rating-summary shortcode template.

= 1.0.1 =
* Added duplicate handling (skip / overwrite).

= 1.0.0 =
* Initial release.
