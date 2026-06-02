# Changelog

All notable changes to **Reviews Importer** are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.0.8] – 2026-06-02

### Fixed
- Removed `@` error-suppression operator from `ini_set` call in the AJAX
  import handler — errors are now handled properly instead of silently masked.
- Added a 5 MB payload size cap on `$_POST['items']` before `json_decode` to
  prevent memory exhaustion from oversized requests (returns HTTP 413).

---

## [1.0.7] – 2026-06-02

### Added
- GitHub auto-updater (`includes/class-github-updater.php`). WordPress will
  detect new releases published on
  [BentBecker/impreza-review-importer](https://github.com/BentBecker/impreza-review-importer)
  and show the standard update notice.
- `readme.txt` (WordPress plugin readme standard).
- `CHANGELOG.md` (this file).
- `GITHUB-UPDATES.md` explaining the update flow and recommended branching
  strategy.

---

## [1.0.6] – 2026-06-01

### Changed
- Renamed plugin from **Yooker Reviews Importer** to **Reviews Importer**.
  Plugin folder, text-domain, and all internal prefixes updated accordingly.

### Fixed
- Stripped UTF-8 BOM from all PHP files that caused WordPress to report
  "unexpected output" during activation.

---

## [1.0.5] – 2026-05-20

### Added
- Live shortcode preview panel inside the admin Shortcodes tab.
- `frontend.css` is now also enqueued on the admin page so the preview is
  correctly styled.

---

## [1.0.4] – 2026-05-10

### Added
- `rating-badge` shortcode template (`[ri_rating_badge]`).

---

## [1.0.3] – 2026-05-05

### Added
- `rating-bars` shortcode template (`[ri_rating_bars]`).

---

## [1.0.2] – 2026-04-28

### Added
- `rating-summary` shortcode template (`[ri_rating_summary]`).

---

## [1.0.1] – 2026-04-20

### Added
- Duplicate handling: skip or overwrite entries matched by title + author.

---

## [1.0.0] – 2026-04-01

### Added
- Initial release.
- JSON file import to `us_testimonial` custom post type.
- Admin menu page with file upload and category selector.
