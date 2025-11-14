# 🎁 Mix & Match Bundle for WooCommerce

[![WordPress Plugin Version](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce Version](https://img.shields.io/badge/WooCommerce-5.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![HPOS Compatible](https://img.shields.io/badge/HPOS-Compatible-success.svg)](https://woocommerce.com/document/high-performance-order-storage/)

> **Create customizable product bundles with tiered quantity discounts. Increase AOV with a beautiful bundle builder and real-time discount calculations.**

![Mix & Match Bundle](https://betatech.co/assets/mix-match-bundle-banner.png)

---

## 📖 Table of Contents

- [Features](#-features)
- [Demo](#-demo)
- [Installation](#-installation)
- [Usage](#-usage)
- [Screenshots](#-screenshots)
- [Configuration](#-configuration)
- [Translation](#-translation)
- [Development](#-development)
- [FAQ](#-faq)
- [Support](#-support)
- [Contributing](#-contributing)
- [License](#-license)
- [Credits](#-credits)

---

## ✨ Features

### 🎯 Core Features

- **Unlimited Bundles** - Create as many bundles as needed with unique configurations
- **Tiered Discounts** - Set quantity-based discount tiers (e.g., buy 2 save 10%, buy 5 save 20%)
- **Dual Bundle Modes**
  - 📝 **Selection Mode** - Customers pick products with checkboxes
  - 🔢 **Quantity Mode** - Customers set quantities for each product
- **Variable Products** - Full support with variation dropdown selection
- **Real-Time Calculations** - Live price updates as customers build bundles
- **Shortcode Integration** - `[mmb_bundle id="X"]` - Place bundles anywhere

### 🎨 Design & Customization

- **5 Color Schemes** - Primary, Button Text, Accent, Hover Background, Hover Accent
- **Custom Text** - Modify all frontend text and labels
- **Visibility Controls** - Show/hide title, description, headings, hints, progress
- **Progress Display** - Visual tier progress with checkmark indicators
- **Responsive Design** - Works flawlessly on all devices
- **Mobile Optimized** - Sticky mobile cart footer for enhanced UX

### 🔌 Integrations

- **Sidecart Compatible** - FunnelKit, WooCommerce Side Cart, and more
- **HPOS Ready** - High-Performance Order Storage compatible
- **Theme Agnostic** - Works with any properly coded WordPress theme
- **Translation Ready** - 28 pre-configured language files included

### 🚀 Performance

- **Modern JavaScript** - Vanilla JS (no jQuery dependencies)
- **Optimized CSS** - CSS variables for lightning-fast customization
- **AJAX-Powered** - Smooth, no-page-reload experience
- **Lightweight** - Minimal footprint, maximum performance

### 🔒 Security

- **Nonce Verification** - All AJAX requests protected
- **Data Sanitization** - Input sanitization throughout
- **Output Escaping** - XSS protection on all outputs
- **WPCS Compliant** - WordPress Coding Standards

---

## 🎥 Demo

**Live Demo:** [https://demo.betatech.co/mix-match-bundle](https://demo.betatech.co/mix-match-bundle)


---

## 💾 Installation

### From WordPress.org (Recommended)

1. Log into your WordPress admin panel
2. Navigate to **Plugins** → **Add New**
3. Search for "Mix & Match Bundle"
4. Click **Install Now** → **Activate**
5. Go to **Mix & Match** in the admin menu

### From GitHub

```bash
cd wp-content/plugins/
git clone https://github.com/ashawkat/mix-match-bundle-for-woocommerce
```

Then activate from WordPress admin → Plugins.

### Manual Upload

1. Download the [latest release](https://github.com/ashawkat/mix-match-bundle-for-woocommerce/releases)
2. Upload ZIP via **Plugins** → **Add New** → **Upload Plugin**
3. Activate and start creating bundles!

---

## 🚀 Usage

### Quick Start (5 Minutes)

1. **Navigate** to **Mix & Match** in WordPress admin
2. **Create** a new bundle or edit inline
3. **Name** your bundle (e.g., "Build Your Box")
4. **Select Products** - Search and choose products to include
5. **Add Tiers** - Set quantity and discount (e.g., 2 items = 10% off)
6. **Customize** - Colors, text, visibility (optional)
7. **Save** and copy the shortcode
8. **Add** shortcode to any page: `[mmb_bundle id="1"]`
9. **Preview** and test your bundle!

### Shortcode

```php
[mmb_bundle id="1"]
```

**Parameters:**
- `id` (required) - The bundle ID

### PHP Template Tag

```php
<?php
if ( function_exists( 'mmb_display_bundle' ) ) {
    mmb_display_bundle( 1 ); // Bundle ID
}
?>
```

---

## 📸 Screenshots

### Admin Interface

**Bundle Editor**
![Bundle Editor](https://betatech.co/assets/screenshots/bundle-editor.png)

**Product Selection**
![Product Selection](https://betatech.co/assets/screenshots/product-selection.png)

**Discount Tiers**
![Discount Tiers](https://betatech.co/assets/screenshots/discount-tiers.png)

**Design Customization**
![Design Options](https://betatech.co/assets/screenshots/design-customization.png)

### Frontend Display

**Desktop View**
![Desktop Bundle](https://betatech.co/assets/screenshots/frontend-desktop.png)

**Mobile View**
![Mobile Bundle](https://betatech.co/assets/screenshots/frontend-mobile.png)

**Progress Indicators**
![Discount Progress](https://betatech.co/assets/screenshots/progress-display.png)

---

## ⚙️ Configuration

### Bundle Settings

| Setting | Description | Options |
|---------|-------------|---------|
| **Name** | Bundle name displayed to customers | Text |
| **Description** | Optional bundle description | Textarea |
| **Mode** | Selection or Quantity mode | Selection/Quantity |
| **Products** | Products available in bundle | Multi-select |
| **Discount Tiers** | Quantity-based discounts | Unlimited tiers |

### Design Options

| Option | Description | Default |
|--------|-------------|---------|
| **Primary Color** | Main brand color | #4caf50 |
| **Button Text Color** | Text color for buttons | #ffffff |
| **Accent Color** | Secondary accent | #45a049 |
| **Hover BG** | Hover background | #388e3c |
| **Hover Accent** | Hover accent color | #2e7d32 |

### Text Customization

- Heading Text (default: "Select Your Products Below")
- Hint Text (default: "Bundle 2, 3, 4 or 5 items and watch the savings grow.")
- Button Text (default: "Add Bundle to Cart")
- Progress Text (default: "Your Savings Progress")

### Visibility Options

- ✅ Show/Hide Bundle Title
- ✅ Show/Hide Bundle Description
- ✅ Show/Hide Heading Text
- ✅ Show/Hide Hint Text
- ✅ Show/Hide Progress Text

### Cart Behavior

- **Sidecart** - Open sidecart popup (recommended)
- **Redirect** - Redirect to cart page

---

## 🌍 Translation

### Supported Languages (28)

The plugin includes pre-configured translation files for:

**European:** 🇫🇷 French, 🇩🇪 German, 🇪🇸 Spanish, 🇮🇹 Italian, 🇵🇹 Portuguese, 🇳🇱 Dutch, 🇵🇱 Polish, 🇸🇪 Swedish, 🇩🇰 Danish, 🇳🇴 Norwegian, 🇫🇮 Finnish, 🇬🇷 Greek, 🇷🇴 Romanian, 🇹🇷 Turkish

**American:** 🇲🇽 Spanish (Mexico), 🇧🇷 Portuguese (Brazil), 🇬🇧 English (UK)

**Asian:** 🇨🇳 Chinese (Simplified), 🇹🇼 Chinese (Traditional), 🇯🇵 Japanese, 🇰🇷 Korean, 🇮🇳 Hindi, 🇸🇦 Arabic, 🇹🇭 Thai, 🇻🇳 Vietnamese, 🇮🇩 Indonesian, 🇲🇾 Malay, 🇵🇭 Filipino

### How to Translate

#### Using Poedit (Recommended)

1. Download [Poedit](https://poedit.net/) (free)
2. Open `languages/mix-match-bundle-{locale}.po`
3. Translate strings
4. Save (auto-generates .mo file)
5. Upload both files

#### Using Loco Translate Plugin

1. Install Loco Translate from WordPress
2. Go to **Loco Translate** → **Plugins**
3. Select **Mix & Match Bundle**
4. Translate online

#### Translation Files

```
languages/
├── mix-match-bundle.pot           # Template
├── mix-match-bundle-fr_FR.po      # French
├── mix-match-bundle-de_DE.po      # German
└── ... (26 more languages)
```

**Text Domain:** `mix-match-bundle`

**Total Strings:** 67

---

## 🛠️ Development

### Requirements

- **WordPress** 5.8+
- **WooCommerce** 5.0+
- **PHP** 7.4+
- **MySQL** 5.6+ or MariaDB 10.0+

### File Structure

```
mix-match-bundle/
├── admin/
│   └── bundle-editor.php          # Admin bundle editor
├── assets/
│   ├── css/
│   │   ├── admin.css               # Admin styles
│   │   └── frontend.css            # Frontend styles
│   ├── img/
│   │   └── mix-match-icon.svg      # Plugin icon
│   └── js/
│       ├── admin.js                # Admin JavaScript
│       └── frontend.js             # Frontend JavaScript
├── includes/
│   ├── class-bundle-manager.php    # Bundle CRUD operations
│   ├── class-cart.php              # Cart integration
│   ├── class-frontend.php          # Frontend display
│   ├── class-settings.php          # Settings management
│   └── class-shortcode.php         # Shortcode handler
├── languages/
│   ├── mix-match-bundle.pot        # Translation template
│   ├── mix-match-bundle-*.po       # 28 language files
│   └── README.md                   # Translation guide
├── templates/
│   └── bundle-display.php          # Frontend template
├── mix-match-bundle.php            # Main plugin file
├── readme.txt                      # WordPress.org readme
├── README.md                       # GitHub readme (this file)
├── uninstall.php                   # Cleanup on uninstall
└── TRANSLATION-SUMMARY.md          # Translation documentation
```

### Tech Stack

**Frontend:**
- Vanilla JavaScript (ES6+)
- CSS3 with Custom Properties
- Responsive Grid & Flexbox
- AJAX for seamless UX

**Backend:**
- PHP 7.4+ with OOP
- WordPress & WooCommerce APIs
- wpdb for database operations
- WordPress Coding Standards

**Database:**
- Custom table: `{prefix}_mmb_bundles`
- WooCommerce session storage
- No external dependencies

### Coding Standards

This plugin follows:
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WooCommerce Coding Standards](https://github.com/woocommerce/woocommerce/wiki/Coding-Guidelines)
- [PHP PSR-12](https://www.php-fig.org/psr/psr-12/)

### Hooks & Filters

**Actions:**
```php
do_action( 'mmb_before_bundle_display', $bundle_id );
do_action( 'mmb_after_bundle_display', $bundle_id );
do_action( 'mmb_bundle_saved', $bundle_id, $bundle_data );
```

**Filters:**
```php
apply_filters( 'mmb_bundle_discount', $discount, $bundle_id, $quantity );
apply_filters( 'mmb_bundle_products', $products, $bundle_id );
apply_filters( 'mmb_cart_behavior', $behavior, $bundle_id );
```

### Development Setup

```bash
# Clone repository
git clone https://github.com/betatech/mix-match-bundle.git

# Navigate to plugin directory
cd mix-match-bundle

# Install WordPress locally (if needed)
# Activate WooCommerce
# Activate Mix & Match Bundle
# Start developing!
```

### Testing

**Manual Testing:**
1. Create bundles with different configurations
2. Test on multiple themes
3. Test on desktop, tablet, mobile
4. Test with variable products
5. Test discount calculations
6. Test cart integration

**Browser Support:**
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari, Chrome Mobile)

---

## ❓ FAQ

<details>
<summary><strong>Does this work with variable products?</strong></summary>

Yes! The plugin fully supports WooCommerce variable products with dropdown variation selection.
</details>

<details>
<summary><strong>Can I create multiple bundles?</strong></summary>

Absolutely! Create unlimited bundles with different products and discount structures.
</details>

<details>
<summary><strong>Is it mobile-friendly?</strong></summary>

Yes! Fully responsive with a special sticky mobile cart footer for optimal mobile UX.
</details>

<details>
<summary><strong>Does it work with my theme?</strong></summary>

Yes! The plugin is designed to work with any properly coded WordPress theme.
</details>

<details>
<summary><strong>Is it compatible with HPOS?</strong></summary>

Yes! Fully compatible with WooCommerce High-Performance Order Storage.
</details>

<details>
<summary><strong>Can I customize the design?</strong></summary>

Yes! Customize 5 colors, all text, and control visibility of elements. Advanced CSS customization also supported.
</details>

<details>
<summary><strong>How are discounts calculated?</strong></summary>

Discounts are based on total quantity in the bundle. Reach a tier threshold to unlock that discount percentage.
</details>

<details>
<summary><strong>Does it work with sidecart plugins?</strong></summary>

Yes! Integrates with FunnelKit, WooCommerce Side Cart, and most theme-based sidecarts.
</details>

---

## 🆘 Support

### Need Help?

- 📧 **Email:** support@betatech.co
- 🐛 **Bug Reports:** [GitHub Issues](https://github.com/ashawkat/mix-match-bundle-for-woocommerce/issues)

### Before Requesting Support

1. ✅ Check FAQ above
2. ✅ Review documentation
3. ✅ Search existing issues
4. ✅ Test with default theme
5. ✅ Disable other plugins to check conflicts

### Creating an Issue

When reporting bugs, please include:
- WordPress version
- WooCommerce version
- PHP version
- Active theme
- Active plugins
- Steps to reproduce
- Expected vs actual behavior
- Screenshots/videos (if applicable)

---

## 🤝 Contributing

We welcome contributions from the community!

### Ways to Contribute

1. **Report Bugs** - [GitHub Issues](https://github.com/ashawkat/mix-match-bundle-for-woocommerce/issues)
2. **Request Features** - [GitHub Issues](https://github.com/ashawkat/mix-match-bundle-for-woocommerce/issues)
3. **Submit Pull Requests** - Fix bugs or add features
4. **Translate** - Help localize the plugin
5. **Documentation** - Improve docs and guides
6. **Spread the Word** - Share with others!

### Pull Request Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Coding Guidelines

- Follow WordPress Coding Standards
- Add inline documentation
- Include translator comments for i18n strings
- Write meaningful commit messages
- Test thoroughly before submitting

---

## 📄 License

This plugin is licensed under the GPLv2 or later.

```
Mix & Match Bundle for WooCommerce
Copyright (C) 2024 Betatech

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
```

Full license: [http://www.gnu.org/licenses/gpl-2.0.html](http://www.gnu.org/licenses/gpl-2.0.html)

---

## 👏 Credits

### Development Team

**[Betatech](https://betatech.co)** - Building better eCommerce solutions

- **Adnan Shawkat** - Lead Developer & Architect
- **Betatech Team** - Design, Testing & Support

### Special Thanks

- **WooCommerce Team** - For the amazing eCommerce platform
- **WordPress Community** - For continuous inspiration and support
- **Beta Testers** - For valuable feedback and testing
- **Contributors** - For translations and improvements
- **Users** - For choosing our plugin!

### Technologies

- [WordPress](https://wordpress.org/) - CMS Platform
- [WooCommerce](https://woocommerce.com/) - eCommerce Platform
- [PHP](https://www.php.net/) - Backend Language
- [JavaScript](https://developer.mozilla.org/en-US/docs/Web/JavaScript) - Frontend Interactivity
- [CSS3](https://developer.mozilla.org/en-US/docs/Web/CSS) - Styling

---

## 📊 Stats

![GitHub stars](https://img.shields.io/github/stars/betatech/mix-match-bundle?style=social)
![GitHub forks](https://img.shields.io/github/forks/betatech/mix-match-bundle?style=social)
![GitHub issues](https://img.shields.io/github/issues/betatech/mix-match-bundle)
![GitHub pull requests](https://img.shields.io/github/issues-pr/betatech/mix-match-bundle)
![Downloads](https://img.shields.io/wordpress/plugin/dt/mix-match-bundle)
![Rating](https://img.shields.io/wordpress/plugin/rating/mix-match-bundle)

---

## 🔗 Links

<!-- - **Plugin Page:** [WordPress.org](https://wordpress.org/plugins/mix-match-bundle/) -->
- **Website:** [https://betatech.co](https://betatech.co)
- **GitHub:** [https://github.com/ashawkat/mix-match-bundle-for-woocommerce](https://github.com/betatech/mix-match-bundle)
- **Demo:** [https://demo.betatech.co/docs/mix-match-bundle](https://betatech.co/docs/mix-match-bundle)
- **Changelog:** [CHANGELOG.md](CHANGELOG.md)
- **Translations:** [languages/README.md](languages/README.md)

---

## 🌟 Show Your Support

If you find this plugin helpful, please:

- ⭐ **Star** this repository
- 🐛 **Report bugs** to help us improve
- 💡 **Suggest features** you'd like to see
<!-- - 📝 **Write a review** on [WordPress.org](https://wordpress.org/support/plugin/mix-match-bundle/reviews/) -->
- 🗣️ **Tell others** about the plugin
<!-- - ☕ **Buy us a coffee** [Donate](https://betatech.co/donate) -->

---

<div align="center">

**[⬆ Back to Top](#-mix--match-bundle-for-woocommerce)**

Made with ❤️ by [Betatech](https://betatech.co)

**Building better eCommerce solutions, one plugin at a time.**

</div>

