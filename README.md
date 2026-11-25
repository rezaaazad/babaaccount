# babaaccount-theme

 قالب و افزونه‌های اختصاصی سایت [babaaccount.ir](https://babaaccount.ir) (هوش‌مارکت / HooshMarket).

Private source for the WordPress theme and custom plugins deployed on **babaaccount.ir**. This is not a third-party theme: it is the live site code (HooshMarket — هوش‌مارکت), packaged for version control and later work in Codex/GPT.

## Versions (as copied from the live site)

| Component | Slug | Header name | Version |
|---|---|---|---|
| Theme | `aimarket` | HooshMarket — هوش‌مارکت | **1.9.5** |
| Plugin | `aimarket-core` | AIM Panel — پنل مدیریت سایت | **1.12.0** |
| Plugin | `aimarket-accounts` | AIM Panel — ماژول فروش اکانت | **1.8.1** |
| Plugin | `aimarket-reseller` | هوش‌مارکت — همکاری در فروش | **1.0.0** |

- Active theme is `aimarket` (not a child theme; no custom parent).
- Requires WordPress 6.5+, PHP 8.0+. The theme is built around Elementor widgets and WooCommerce (those two plugins are third-party and are **not** in this repo).
- Activating the theme can install/activate the bundled AIM Panel plugins from `theme/aimarket/inc/bundled/*.zip`. The copies in `plugins/` are the live installed plugins and should be treated as canonical.

## Layout

```
theme/aimarket/                 WordPress theme
  assets/                      CSS, JS, brand, icons, images
  inc/                         Theme PHP (Elementor, footer, setup, …)
  inc/bundled/                 Zip copies of the three plugins + versions.json
  template-parts/
  woocommerce/                 WooCommerce template overrides
plugins/aimarket-core/          Admin panel, OTP/SMS login, site settings
plugins/aimarket-accounts/      Encrypted account inventory and delivery
plugins/aimarket-reseller/      Reseller accounts, wallet, wholesale API
```

Install on a WordPress site as usual: copy `theme/aimarket` to `wp-content/themes/` and the three folders under `plugins/` to `wp-content/plugins/`, then activate WooCommerce, Elementor, the three plugins, and the theme.

## Notes

- Secrets (SMS API keys, `AIM_SECRET_KEY` in `wp-config.php`, payment gateway keys, account inventory) live in the database / wp-config, not in these files.
- Do not commit `node_modules`, uploads, or WordPress core.
