# Changelog

All notable changes to CalendiFlow – Booking & Event Calendar will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.1] - 2026-10-18

### Security
- Added rate limiting on booking submissions (IP + User-Agent + user_id based)
- Added rate limiting on waiting list submissions (email + activity + date based)
- Improved token generation with 128-bit entropy using `random_bytes()`
- Cancellation links now show generic messages (no booking existence disclosure)
- Added `UNIQUE` constraint on booking tokens
- Implemented transaction-based atomic checks for overbooking prevention

### Fixed
- Waiting-list promotion emails are now sent after transaction commits (prevents email/no-reservation mismatch on rollback)
- Database migration version consistency (CALEBO_VERSION now matches migration checks)
- Resolved race condition in waiting list auto-promotion with `FOR UPDATE SKIP LOCKED`

### Changed
- PDF reports now generated entirely in browser (removed server-side PDF endpoint)
- Text domain standardized to `calendar-booking` (matches plugin slug)
- Updated Tested up to: WordPress 7.1

### Removed
- Removed obsolete `class-pdf-generator.php` (unused code)
- Removed dead `ajax_download_pdf()` endpoint

## [1.3.9] - 2026-09-10

### Added
- Permanent deletion of all bookings for a specific activity from Reservations screen
- Activity tab × button now removes records completely instead of archiving

### Fixed
- "Cannot modify header information - headers already sent" warning
- Moved redirect-requiring form actions to `admin_init` hook (before output)
- Replaced raw `header()` calls with `wp_send_json_error()` for proper AJAX responses

## [1.1.5] - 2025-05-15

### Changed
- Stability and security improvements across admin and booking flows
- Enhanced input validation and sanitization

## [1.1.1] - 2025-03-20

### Fixed
- Improved booking flow stability
- Admin configuration improvements
- Compatibility adjustments for WordPress 6.5+ and PHP 8.1+

### Changed
- Metadata alignment for WordPress.org distribution

## [1.1.0] - 2025-02-10

### Added
- Reports and email-related improvements for reservation management
- Enhanced calendar configuration options
- Improved booking experience with better error messages

### Changed
- Refactored notification system for better reliability

## [1.0.0] - 2025-01-15

### Added
- Initial release
- Booking calendar with real-time availability
- Capacity management by attendees
- Multiple calendars support
- Visual design panel
- Customizable form fields
- Holiday and vacation management
- Email notifications
- PDF reports
- SMTP configuration
- Privacy/GDPR tools

---

## Version History

- **2.0.x** — Security hardening, WordPress.org preparation
- **1.3.x** — Admin improvements, bug fixes
- **1.1.x** — Stability and compatibility updates
- **1.0.x** — Initial public release

[2.0.1]: https://github.com/misaelpalomino/calendar-booking/releases/tag/v2.0.1
[1.3.9]: https://github.com/misaelpalomino/calendar-booking/releases/tag/v1.3.9
[1.1.5]: https://github.com/misaelpalomino/calendar-booking/releases/tag/v1.1.5
[1.1.1]: https://github.com/misaelpalomino/calendar-booking/releases/tag/v1.1.1
[1.1.0]: https://github.com/misaelpalomino/calendar-booking/releases/tag/v1.1.0
[1.0.0]: https://github.com/misaelpalomino/calendar-booking/releases/tag/v1.0.0
