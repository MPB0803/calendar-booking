# CalendiFlow – Booking & Event Calendar

[![WordPress Plugin Version](https://img.shields.io/badge/version-2.0.1-blue.svg)](https://github.com/MPB0803/calendar-booking)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)](LICENSE.txt)

Booking system with capacity limit per number of attendees. Perfect for playrooms, workshops, medical appointments and events.

## 🎯 Features

- **Real-time booking calendar** with availability display
- **Capacity management** by number of attendees per day
- **Multiple calendars** with independent configuration
- **Visual design panel** with colors, typography and custom styles
- **Customizable form fields** with predefined templates
- **Available days configuration** per week
- **Holiday and vacation management**
- **Secure cancellation system** via email link
- **PDF reports** (daily, weekly, monthly, annual)
- **Daily admin emails** with statistics
- **Compatible** with any WordPress theme

## 📦 Installation

### From WordPress.org (Recommended)

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "CalendiFlow"
3. Click **Install Now** and then **Activate**

### Manual Installation

1. Download the latest release from [GitHub Releases](https://github.com/MPB0803/calendar-booking/releases)
2. Upload the `calendar-booking` folder to `/wp-content/plugins/`
3. Activate the plugin from the WordPress Plugins menu

### From Source

```bash
git clone https://github.com/MPB0803/calendar-booking.git
cd calendar-booking
# No build process required - ready to activate
```

## 🚀 Quick Start

1. Go to **Calendar Booking > Calendars** in WordPress admin
2. Create your first calendar with capacity limit
3. Configure available days and holidays
4. Customize the design (colors, typography)
5. Insert the shortcode `[cb_calendario id="1"]` on any page

## 📝 Shortcodes

- `[cb_calendario id="1"]` — Display the booking calendar
- `[cb_confirmacion]` — Booking confirmation page
- `[cb_cancelar]` — Booking cancellation page

## 🎨 Form Templates

- **Playroom** — For children's play spaces
- **Generic Booking** — Flexible for any service
- **Medical Appointment** — Healthcare settings
- **Class/Workshop** — Educational events

## 📋 Requirements

- **WordPress:** 5.8 or higher
- **PHP:** 7.4 or higher
- **MySQL:** 5.6 or higher
- **Tested up to:** WordPress 7.1

## 🔒 Security

This plugin has been fully audited for security:

- ✅ SQL injection protection (prepared statements)
- ✅ XSS protection (output escaping)
- ✅ CSRF protection (nonces)
- ✅ Rate limiting on bookings
- ✅ Secure token generation (128-bit entropy)
- ✅ Capability checks on all admin actions

For details, see [AUDIT-REPORT.md](AUDIT-REPORT.md)

## 🌐 Translation

The plugin is translation-ready with the `calendar-booking` text domain.

Current translations:
- **English** (default)
- **Spanish** (es_ES)

Want to translate? Use the `.pot` file in `/languages/`

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2026 Misael Francisco Palomino Beltrán

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

See [LICENSE.txt](LICENSE.txt) for full license text.

## 📞 Support

- **WordPress.org Forums:** [Plugin Support](https://wordpress.org/support/plugin/calendar-booking)
- **GitHub Issues:** [Report bugs or request features](https://github.com/MPB0803/calendar-booking/issues)
- **Documentation:** [Wiki](https://github.com/MPB0803/calendar-booking/wiki) *(coming soon)*

## 📊 Changelog

### 2.0.1 (2026-10-18)

- **Security:** Rate limiting on booking and waiting list submissions
- **Security:** Unique booking tokens with 128-bit entropy
- **Security:** Cancellation links no longer reveal booking existence
- **Fix:** Waiting-list promotion emails sent after transaction commits
- **Enhancement:** PDF reports generated entirely in browser
- **Standards:** Text domain standardized to `calendar-booking`
- **Tested:** WordPress 7.1 compatibility confirmed

### 1.3.9

- Added permanent deletion of activity bookings from Reservations screen
- Fixed "headers already sent" PHP warning
- Improved rate-limiting with proper HTTP 429 status

See full [CHANGELOG.md](CHANGELOG.md) for version history.

## 🙏 Credits

Developed and maintained by [Misael Francisco Palomino Beltrán](https://github.com/MPB0803)

---

**⭐ If this plugin helps you, please consider [rating it on WordPress.org](https://wordpress.org/support/plugin/calendar-booking/reviews/)!**
