=== Canvasly - Visual Page Builder ===
Contributors: canvasly-lite
Tags: page builder, drag-and-drop, landing page, website builder, responsive
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.12.80
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A visual WordPress page builder with nested containers, CSS Grid, templates, a design system, and responsive editing.

== Description ==

Canvasly is a visual page builder for WordPress. Design a page on a canvas, then publish it the usual WordPress way. The editor has a unit library, desktop, tablet, and mobile views, and a design system for colors, type, and reusable parts.

Canvasly keeps its own document model, unit API, editor, CSS and JavaScript, icon library, and REST endpoints. It stays out of the normal WordPress block editor except for the Canvasly Template block and an **Edit with Canvasly** launcher.

Start with the [Canvasly documentation](https://canvasly.pro/overview.html). Unlock theme parts, shop units, popups, form logging, hosted payments, and AI connectivity with **[Canvasly Pro](https://canvasly.pro/price.html)**.

### Create professional websites

- **[Visual drag-and-drop editor](https://canvasly.pro/blocks.html#how-to-add)**: Build a page from the unit library. Search or filter by category, then drop a unit into a Container, a Grid, or the page.
- **[Layout and style controls](https://canvasly.pro/settings.html)**: Typography, spacing, sizing, borders, shadows, positioning, visibility, CSS classes, custom CSS, ARIA attributes, and responsive settings. Image units add size, link, lightbox, caption, alt text, object fit, and object position.
- **[Templates](https://canvasly.pro/shortcodes.html)**: Save a layout and place it with `[canvasly_lite_template id="42"]`, `[canvasly_lite_template title="Homepage hero"]`, the Canvasly Template block, the Template unit, or the Canvasly Template sidebar widget.
- **[Free units](https://canvasly.pro/blocks.html)**: Layout, basic, media, content, and advanced units, including Heading, Image, Button, Gallery, Video, Tabs, Form, Price Table, and Collection Loop.
- **[Canvasly Pro](https://canvasly.pro/price.html#lite-vs-pro)**: Theme Builder, theme units, popups, extra form fields, shop units when WooCommerce is active, hosted payments, editor notes, and an AI connection with MCP.

= Key features =

- **[Design system](https://canvasly.pro/settings.html#design-system)**: Global colors, typography, variables, classes, and components. Classes support base, hover, focus, active, and focus-visible. Components expose properties and update every instance. Site kits export and import settings, tokens, classes, components, templates, and optional pages.
- **[Responsive design](https://canvasly.pro/overview.html)**: Edit desktop, tablet, and mobile on the canvas. Extra breakpoints can be turned on under Design System.
- **[CSS Grid](https://canvasly.pro/blocks.html#grid)**: Columns, rows, explicit tracks, gaps, auto-flow, dense placement, outlines, and per-child span and alignment. Drag an edge to resize; neighboring columns share the width.
- **Entrance and exit motion**: About 50 CSS presets, custom keyframes, and viewport, load, hover, click, focus, and scroll-progress triggers. Timing, per-breakpoint exclusions, reduced motion, and an editor preview are included. The interaction script loads only when a document uses it.
- **Dynamic tags**: Post, author, site, user, archive, and term values, with before, after, and fallback text. Previews resolve in the editor and on the front end. [Canvasly Pro](https://canvasly.pro/price.html#lite-vs-pro) adds request values, custom fields, ACF, and product price and SKU.
- **[History and revisions](https://canvasly.pro/blocks.html#context-menu)**: Undo, redo, an action history you can jump through, WordPress revisions with a canvas preview, and autosave recovery. Copy, paste, and paste-style work across pages and tabs.
- **[Settings, roles, and tools](https://canvasly.pro/settings.html)**: General, Integrations, Advanced, Performance, Tools, and Features. Units Manager and Role Manager control which units each role can use. Maintenance and Coming Soon, per-user Safe Mode, System Info, Replace URL, and rollback from stored plugin ZIPs live under Tools.
- **Developer surface**: REST routes under `canvasly-lite/v1` with capability checks and nonces. WP-CLI: `wp canvasly-lite regenerate-css`, `flush-cache`, `replace-url`, `import`, `export`, and `convert`.
- **Sticky, scroll, and page transitions [Pro](https://canvasly.pro/price.html#lite-vs-pro)**: Stick to the top or bottom, scroll opacity, slide, and scale, scroll snap, and page transitions.
- **[Theme Builder](https://canvasly.pro/blocks.html#pro-units) [Pro](https://canvasly.pro/price.html#lite-vs-pro)**: Header, footer, single, archive, search, 404, and section templates, with display rules.
- **[Popups](https://canvasly.pro/blocks.html#other-surfaces) [Pro](https://canvasly.pro/price.html#lite-vs-pro)**: Open on load, scroll, click, exit, or inactivity. A link can open a popup. Frequency uses a cookie set by the site.
- **Forms [Pro](https://canvasly.pro/price.html#lite-vs-pro)**: Lite sends one email. Pro adds number, date, radio, acceptance, and file fields, plus email, redirect, webhook, a submissions log, and CSV export.
- **[WooCommerce](https://canvasly.pro/blocks.html#pro-units) [Pro](https://canvasly.pro/price.html#lite-vs-pro)**: Product and product-archive templates, product units, menu cart, and notices. Cart, checkout, and My Account print WooCommerce’s own forms. Shop units load only while WooCommerce is active.
- **[Hosted payments](https://canvasly.pro/price.html#lite-vs-pro) [Pro](https://canvasly.pro/price.html#plans)**: Stripe, PayPal, Square, Razorpay, Mollie, and Authorize.net. Card data stays on the gateway.
- **[AI connection and MCP](https://canvasly.pro/ai-connectivity.html) [Pro](https://canvasly.pro/price.html#lite-vs-pro)**: Store a provider key and let an agent read the layout through WordPress. Saving a page still requires a WordPress user who can edit layouts.
- **Editor notes [Pro](https://canvasly.pro/blocks.html#other-surfaces)**: Notes on a unit for people who can edit the page. Notes are not printed on the site.
- **Custom code, fonts, and icons [Pro](https://canvasly.pro/price.html#lite-vs-pro)**: Site-wide snippets, uploaded font files, and extra icon sets. Lite includes per-unit CSS, Google Fonts, and the icon manager.

= Free units =

The full list, with how to use each one, is on the [units page](https://canvasly.pro/blocks.html).

**Layout**

- **Container**: The main box for a section. Semantic HTML tag, a link for the whole container, background overlay, shape dividers, and self-hosted background video. Container cannot be turned off.
- **Inner Section**: A nested layout region inside a Container.
- **Grid**: Rows, columns, tracks, gaps, and child placement.

**Basic**

- **Heading**: A title, with a link and size presets.
- **Text Editor**: Body text, with a drop cap and columns.
- **TinyMCE Text Editor**: The WordPress visual editor.
- **Button**: A link styled as a button, with sizes, icon placement, and alignment.
- **Divider**: A rule, with patterns and optional text or an icon.
- **Spacer**: Empty vertical space.
- **Icon**: One icon from the Canvasly icon library.
- **Icon List**: A list of items that each have an icon.
- **Icon Box**: An icon, title, and text.
- **Progress Bar**: A labeled bar with a value and inner text.
- **Counter**: A number that can count up, with prefix and suffix.
- **Alert**: A notice visitors can dismiss.
- **Accordion**, **Toggle**, and **Tabs**: Stacked or tabbed panels, including FAQ schema on Accordion.
- **Nested Tabs**, **Nested Accordion**, and **Nested Toggle**: Panels that hold any unit. On by default under Features.
- **Social Icons**: Profile links with brand colors, shapes, and a grid.
- **Star Rating**: Stars, including half stars.
- **Testimonial**: A quote with the person’s details.
- **Price Table**: A plan name, price, features, and button.
- **Text Path**: Text that follows a path.
- **Shortcode**: Runs any WordPress shortcode you paste.
- **HTML** and **Code**: Custom markup, or a preformatted code block.

**Media**

- **Image**: Media Library image, with lightbox, caption, alt text, and responsive sizing.
- **Image Box**: An image with a title and text.
- **Gallery**: Grid, masonry, or justified, with captions and hover effects.
- **Image Carousel**: Slides to show, fade, autoplay, and captions.
- **Video**: YouTube, Vimeo, Dailymotion, VideoPress, or a file you host.
- **Audio**: A hosted file or an allowed oEmbed URL.
- **SoundCloud**: A SoundCloud player.

**Content**

- **Form**: Fields and one email action. reCAPTCHA runs when Form reCAPTCHA is on and both keys are saved.
- **Read More**: A text link.
- **Rating**: A score made of icons.
- **Link in Bio**: A title, subtitle, avatar, and a list of links.
- **Site Menu**: A WordPress menu. The same output is `[canvasly_nav]`. See [shortcodes](https://canvasly.pro/shortcodes.html).
- **Menu Anchor**: A jump target. A sticky header can clear it with `--lb-anchor-scroll-offset`. The matching shortcode is `[canvasly_anchor]`.
- **Collection Loop**: Posts, custom types, or terms, with an item layout and numbered, previous-next, or load-more pagination.

**Advanced**

- **Embed**: An allowed URL through WordPress oEmbed.
- **Google Maps**: An address. With an API key and the Maps feature, it uses the Embed API.
- **Sidebar** and **WordPress Widget**: A registered widget area, or one registered widget.
- **Template** and **Component**: A saved template, or a reusable component.
- **Flip Box**: A card with a front and a back.
- **Login**: The WordPress login form.

### Faster pages

Canvasly prints CSS for the units a page uses, and it can write that CSS to files under `uploads/canvasly-lite/css` with a hash in the file name. Details are under [Settings → Performance](https://canvasly.pro/settings.html#performance) and [Settings → Advanced](https://canvasly.pro/settings.html#advanced).

- **External or inline CSS**: External files by default, or the same minified CSS printed on the page.
- **Fonts**: Only the Google Font weights and styles a document uses, with `font-display` and optional self-hosting under `uploads/canvasly-lite/fonts`.
- **Unit cache**: HTML for units that do not use dynamic tags, with a time limit and a flush control.
- **Images**: The first image uses `fetchpriority="high"`. Later images and background images can load lazily.
- **Optimized markup**: An optional mode that drops an extra wrapper on simple units.
- **Scripts**: A unit’s scripts and styles load only when that unit is on the page.

### Canvasly Pro

[Canvasly Pro](https://canvasly.pro/price.html) is a second plugin. It loads only while Canvasly is active and at least version 0.12.73. Every annual plan unlocks the same features. The plans differ by site count: [$59 for 1 site, $79 for 5, $129 for 25, and $199 for 100](https://canvasly.pro/price.html#plans). Without a valid key, new Pro units are dropped on save.

**Pro content units** ([full list](https://canvasly.pro/blocks.html#pro-units)):

1. **Share Buttons**: Share the current page.
2. **Blockquote**: A styled quotation.
3. **Call to Action**: A prompt with a button.
4. **Animated Headline**: A headline that changes.
5. **Countdown**: A timer toward a date.
6. **Slides**: A slide deck.
7. **Nested Carousel**: Slides that hold other units.
8. **Media Carousel**: A carousel of media items.
9. **Table of Contents**: Links to headings on the page.
10. **Progress Tracker**: How far the visitor has scrolled.
11. **Hotspot**: An image with clickable points.
12. **Price List**: Named prices.
13. **Off-Canvas**: A panel that opens from the edge of the page.
14. **Floating Buttons**: Buttons that stay on the screen.
15. **Search**: A search box.
16. **Lottie**: An animation file.
17. **Video Playlist**: Several videos in one list.
18. **Code Highlight**: Code with highlighting.
19. **Nav Menu**: A Pro navigation menu, including a mega menu from a section template.
20. **Taxonomy Filter**: Filters a Collection Loop by taxonomy.
21. **Payment Button** and **Payment Form**: Hosted checkout. Tax and fees are under Canvasly → Payments.

**Pro theme units**

Use these inside [Theme Builder](https://canvasly.pro/blocks.html#pro-units) templates so the text comes from the current page, post, or site.

1. **Site Logo**, **Site Title**, and **Site Tagline**
2. **Page Title** and **Post Title**
3. **Post Excerpt** and **Post Content**
4. **Post Info**
5. **Featured Image**
6. **Author Box**
7. **Post Navigation**
8. **Comments**
9. **Archive Title** and **Archive Posts**
10. **Breadcrumbs**
11. **Search Form**
12. **Sitemap**

**Pro shop units**

These load only while WooCommerce is active.

1. **Products** and **Archive Products**
2. **Product Title**, **Product Image**, **Product Price**, **Product Rating**, and **Product Stock**
3. **Product Meta**, **Product Short Description**, **Product Content**, and **Product Data Tabs**
4. **Add To Cart**, **Product Related**, and **Product Upsells**
5. **Shop Notices** and **Menu Cart**
6. **Cart**, **Checkout**, **My Account**, and **Purchase Summary**

Build the rest of the site with **[Canvasly Pro](https://canvasly.pro/price.html)**.

= Security =

Canvasly checks WordPress capabilities on admin screens and REST routes, validates nonces, and sanitizes and escapes stored content. SVG uploads go through sanitization. reCAPTCHA secret keys are not sent to the browser. Pro API keys are encrypted at rest, and the editor never receives them. See [AI connectivity](https://canvasly.pro/ai-connectivity.html) for how provider hosts and MCP tokens are limited.

= Accessibility =

Units use HTML you can label, including alt text, ARIA attributes, and semantic tags on Container. The editor includes checks for common image, heading, and button issues. Motion respects reduced-motion preferences. Menus from the Site Menu unit expose an accessible name.

= Translations =

Canvasly ships a `canvasly-lite` text domain (`languages/canvasly-lite.pot`). Editor strings can be translated with the usual WordPress translation tools. RTL layouts follow the site language.

= Third-party services =

These run only when you turn the related feature on:

- **Google Fonts** load when a document uses a Google font family and self-hosting is off. Google’s [Terms of Service](https://policies.google.com/terms) and [Privacy Policy](https://policies.google.com/privacy) apply. You can download the files and serve them from the site under [Settings → Advanced](https://canvasly.pro/settings.html#advanced).
- **Google Maps** uses the Maps Embed API when you save an API key and enable the Maps feature. Otherwise the map uses the public Google Maps iframe. The same Google policies apply.
- **reCAPTCHA** (Google) runs on forms when Form reCAPTCHA is on and both keys are saved.
- **oEmbed** uses WordPress `wp_oembed_get` for Video and Audio fallbacks, lone-URL HTML units, and the Embed unit. Results are cached. Only allowed hosts are requested.
- **Canvasly Pro AI providers** are called only when an administrator presses Test connection on [AI Connection](https://canvasly.pro/ai-connectivity.html). Supported hosts are OpenAI, Anthropic, Gemini, Azure OpenAI, Perplexity, Cursor, Groq, Mistral, and DeepSeek. MCP traffic stays on your WordPress site and is not forwarded to those providers.

= Related =

**[Canvasly Pro](https://canvasly.pro/price.html)**: Theme Builder, popups, shop units, hosted payments, submissions, and AI connectivity (MCP). It does not replace Canvasly.

Questions about install, settings, or a page that will not open: [Contact support](https://canvasly.pro/support.html). Common answers are in the [FAQ](https://canvasly.pro/faq.html).

== Installation ==

= Minimum requirements =

* WordPress 6.9 or greater
* PHP 7.4 or greater
* MySQL 5.7 or MariaDB 10.3 or greater

= Recommended =

* WordPress 6.9 or greater, tested up to 7.1
* PHP 8.1 or greater

Shop units appear only while WooCommerce is active. [Canvasly Pro](https://canvasly.pro/installation.html) will not start if Canvasly is missing, switched off, or older than 0.12.73.

= Installation =

1. Install using **Plugins → Add New → Upload Plugin**, or place the plugin folder in `wp-content/plugins/`.
2. Activate **Canvasly** on the Plugins screen.
3. Open a page or post and choose **Edit with Canvasly**. The same link is in the admin bar.
4. Drag units from the left library onto the canvas, then save. Publish or update the page in WordPress.

Step-by-step install for Canvasly and Canvasly Pro, including the license screen, is in the [installation guide](https://canvasly.pro/installation.html). Settings for post types, CSS, fonts, maps, and performance are in the [settings guide](https://canvasly.pro/settings.html).

== Frequently Asked Questions ==

= How do I install Canvasly? =

From the WordPress dashboard go to Plugins → Add New → Upload Plugin, upload the Canvasly zip, then Activate. You can also copy the plugin folder into `wp-content/plugins/`. The [installation guide](https://canvasly.pro/installation.html) covers Canvasly Pro as well.

= What does Canvasly require? =

WordPress 6.9 or later and PHP 7.4 or later. The plugin is tested up to WordPress 7.1. Canvasly Pro also needs Canvasly 0.12.73 or newer.

= How do I edit a page? =

Open the page and choose **Edit with Canvasly**, or use the admin-bar link. Posts and Pages work as soon as you activate the plugin. Other public types stay off until you enable them under Canvasly → Settings → General.

= Where are the settings? =

[Canvasly → Settings](https://canvasly.pro/settings.html) covers post types, maps, reCAPTCHA, CSS, fonts, performance, tools, and experiments. Colors, type, and breakpoints are under Design System.

= Does Canvasly work with my theme and with Gutenberg? =

On the normal WordPress editing screen, Canvasly does not load its full editor. The Canvasly Template block and the Edit with Canvasly launcher still appear. You can turn off Canvasly’s default colors and fonts so the theme keeps those. See the [FAQ](https://canvasly.pro/faq.html).

= Do I need to know how to code? =

No. The free unit library, templates, and design system cover a typical page. Custom CSS, HTML, and the Code unit are there when you want them.

= Can I reuse a section on another page? =

Right-click a Container or Grid and choose Save as Template. Place it later with the Template unit, `[canvasly_lite_template id="42"]`, the Canvasly Template block, or the Canvasly Template sidebar widget. Attributes are listed on the [shortcodes page](https://canvasly.pro/shortcodes.html).

= Why is a unit missing from the library? =

Units Manager can disable a type or hide it from your role. Nested Tabs, Accordion, and Toggle, Collection Loop, and Grid container can also be switched off under Settings → Features. Shop units are absent unless WooCommerce is active. Pro units are absent unless [Canvasly Pro](https://canvasly.pro/price.html) has started.

= Will Canvasly slow down my site? =

CSS and scripts load for the units on the page. You can print CSS as external files, cache non-dynamic unit HTML, lazy-load images, and self-host Google Fonts. See [Performance](https://canvasly.pro/settings.html#performance).

= How do I move the site to a new address? =

Use Canvasly → Settings → Tools → Replace URL. Run Dry run first. The replacement cannot be undone from that screen.

= What is the difference between Canvasly and Canvasly Pro? =

Canvasly is the free page builder: the visual editor, the free unit library, the design system, entrance and exit motion, page templates, the collection loop, and basic dynamic tags. [Canvasly Pro](https://canvasly.pro/price.html#lite-vs-pro) adds Theme Builder, popups, shop units, hosted payments, extra form actions, editor notes, and the AI connection. Every Pro plan includes the same features.

= The editor looks broken. What should I try? =

Under Settings → Advanced, set Editor loader to Iframe. Under Settings → Tools, turn on Safe mode for your account. Safe mode loads the editor without other plugins and without the theme. More answers are in the [FAQ](https://canvasly.pro/faq.html).

= Where do I get help? =

Read the [documentation](https://canvasly.pro/overview.html) or [contact support](https://canvasly.pro/support.html).

== Screenshots ==

1. **Visual editor** - Unit library on the left, the page canvas in the center, and Content, Style, and Advanced on the right.
2. **Responsive editing** - Switch the canvas between desktop, tablet, and mobile.
3. **CSS Grid** - Place children in rows and columns, span cells, and resize by dragging an edge.
4. **Design system** - Global colors, typography, classes, variables, and components.
5. **Templates** - Save a section and embed it with a shortcode, a block, a unit, or a sidebar widget.
6. **Canvasly Pro** - Theme Builder, popups, shop units, and the annual plans.

== Changelog ==

= 0.12.80 =
* Plugin header name matches the readme title: Canvasly - Visual Page Builder.

= 0.12.77 =
* Styles and scripts load through WordPress enqueue APIs, and only on the screens that use them.

= 0.12.76 =
* The Canvasly dashboard shows the Canvasly Pro annual price table when Pro is active.

= 0.12.74 =
* Menu Anchor shows its target on the canvas and scrolls the page to that ID. A sticky header can clear the target with the `--lb-anchor-scroll-offset` custom property.

= 0.12.73 =
* Header & Footer theme templates open both regions on one canvas.

= 0.12.72 =
* Container and widget mouse resize: grab left, right, top, or bottom; adjacent columns share width; size tooltip while dragging.

= 0.12.71 =
* PHPUnit (wp-env) tests for DocumentManager sanitization/migration, Style CSS output and REST permissions, Playwright editor flows (add, drag, responsive, save, undo, template insert), and a GitHub Actions CI workflow (Roadmap 7.7).

= 0.12.70 =
* WP-CLI commands (`wp canvasly-lite regenerate-css`, `flush-cache`, `replace-url`, `import`, `export`, `convert`) and an admin-bar Edit with Canvasly Lite node on the frontend and back end (Roadmap 7.6).

= 0.12.69 =
* Versioned upgrade framework (`Upgrades::run()` on plugin version change) with background-batched document migrations, a rotating logger under uploads/canvasly-lite/logs, and admin notices for failed upgrades (Roadmap 7.4).

= 0.12.68 =
* Maintenance / Coming Soon mode (saved template, role exclusions, HTTP 503 vs 200), per-user Safe Mode (editor loads without other plugins or the theme), System Info report + download, and plugin version rollback from stored ZIPs (Roadmap 7.3).

= 0.12.67 =
* Element Manager and Role Manager: enable or disable each element, restrict elements per role, usage counts, and role access of no access / content only / full. Design-system saves use `canvasly_lite_design` instead of `manage_options` (Roadmap 7.2).

= 0.12.66 =
* Admin settings screens (General, Integrations, Advanced, Performance, Tools, Features) with a single sanitized settings API, capability checks, REST `/settings`, Maps/reCAPTCHA keys, experiments, and editor iframe loader (Roadmap 7.1).

= 0.12.65 =
* oEmbed via wp_oembed_get with a host allow-list and transient cache for Video and Audio fallbacks, lone-URL HTML widgets, and a new Embed element (Roadmap 6.5).

= 0.12.64 =
* Elements declare `scripts()`/`styles()` WordPress handles; the renderer enqueues only assets the page uses. `CanvaslyLiteFrontend.registerHandler(type, fn)` runs for first paint and AJAX-inserted content (Roadmap 6.4).

= 0.12.63 =
* Element fragment cache for non-dynamic widgets (TTL and invalidation), lazy-loaded background images below the first one, fetchpriority=high on the first image with loading=lazy after, and an optional optimized-markup mode that removes extra node wrappers (Roadmap 6.3).

= 0.12.62 =
* Weight-aware Google Fonts loading (only used weights and italic styles), a font-display setting, preconnect hints, and optional local font hosting under uploads/canvasly-lite/fonts (Roadmap 6.2).

= 0.12.61 =
* External CSS files at uploads/canvasly-lite/css (global.css and post-{id}.css) with hash cache busting, a css_print_method setting (external or inline), minification, and a Regenerate CSS tool (Roadmap 6.1).

= 0.12.60 =
* Replace URL tool on Canvasly Lite → Tools (document JSON + CSS cache, dry run). WordPress importer/exporter keeps JSON meta valid, and post-duplication plugins copy Canvasly Lite document data (Roadmap 5.4).

= 0.12.59 =
* Convert stored layout JSON into Canvasly Lite documents: widget mapping table, sections/columns → containers, responsive breakpoint keys, global color binds, dry-run report, and a bulk tool on Canvasly Lite → Tools (Roadmap 5.3).

= 0.12.58 =
* Template shortcode `[canvasly_lite_template id=""]`, Gutenberg block (`canvasly-lite/template`) with picker and preview, Template widget, and a WordPress sidebar widget (Roadmap 5.2).
* Embedded templates enqueue their CSS, fonts and frontend scripts, with recursion protection and scoped selectors.

= 0.12.57 =
* Counter Number, prefix and suffix now update the canvas digits live while editing settings.

= 0.12.56 =
* Interactions 2.0: about 50 CSS entrance/exit presets, custom keyframes, viewport/load/hover/click/scroll-progress triggers, timing, per-breakpoint exclusions, reduced-motion handling, and an editor preview (Roadmap 4.4).
* Schema 2.8 migrates the legacy single `interaction` setting into the per-node `interactions` list.

= 0.12.55 =
* Collection Loop widget: query builder for posts/CPTs and terms, inline or saved-template item layouts, grid/list, and numbers / previous-next / load-more pagination (Roadmap 4.3).
* Term dynamic tags (name, description, URL, count) resolve inside a term loop.

= 0.12.54 =
* Dynamic tags no longer fatal when `Tag` is missing; the registry loads `class-tag.php` from the same directory.
* The plugin no longer defines `CANVASLY_LITE_DEV_MODE`, so a late `wp-config.php` define is not a PHP 9 error.

= 0.12.53 =
* Dynamic tags framework: extensible registry, per-control toggle with before/after/fallback, built-in post/site/user/archive tags, editor previews and frontend resolution (Roadmap 3.8).

= 0.12.52 =
* Action-level History panel with jump-to for add, move, edit, delete and related edits (Roadmap 3.5).
* Document snapshots are stored as WordPress revisions (legacy `_lb_document_revisions` meta is migrated). Preview a revision on the canvas before restore. Autosave uses the WordPress autosave revision.

= 0.12.51 =
* Persistent editor clipboard in `localStorage` so Copy / Paste / Paste Style / Copy All / Paste All work across pages and tabs (Roadmap 3.4).
* Clipboard payloads are schema-tagged and migrated on paste. Reset Style remains on the context menu and shortcuts.

= 0.12.50 =
* Keyboard shortcuts are a complete, rebindable map (save, paste style, navigator, responsive, library, history, preview, delete, Escape) with a cheat-sheet dialog (`Ctrl+?`) (Roadmap 3.3).
* Finder (`Ctrl+E`) searches pages, templates, components, classes, settings panels and editor actions.

= 0.12.49 =
* Code editor control (`code`) using WordPress CodeMirror (`wp.codeEditor`) for HTML, CSS and JS (Roadmap 3.2).
* HTML and Code elements, element Custom CSS, page Custom CSS, and Global Class CSS use the code editor.

= 0.12.48 =
* Visual group controls: slider with unit switcher, typography, border, background (classic/gradient/video/slideshow + overlay), box/text shadow, CSS filters, transform, transition, linked dimensions, choose icon groups, and gaps (Roadmap 3.1).
* Free-text transform, filter, background gradient, text shadow and transition values migrate to structured objects (document schema 2.6).

= 0.12.47 =
* Site kit export/import: ZIP packages of site settings, design tokens, theme style, classes, components, saved templates and optional pages with media, using merge or replace (Roadmap 2.4).
* Tools screen under Canvasly Lite and a Kit tab in Site Settings. REST endpoints at `/canvasly-lite/v1/kit`.

= 0.12.46 =
* Site Settings Layout tab: content width, widgets space, page title selector, default template, site identity (WordPress title/tagline/logo/favicon), global lightbox, and site background (Roadmap 2.3).
* Identity fields read and write WordPress core options. Lightbox captions can use alt, caption, or title.

= 0.12.45 =
* Theme Style in Site Settings: site-wide defaults for body text, H1–H6, links (normal/hover), buttons, images and form fields, compiled into the global CSS and previewed live on the canvas (Roadmap 2.2).
* Color fields in Theme Style can bind to global color tokens.

= 0.12.44 =
* Custom post type support: an enabled-post-types setting (default Posts and Pages) replaces hard-coded `post`/`page` checks so any public CPT can be edited with Canvasly Lite (Roadmap 1.4).
* "Edit with Canvasly Lite" now appears on every enabled post type's list table and in the block editor.

= 0.12.43 =
* Editor JavaScript is now built from ES modules under `src/editor/` with esbuild (`npm run build`). The bundled `assets/js/editor.js` is still shipped so the plugin runs without Node.
* No editor behaviour change: this is a source split and build step only (Roadmap 0.5).

= 0.12.28 =
* Widget depth: Accordion / Toggle (multi-item, icons, FAQ schema), Tabs (vertical, alignment, styles), Alert (dismiss), Video (YouTube / Vimeo / Dailymotion / VideoPress / self-hosted, overlay, lightbox, aspect ratio), Counter (count-up animation), Progress (styles, inner text), Divider (patterns, text / icon), Icon and Icon Box (stacked / framed views), Image Box, Icon List (inline, dividers), Social Icons (brand colours, shapes, grid), Star Rating (SVG, half stars), Testimonial, Heading (link, size presets), Text (drop cap, columns), Button (sizes, icon placement, alignment), Image Carousel (slides-to-show, fade, autoplay, captions), Gallery (captions, hover effects), SoundCloud (visual mode, player toggles).
* Container: semantic HTML tag, whole-container link, background overlay with blend mode, top / bottom shape dividers, self-hosted background video.
* WordPress Widget: choose any registered widget from a list; Sidebar picks from registered widget areas.
* Editor: element-aware select options, icon picker for every icon control, media picker for every image control, live previews for all widgets, frontend stylesheet loaded in the canvas, legacy single-panel Accordion / Toggle documents migrate to the items list.
* Frontend: script now loads whenever a page uses a script-driven widget; button colours / borders apply to the button itself instead of its wrapper.
= 0.12.27 =
* Element library: right-click on an element card again shows Add to Favorites / Remove from Favorites (the panel re-binding layers were dropping the listener).
* Image: visible resize handles now sit on the picture itself (right edge, bottom edge, corner). The image resizes live while dragging, keeps its aspect ratio (Shift on the corner stretches freely), shows its size, and saves % of the container (or px if the width was already px) for the current device.

= 0.12.26 =
* Frontend: a saved image width is now applied once (it was applied to both the wrapper and the image); per-device {desktop,tablet,mobile} width/height values now output their desktop value, so resizing on Tablet/Mobile no longer drops the Desktop width.
* Grid: a plain number in Rows (e.g. 3) now produces that many rows on the frontend; responsive gap values no longer collapse to 1px.
* Asset cache-busting version now follows the plugin version.

= 0.10.0 =
* Production Feature Depth release: Atomic element library, responsive design controls, typography/design controls, dynamic content bindings, design-system import/export, template library improvements, native form element, richer accessibility and editor workflows, and expanded Grid/media/WordPress integration foundations.

= 0.9.6 =
* Fixed isolated-canvas navigation caused by Button/Link activation.
* Editor Buttons now render as non-navigating editing controls.
* Added capture-phase navigation guards for anchors and interactive elements.
* Prevented Enter/Space activation from navigating the editor iframe.


= 0.9.2 =
* Fixed isolated editor iframe mounting so the canvas is populated after each render.
* Restored drag-and-drop from the Elements panel into the iframe canvas.
* Restored node selection, nesting, reordering, duplicate/delete handles and context-menu behavior inside the iframe.
* Added element type metadata to canvas nodes for reliable drop targeting.



= 0.8.4 =
* Element library single-click no longer inserts elements. Double-click inserts an element by click, while drag-and-drop inserts exactly once.

= 0.9.1 =
* Added an isolated editor iframe canvas so theme/editor CSS cannot directly contaminate the editing surface.
* Expanded responsive sizing, spacing, visibility and breakpoint-aware frontend CSS.
* Expanded Grid with explicit tracks, dense auto-placement, auto rows/columns and per-child placement controls.
* Added a reusable universal control layer for layout, effects, interactions, accessibility and advanced attributes.
* Added registered WordPress sidebar discovery, Media Library metadata/srcset/sizes support and richer image delivery.
* Added component versioning, exposed-property metadata and instance override propagation.
* Expanded Global Classes with pseudo-state CSS and usage tracking; expanded Variables and Design System export.
* Expanded Template Library with duplicate/delete workflows and document schema 2.1 migrations.
* Added accessibility audit, context menu, element handles, icon manager, site navigation, performance/asset manager and collaboration lock heartbeat.
* Added server-side autosave recovery, revision restoration, import/export and asset cache invalidation foundations.

= 0.9.0 =
* Added deeper Image content, media, lightbox, caption, accessibility, styling and responsive controls.
* Added advanced CSS Grid tracks, dense auto-flow, grid outline, and per-child placement/span controls.
* Added document schema 2.0, JSON import/export, and recovery autosave snapshots.
* Added responsive breakpoint manager and registered WordPress sidebar selection.
* Improved reduced-motion behavior and frontend image rendering.

= 0.10.1 =
* Complete Font Awesome Free icon library integration with searchable family filters (Solid, Regular, Brands), SVG previews, and custom SVG support.
* Icon, Icon Box, Icon List, and Button icon rendering now use real SVG icon artwork instead of text identifiers.
* Google Fonts catalog integrated into Style > Typography & Design > Font Family with 1,600+ supported Google font names.
* Selected Google Fonts are loaded on demand in the editor and frontend.


= 0.10.2 =
* Fixed Style > Typography > Font Family so the Google Fonts selector is populated directly in the editor.
* Added a native dropdown containing the bundled Google font family catalog.
* Fixed Google Fonts CSS2 URL construction for selected fonts.


= 0.10.3 =
* Fixed Style > Typography > Font Family so the primary control is a native Google Fonts dropdown.
* Populates the selector from Canvasly Lite's bundled Google Fonts catalog without requiring a network request to display the font list.
* Selecting a font updates the document and loads the selected Google Font for the editor preview.
* Added an explicit editor asset version bump to reduce stale browser/cache loading after upgrades.


= 0.10.4 =
* Fixed Font Family initialization so new Heading/Text elements immediately show Default in a real Google Fonts dropdown.
* Corrected plugin version constant and asset versioning to 0.10.4.

= 0.10.6 =
* Added a native WordPress-powered "TinyMCE Text Editor" element.
* Uses WordPress editor/TinyMCE assets via wp_enqueue_editor() and wp.editor.initialize().
* Double-clicking the element opens a stable TinyMCE editing dialog; single-click continues to select the element.
* Rich HTML is stored in the Canvasly Lite document and rendered as frontend HTML after WordPress sanitization.
* Added a focused Content panel action, TinyMCE toolbar, and editor lifecycle cleanup.

= 0.11.0 =
* Added Atomic Design System 2.0 with richer Global Classes, Variables and Components workflows.
* Global Classes now support editable base/hover/focus/active/focus-visible declarations, inheritance, descriptions, assignment, usage visibility and reliable frontend propagation.
* Variables now support built-in design tokens plus custom token groups and CSS custom property output.
* Added CSS-first class assignment and token references such as {{var:colors.primary}}.
* Components now support source editing, structure editing, exposed properties, per-instance overrides, versioning, duplication, deletion and automatic propagation to all instances.
* Design System import/export now includes classes, variables, components, global settings and atomic metadata.
* Atomic metadata expanded and integrated into the document model and editor.

= 0.11.1 =
* Completed Atomic Design System 2.1 workflows for Classes, Variables, Components, exposed properties, propagation, inheritance, CSS-first styling and design-system import/export.
* Added editor-side component previews and synchronized component structure editing.
* Added custom variable deletion and typed exposed component properties.
* Improved TinyMCE Text Editor Duplicate/Delete controls for readability and accessibility.


= 0.11.4 =
* Fixed TinyMCE Text Editor initialization with a native TinyMCE fallback and clearer diagnostics.
* Added Page > Add New Page workflow that creates a draft and opens it directly in Canvasly Lite instead of the WordPress block editor.

= 0.11.5 =
* Fixed TinyMCE Text Editor action binding after Settings panel re-renders.
* Fixed WordPress Dashboard Pages > Add New to open directly in Canvasly Lite instead of the block editor.
* Added safe draft-page creation for dashboard page creation.

= 0.11.9 =
* Isolates native WordPress post/page Gutenberg screens from Canvasly Lite runtime hooks.
* Adds a safe Edit with Canvasly Lite launcher after the native editor renders.

= 0.8.1 =
* Core feature-depth and stabilization release.
* Expanded responsive controls, typography, spacing, borders, shadows, positioning and advanced attributes.
* Added Grid, global classes, variables, reusable components, favorites, icon library, template library UI and site navigation.
* Added revision browser/restore UI, interaction triggers, accessibility checks and frontend asset improvements.
* Expanded Gallery, Carousel, Button, Image, Heading, Text, Icon, Icon Box, Video, Accordion, Tabs and Social controls.
* Requires WordPress 6.9 or later and PHP 7.4 or later.

= 0.8.0 =
* Stabilization and feature-depth release toward Core feature parity.
* Expanded responsive controls, typography, spacing, sizing, borders, shadows, positioning, visibility and advanced attributes.
* Added Grid depth, legacy Inner Section compatibility, global classes, variables, components, favorites and site navigation.
* Expanded icon library, template library UI, revision browser, gallery lightbox, carousel controls and interaction behavior.
* Added accessibility helpers and performance-conscious frontend asset handling.

== Upgrade Notice ==

= 0.12.74 =
Menu Anchor now shows its jump target on the canvas and supports a scroll offset for sticky headers.

wordpress builder plugin github"
"open source elementor alternative github"
"canvasly wordpress plugin source code"
#wordpress-plugin 
#page-builder
#wordpress-builder
#gutenberg
