=== CalendiFlow – Booking & Event Calendar ===
Contributors: misaelpalomino, mpb0803
Tags: booking, reservations, calendar, capacity, attendees
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Booking system with capacity limit per attendee. Perfect for playrooms, workshops, medical appointments and events.

== Description ==

Booking system with capacity limit per number of attendees. Ideal for playrooms, workshops and more.

= Main Features =

* Booking calendar with real-time availability
* Configurable capacity limit by number of attendees
* Multiple calendars with independent shortcode
* Visual design panel with colors, typography and custom styles
* Customizable form fields with predefined templates
* Configurable available days per week
* Public holidays and vacation periods management
* Cancellation system via secure link in email
* PDF reports (daily, weekly, monthly and annual)
* Automatic daily email to administrator
* Compatible with any WordPress theme

= Shortcodes =

* `[cb_calendario id="1"]` — Shows the booking calendar
* `[cb_confirmacion]` — Confirmation page after booking
* `[cb_cancelar]` — Booking cancellation page

= Form templates included =

* Playroom
* Generic booking
* Medical appointment
* Class or workshop

== Installation ==

1. Upload the `calendar-booking` folder to `/wp-content/plugins/`
2. Activate the plugin from the WordPress Plugins menu
3. Go to **Calendar Booking > Calendars** and create your first calendar
4. Insert the shortcode `[cb_calendario id="1"]` on any page

== Frequently Asked Questions ==

= Can I have multiple calendars? =
Yes, you can create as many calendars as you need, each with its own shortcode, capacity limit and design.

= How does the capacity limit work? =
The limit is calculated by adding the numeric field of attendees across all bookings for the day. When the sum reaches the configured limit, the day appears as FULL.

= Can clients cancel their booking? =
Yes, upon confirming the booking they receive an email with a unique link to cancel it.

== Screenshots ==

1. Frontend booking calendar with real-time availability display
2. Admin dashboard showing all reservations and daily statistics
3. Activity configuration screen with capacity limits and schedules
4. Visual design customization panel with color and typography options
5. Booking form with customizable fields and templates
6. PDF reports generation (daily, weekly, monthly views)

== Privacy Policy ==

This plugin stores booking information in your WordPress database, including:

* Name
* Email address
* Phone number (if enabled in the booking form)
* Booking details such as selected date, attendees, and optional notes

This data is used only to manage reservations and send booking notifications.

This plugin integrates with WordPress personal data tools and provides:

* Personal data export support
* Personal data erasure/anonymization support

This plugin includes the Freemius SDK for licensing/update infrastructure and may connect to Freemius services depending on plugin settings and consent flow.

If the site owner configures SMTP providers or other third-party email services, booking notifications may be processed by those providers.

Site owners should describe this collection, retention and third-party processing in their site's privacy policy.

== Changelog ==

= 2.0.1 =
* Security hardening: rate limiting on booking and waiting list submissions, unique booking tokens, cancellation links no longer reveal whether a booking exists.
* Waiting-list promotion emails are now sent after the transaction commits, so a rollback cannot leave users with a confirmation email but no reservation.
* PDF reports are generated entirely in the browser (no server-side PDF endpoint).
* Standardized text domain to `calendar-booking` (matches plugin slug).
* Tested up to WordPress 7.1.

= 1.3.9 =
* Added: Permanent deletion of all bookings (and waiting list) for a specific activity directly from the Reservations screen. The × button on each activity tab now removes records completely instead of archiving them.
* Fixed: "Cannot modify header information - headers already sent" warning caused by wp_safe_redirect() being called inside page-render callbacks. Form actions that require redirects are now processed on the admin_init hook, before any output is generated.
* Fixed: Raw header() calls for rate-limiting replaced with proper HTTP 429 status via wp_send_json_error() to avoid header conflicts in AJAX context.

= 1.1.5 =
* Stability and security improvements across admin and booking flows.

= 1.1.1 =
* Improved booking flow stability and admin configuration.
* Added compatibility adjustments for current WordPress and PHP requirements.

= 1.1.0 =
* Added reports and email-related improvements for reservation management.
* Improved calendar configuration options and booking experience.

= 1.0.0 =
* First stable version

== Upgrade Notice ==

= 2.0.1 =
Important update. Security hardening, waiting-list email fixes and standardized text domain. Recommended for all users.

= 1.3.9 =
Important update. Fixes "headers already sent" PHP warning in WordPress admin. Adds permanent deletion of activity bookings from the Reservations screen.

= 1.1.1 =
Recommended update with stability improvements and metadata alignment for WordPress.org distribution.

= 1.1.0 =
Includes new management improvements for reports, notifications and booking settings.

= 1.0.0 =
First version of the plugin.