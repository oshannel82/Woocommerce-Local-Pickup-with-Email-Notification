# WooCommerce Ready to Collect

A lightweight WordPress plugin that adds a **"Ready to Collect"** custom order status to WooCommerce, triggers a customer notification email when an order is marked with that status, and registers the email template with **YayMail** so it can be fully designed in YayMail's drag-and-drop editor.

---

## Features

- Adds a "Ready to Collect" order status that appears alongside all native WooCommerce statuses
- Automatically sends a customer notification email when an order is moved to that status
- Email subject and heading are editable via **WooCommerce → Settings → Emails**
- Full drag-and-drop template editing via **YayMail** (free or Pro)
- No configuration required — install, activate, and it works

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 6.0+ |
| WooCommerce | 7.0+ |
| YayMail | Any (free or Pro) |
| PHP | 7.4+ |

YayMail is **optional** — the email will send correctly without it. YayMail is only needed if you want to customise the email's visual design.

---

## Installation

### Via FTP / cPanel

1. Download or clone this repository
2. Upload the `ready-to-collect` folder to `wp-content/plugins/`
3. Your plugin folder should look like this:

```
wp-content/plugins/ready-to-collect/
├── ready-to-collect.php
├── templates/
│   └── ready-to-collect-email.php
├── README.md
└── LICENSE
```

4. Go to **WordPress Admin → Plugins** and activate **WooCommerce Ready to Collect Status**

### Via WordPress Admin

1. Zip the `ready-to-collect` folder
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Upload the zip and activate

---

## Usage

### Changing an order status

1. Go to **WooCommerce → Orders**
2. Open any order
3. Change the status dropdown to **Ready to Collect**
4. Save the order — the customer email fires automatically

You can also bulk-update orders from the Orders list view using the **Bulk Actions** dropdown.

### Customising the email in YayMail

1. Go to **YayMail → Templates**
2. Find **Ready to Collect** in the list
3. Click to open the drag-and-drop editor and design freely

### Customising the subject line and heading

1. Go to **WooCommerce → Settings → Emails**
2. Find **Ready to Collect** in the list and click **Manage**
3. Edit the subject line, heading, and additional content

---

## How it works

This plugin registers **two parallel email classes** — which is the key pattern required to support both WooCommerce and YayMail simultaneously:

| Class | Extends | Purpose |
|---|---|---|
| `RTC_WC_Email` | `WC_Email` | Handles the actual email sending when the order status changes |
| `RTC_YayMail_Email` | `\YayMail\Abstracts\BaseEmail` | Registers the template in YayMail's editor via the `yaymail_register_emails` action |

Both classes share the same `id` (`ready_to_collect`) so they stay in sync. YayMail's editor completely ignores `WC_Email` subclasses — it maintains its own separate email registry via `yaymail_register_emails`, which is why plugins that only extend `WC_Email` show as "template not found" in YayMail.

---

## Frequently Asked Questions

**Does this work without YayMail?**  
Yes. The email sends correctly using WooCommerce's default transactional email styling. YayMail is only needed for visual customisation.

**Will it conflict with other custom status plugins?**  
It shouldn't. The status slug `wc-ready-to-collect` and all function names are namespaced with the `rtc_` prefix. If you do hit a conflict, open an issue.

**Can I rename the status or email?**  
Not from the UI currently — you'd need to edit the PHP directly. A settings page for this is on the roadmap.

---

## Changelog

### 1.0.0
- Initial public release
- Custom "Ready to Collect" order status
- WooCommerce email notification on status change
- YayMail template registration via official `yaymail_register_emails` API

---

## Contributing

Pull requests are welcome. For major changes please open an issue first to discuss what you'd like to change.

---

## License

[MIT](LICENSE)

