# SDB Product Configurator

A WordPress/WooCommerce plugin that adds two customer-facing shopping tools to any site:

1. **Product Configurator** — a step-by-step popup ("Tiers & Steps") that walks a customer through picking products (or booking a date) across multiple steps, then adds everything to the WooCommerce cart in one go.
2. **Product Range** (`[sdb_product_range]`) — a shortcode that shows a row of category icons above a paginated, AJAX-driven WooCommerce product grid, with quick-view popups and AJAX add-to-cart.

Both features are configured from a single admin screen and require no template editing.

## Requirements

- WordPress
- [WooCommerce](https://woocommerce.com/) (active) — the plugin shows an admin notice and does nothing until WooCommerce is active
- PHP 7.4+ recommended

## Installation

1. Copy this plugin folder into `wp-content/plugins/` (or zip it and upload via **Plugins → Add New → Upload Plugin**).
2. Activate **SDB Product Configurator** from the Plugins screen.
3. Make sure WooCommerce is installed and active.
4. Go to **Configurator** in the WordPress admin menu to set things up (see below).

## Admin settings

A top-level **Configurator** menu appears in wp-admin, with four tabs:

| Tab | What it's for |
|---|---|
| **General** | Sitewide text/labels used by the configurator popup (cart title, button labels, empty-cart message, etc.) |
| **Tiers & Steps** | Define one or more "tiers" (packages), each with an ordered list of steps. A step is either a product-selection step or a date-booking step. |
| **Product Range** | Pick which WooCommerce categories get a round icon in the `[sdb_product_range]` row, upload/override each icon and label, set the default category and products-per-page, and generate the shortcode to paste onto a page (no need to know category IDs — pick categories with checkboxes and copy the ready-made shortcode). |
| **How To Use** | In-admin usage notes. |

## Feature 1 — Product Configurator popup

Add `data-configurator="<tier-prefix>"` to any element (button, link, etc.) on the site — clicking it opens the configurator modal for that tier:

```html
<button data-configurator="basic">Configure your package</button>
```

The tier's steps are shown one at a time. Each step is either:

- a **product step** — customer picks quantities of one or more products, or
- a **booking step** — customer picks a date (and optionally an end date) for a bookable product.

When the customer finishes, every selected item is added to the WooCommerce cart via AJAX and they're taken to the cart.

## Feature 2 — Product Range shortcode

```
[sdb_product_range]
```

Renders a row of category icons (configured in **Configurator → Product Range**) above a paginated product grid. Clicking a category icon, a pagination link, or the "Reset filter" link refreshes the grid via AJAX — no page reload. Each product card has:

- an **info ("i") icon** that opens a quick-view popup (image, price, short description, add-to-cart) without leaving the page
- an **AJAX add-to-cart** button with a quantity field

### Shortcode attributes

| Attribute | Default | Description |
|---|---|---|
| `per_page` | value set in **Configurator → Product Range** | How many products per page in the grid |
| `default` | admin-configured default category | Category (slug or term ID) to show first, before any icon is clicked. Does not have to be one of the displayed icons — e.g. a catch-all "show everything" category works even without its own icon. |
| `categories` | *(empty = show all configured icons)* | Comma-separated category slugs or term IDs — restricts and orders which admin-configured icons appear on *this* instance of the shortcode, so different pages can show a different subset/order from the same master list. |

Example — a page that should only show two specific categories, in a specific order:

```
[sdb_product_range categories="headphones,cables-and-accessories" per_page="12"]
```

The easiest way to get a correct shortcode is **Configurator → Product Range**: tick the categories you want, and copy the shortcode string it generates.

## File structure

```
sdb-product-configurator/
├── sdb-product-configurator.php     Main plugin file — bootstraps everything
├── includes/
│   ├── class-sdb-configurator-settings.php   Admin screen: General / Tiers & Steps / Product Range / How To Use tabs
│   ├── class-sdb-configurator-ajax.php       AJAX endpoints for the Tiers & Steps popup
│   ├── class-sdb-product-configurator.php    Front-end bootstrap for the Tiers & Steps popup (enqueues assets, renders the modal)
│   ├── class-sdb-range-settings.php          Admin storage/UI for Product Range category icons + shortcode generator
│   ├── class-sdb-range-ajax.php              AJAX endpoints for the Product Range grid (paging, add-to-cart, quick view)
│   └── class-sdb-product-range.php           [sdb_product_range] shortcode + front-end bootstrap
├── templates/
│   ├── modal.php                    Markup for the Tiers & Steps popup
│   └── product-range.php            Markup for the Product Range shortcode output
└── assets/
    ├── css/
    │   ├── configurator.css         Styles for the Tiers & Steps popup
    │   └── product-range.css        Styles for the Product Range shortcode
    └── js/
        ├── configurator.js          Front-end logic for the Tiers & Steps popup
        └── product-range.js         Front-end logic for the Product Range shortcode (AJAX paging/filtering, quick view, add-to-cart)
```

## Notes for contributors

- All Product Range markup, CSS classes, and JS are namespaced under `sdbpr-` and are intentionally independent of the configurator popup's `sdbpc-`/`sdbpkp-` namespace, so the two features can't clash on the same page.
- The plugin degrades gracefully (renders nothing, or an admin-only hint) when WooCommerce isn't active or when a configured category has no products.
- No build step is required — CSS/JS are enqueued as-is.

## License

No license file is currently included. Add a `LICENSE` file (e.g. GPLv2-or-later, the standard for WordPress plugins) before publishing this repository publicly.
