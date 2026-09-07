---
name: wordpress-theme-development
description: >
  Skill specialized in architecture, creation, maintenance, review, and
  optimization of modern WordPress themes. Prioritizes Block Themes,
  theme.json, the Interactivity API, the Block Bindings API, security,
  accessibility, performance, internationalization, WordPress Coding
  Standards, and modern PHP compatible with the WordPress ecosystem. All
  generated code, comments, and identifiers must be in English unless the
  user explicitly requests otherwise.
version: "1.2.0"
last_verified: "2026-09-07"
wordpress_reference: "7.1"
php_reference: "8.5.10"
theme_json: "3"
language: "en"
---

# WordPress Theme Development Skill

> Version note: the reference versions above were verified on 2026-09-07.
> WordPress 7.1 was released on 2026-08-19; a 7.1.1 maintenance release is
> planned for 2026-09-17. Before starting a new project, revalidate these
> numbers in section 3 — never assume they are still the latest.

## Quick summary (read this first)

For quick lookups during development, without reading the entire skill:

```text
0. Language rule: ALL output — code, comments, identifiers, commit messages,
   docblocks, strings meant for developers — is written in English by
   default, even if the conversation with the user is in another language.
   Only deviate when the user explicitly says otherwise (section 2).
1. Block Theme + theme.json is the default. Classic theme only with justification
   (section 5).
2. theme.json is the source of truth for design tokens; don't duplicate it in
   loose CSS (sections 8-9).
3. Preferred order for solving problems: Core > theme.json > Core Blocks >
   Patterns > Block Styles > Template/Part > CSS > PHP hooks > JS > external
   dependency (section 72).
4. Plugin territory: CPTs, business taxonomies, SEO, analytics, forms,
   payments, and business rules do NOT belong in the theme (section 19).
5. Security always: validate input, sanitize, escape output late, check
   nonce + capability, never concatenate SQL (sections 20-25).
6. Block/pattern interactivity: prefer the Interactivity API over loose
   vanilla JS or external frameworks (section 44). Simple data binding to a
   native block: use the Block Bindings API instead of a custom block
   (section 45).
7. Accessibility and performance are architectural requirements, not a final
   step (sections 27, 32).
8. functions.php is a bootstrap file; logic lives in inc/*.php with its own
   prefix (sections 16-17).
9. Before finishing, run the checklists in sections 66-70.
```

# 1. Purpose

This skill should be used whenever the task involves:

- creating a WordPress theme;
- modifying a WordPress theme;
- creating a Block Theme;
- creating or maintaining a classic theme;
- creating a child theme;
- developing templates;
- developing template parts;
- creating patterns;
- configuring `theme.json`;
- developing `functions.php`;
- CSS for WordPress themes;
- JavaScript used by the theme;
- integrating the theme with Gutenberg;
- Site Editor compatibility;
- block/pattern interactivity with the Interactivity API;
- binding dynamic data to native blocks with the Block Bindings API;
- theme performance optimization;
- accessibility;
- internationalization;
- theme code review;
- preparing themes for production;
- preparing themes for WordPress.org.

The main objective is to produce themes that are:

1. secure;
2. performant;
3. accessible;
4. compatible with WordPress;
5. compatible with Gutenberg;
6. easy to maintain;
7. extensible;
8. internationalizable;
9. aligned with native WordPress APIs;
10. with as few external dependencies as possible.

---

# 2. Language policy

**This rule applies to everything Claude (or any AI assistant using this
skill) produces while working on a WordPress theme, regardless of the
language used in the conversation with the user.**

By default, always write in English:

```text
Code (PHP, JS, CSS, JSON, HTML).
Comments and docblocks.
Function, class, method, and variable names.
Hook names, filter names, action names.
Constant names and option/meta keys.
Commit messages.
File and folder names.
README and CHANGELOG content (unless the project explicitly requires
  another language for end users, e.g. a translation-ready readme.txt
  section aimed at a non-English WordPress.org locale).
Error messages and log messages intended for developers.
```

Exceptions — only deviate from English when:

- the **user explicitly asks** for another language for a specific
  artifact (e.g. "write the README in Portuguese", "the admin-facing label
  should be in Spanish");
- the content is **user-facing, translatable string content** whose actual
  runtime language depends on the site's locale — in that case the
  **source string** (the string wrapped in `__()`, `_e()`, etc.) is still
  written in English, because that is the WordPress i18n convention: the
  English string is the default/source text, and translation happens via
  `.pot`/`.po`/`.mo` files, not by hardcoding another language into the
  source;
- the project's existing codebase already establishes a different
  convention (e.g. a legacy theme where all comments are in another
  language) — in that case, follow the existing convention for consistency
  within that file, but flag the inconsistency to the user rather than
  silently perpetuating it in new files.

This does not change the language of the conversation itself: replies to
the user should still be written in the language the user is using. This
rule is only about the artifacts produced (code, comments, identifiers,
file content).

---

# 3. Reference versions

Last verification of this skill:

- WordPress: `7.1`
- Stable PHP: `8.5.10`
- `theme.json`: version `3`
- Verification date: `2026-09-07`

## Compatibility policy

For new projects:

### Recommended runtime

```text
WordPress 7.1+
PHP 8.5+
```

### Default minimum compatibility

When there is no specific project requirement:

```text
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.3
```

Although PHP 8.5 is the reference version, avoid using PHP 8.5-exclusive
features when they bring no real benefit to the theme.

This allows the code to run on:

```text
PHP 8.3
PHP 8.4
PHP 8.5
```

If the project explicitly requires PHP 8.5, version-specific features can
be used.

Never use unsupported PHP versions in new projects.

---

# 4. Version updates

Before starting a new project, when internet access is available, check:

1. the current stable WordPress version;
2. the current stable PHP version;
3. the PHP versions officially supported by WordPress;
4. the current `theme.json` schema version;
5. recent changes in the Theme Handbook;
6. WordPress Developer Notes for the current version;
7. Theme Review Requirements.

Prioritize official sources:

```text
wordpress.org
developer.wordpress.org
make.wordpress.org
php.net
```

Never assume the numbers recorded in this skill will remain the most
recent.

---

# 5. Standard architecture

## Priority

For new themes, preferably use:

```text
Block Theme
+ Site Editor
+ theme.json
+ HTML templates
+ template parts
+ patterns
```

Do not create a classic theme by default.

Create a classic theme only when:

- a legacy project requires it;
- an essential plugin is not compatible with Block Themes;
- the product architecture explicitly requires PHP templates;
- there is a proven technical requirement.

A hybrid theme can be considered when there is a clear benefit.

---

# 6. Recommended Block Theme structure

Use as a starting point:

```text
theme-slug/
│
├── assets/
│   ├── css/
│   │   └── custom.css
│   ├── js/
│   │   └── theme.js
│   ├── fonts/
│   └── images/
│
├── inc/
│   ├── setup.php
│   ├── assets.php
│   └── helpers.php
│
├── parts/
│   ├── header.html
│   └── footer.html
│
├── patterns/
│   ├── hero.php
│   ├── call-to-action.php
│   └── testimonials.php
│
├── styles/
│   ├── dark.json
│   └── high-contrast.json
│
├── templates/
│   ├── index.html
│   ├── home.html
│   ├── front-page.html
│   ├── page.html
│   ├── single.html
│   ├── archive.html
│   ├── search.html
│   └── 404.html
│
├── functions.php
├── style.css
├── theme.json
├── readme.txt
├── LICENSE
├── screenshot.png
└── package.json
```

Minimum files required to recognize and properly develop a Block Theme:

```text
style.css
templates/index.html
theme.json
```

For submission to the official directory, also review the files and
metadata required by the Theme Review Guidelines.

---

# 7. style.css

`style.css` must exist in the theme root.

Example:

```css
/**
 * Theme Name:        Theme Name
 * Theme URI:         https://example.com/theme
 * Author:            Author Name
 * Author URI:        https://example.com
 * Description:       Short, objective description of the theme.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Tested up to:      7.1
 * Requires PHP:      8.3
 * License:           GNU General Public License v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       theme-slug
 * Tags:              block-patterns, full-site-editing
 */
```

`Text Domain` must match the theme's slug.

Do not change the text domain randomly between files.

---

# 8. theme.json

For modern WordPress, use:

```json
{
	"$schema": "https://schemas.wp.org/wp/7.1/theme.json",
	"version": 3,
	"settings": {},
	"styles": {}
}
```

Prefer the schema specific to the target version in stable projects.

The `trunk` schema can be used only during development when testing future
features is necessary.

## theme.json must be the primary source of design tokens

Configure in it whenever possible:

- colors;
- gradients;
- typography;
- font sizes;
- spacing;
- layout;
- content width;
- wide width;
- borders;
- shadows;
- duotone;
- presets;
- element styles;
- block-specific styles.

Avoid duplicating in CSS what can be defined in `theme.json`.

---

# 9. Design tokens

Never scatter arbitrary values across CSS when they are part of the design
system.

Prefer:

```json
{
	"settings": {
		"color": {
			"palette": [
				{
					"name": "Primary",
					"slug": "primary",
					"color": "#0057ff"
				}
			]
		}
	}
}
```

And use the variables generated by WordPress.

Example:

```css
.component {
	color: var(--wp--preset--color--primary);
}
```

Avoid:

```css
.component {
	color: #0057ff;
}
```

if that color already exists in the design system.

---

# 10. CSS

## General rules

CSS should:

- be mobile-first;
- have low specificity;
- take advantage of CSS variables;
- use `theme.json` tokens;
- avoid excessively deep selectors;
- avoid IDs for styling;
- avoid `!important`;
- respect accessibility preferences;
- work in both the editor and the frontend;
- not indiscriminately override plugin styles.

Prefer:

```css
.site-card {
	padding: var(--wp--preset--spacing--40);
}
```

Avoid:

```css
body #page .content .wrapper div.site-card {
	padding: 24px !important;
}
```

## Modern CSS

When appropriate, use:

```css
clamp()
min()
max()
minmax()
grid
flex
aspect-ratio
object-fit
logical properties
container queries
```

Consider progressive enhancement.

---

# 11. Responsiveness

Do not develop for fixed widths only.

Test at least:

```text
320px
375px
768px
1024px
1280px
1440px+
```

Check:

- menus;
- headings;
- tables;
- embeds;
- images;
- galleries;
- forms;
- grids;
- wide/full blocks;
- long content;
- very long words;
- browser zoom.

Prefer fluid typography when appropriate.

Example:

```css
font-size: clamp(2rem, 5vw, 4.5rem);
```

---

# 12. JavaScript

Add JavaScript only when truly necessary.

Priorities:

1. native HTML/CSS;
2. browser APIs;
3. native WordPress APIs;
4. vanilla JavaScript;
5. `@wordpress/*` packages when related to the editor;
6. external libraries only with justification.

Do not add jQuery just for convenience.

---

# 13. Asset loading

Never insert `<script>` or `<link>` directly into PHP templates for theme
assets.

Use WordPress APIs.

Example:

```php
<?php

function acme_theme_enqueue_assets(): void {
	$theme = wp_get_theme();

	wp_enqueue_style(
		'acme-theme',
		get_stylesheet_uri(),
		array(),
		$theme->get( 'Version' )
	);

	wp_enqueue_script(
		'acme-theme',
		get_theme_file_uri( 'assets/js/theme.js' ),
		array(),
		$theme->get( 'Version' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
}

add_action( 'wp_enqueue_scripts', 'acme_theme_enqueue_assets' );
```

Use:

```text
get_theme_file_uri()
get_theme_file_path()
get_parent_theme_file_uri()
get_stylesheet_uri()
```

instead of building paths manually.

---

# 14. Asset versioning

Production:

```php
wp_get_theme()->get( 'Version' );
```

During development, `filemtime()` can be used for cache busting when
appropriate.

Do not randomly use:

```php
time()
rand()
```

in production.

This defeats browser caching and degrades performance.

---

# 15. PHP and WordPress Coding Standards

All PHP must follow WordPress Coding Standards.

## Indentation

Use tabs for structural indentation.

## Spacing

Example:

```php
if ( $condition ) {
	do_something();
}
```

Not:

```php
if($condition){
	do_something();
}
```

## Arrays

Prefer short syntax in modern projects:

```php
$options = [
	'enabled' => true,
	'limit'   => 10,
];
```

When the project or tooling requires strict WordPress Core style, accept
`array()`.

Keep consistency across the project.

---

# 16. Prefixes and namespaces

All global code must avoid collisions.

Use a unique prefix of at least four characters.

Example:

```php
acme_theme_setup()
acme_theme_assets()
acme_theme_filter_title()
```

For object-oriented code, namespaces are recommended.

Example:

```php
namespace Acme\Theme;
```

Even when using a namespace, handles, custom hooks, options, and global
identifiers must remain unique.

---

# 17. functions.php

`functions.php` should be small.

It should preferably act as a bootstrap.

Example:

```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_theme_file_path( 'inc/setup.php' );
require_once get_theme_file_path( 'inc/assets.php' );
require_once get_theme_file_path( 'inc/helpers.php' );
```

Avoid `functions.php` files with thousands of lines.

Organize responsibilities into separate files.

---

# 18. Theme setup

Use `after_setup_theme` for features related to theme configuration.

Example:

```php
function acme_theme_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
}

add_action( 'after_setup_theme', 'acme_theme_setup' );
```

Before adding an `add_theme_support()`, check whether it is still needed
for the theme type and target WordPress version.

Do not add legacy code out of habit.

---

# 19. Plugin territory

One of the most important rules:

> Functionality that must keep existing when the user switches themes
> belongs in a plugin.

Do not implement in the theme:

- Custom Post Types related to site content;
- business taxonomies;
- shortcodes;
- SEO;
- analytics;
- tracking;
- contact forms;
- backups;
- caching;
- payment integrations;
- business rules;
- custom authentication;
- business APIs;
- content-dependent structured data;
- features that store essential data;
- custom blocks with persistent functionality.

These features must go into a plugin.

The theme should mainly control presentation.

---

# 20. Security

Assume any external data is untrusted.

Apply:

```text
Validate early
Sanitize input
Escape output late
Check capabilities
Verify nonces
Use prepared SQL
```

---

# 21. Sanitization

Examples:

```php
$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );

$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );

$id = absint( $_POST['post_id'] ?? 0 );
```

When possible, validate and reject invalid values instead of simply
modifying them.

---

# 22. Output escaping

Escape as late as possible.

Text:

```php
echo esc_html( $title );
```

Attribute:

```php
<input value="<?php echo esc_attr( $value ); ?>">
```

URL:

```php
<a href="<?php echo esc_url( $url ); ?>">
```

Allowed HTML:

```php
echo wp_kses_post( $content );
```

Translation + escape:

```php
echo esc_html__( 'Read more', 'theme-slug' );
```

Never run:

```php
echo $user_input;
```

without explicitly determining the context and the required escaping.

---

# 23. Nonces

For actions that modify data:

```php
wp_nonce_field( 'acme_save_settings', 'acme_nonce' );
```

On validation:

```php
if (
	! isset( $_POST['acme_nonce'] ) ||
	! wp_verify_nonce(
		sanitize_text_field( wp_unslash( $_POST['acme_nonce'] ) ),
		'acme_save_settings'
	)
) {
	return;
}
```

A nonce does not replace authorization.

Also check capabilities:

```php
if ( ! current_user_can( 'edit_theme_options' ) ) {
	return;
}
```

---

# 24. Database

Themes should rarely run manual SQL.

Prioritize:

```text
WP_Query
get_posts()
get_post_meta()
get_option()
WP_User_Query
WP_Term_Query
```

If direct SQL is truly necessary:

```php
$wpdb->prepare()
```

Never concatenate user input into SQL queries.

Forbidden:

```php
$sql = "SELECT * FROM {$wpdb->posts} WHERE ID = " . $_GET['id'];
```

---

# 25. Hooks

Use Actions and Filters instead of altering Core behavior.

Example:

```php
add_action( 'after_setup_theme', 'acme_theme_setup' );

add_filter( 'body_class', 'acme_theme_body_classes' );
```

Callbacks should:

- do one thing;
- have a clear name;
- avoid unexpected side effects;
- return a value in filters;
- document non-obvious behavior.

Never edit WordPress Core files.

---

# 26. Internationalization

All text shown to the user must be translatable.

Use:

```php
__( 'Text', 'theme-slug' );
_e( 'Text', 'theme-slug' );
esc_html__( 'Text', 'theme-slug' );
esc_attr__( 'Text', 'theme-slug' );
_x( 'Post', 'noun', 'theme-slug' );
_n( '%s item', '%s items', $count, 'theme-slug' );
```

Do not do:

```php
echo 'Read more';
```

Prefer:

```php
echo esc_html__( 'Read more', 'theme-slug' );
```

Do not assemble translated sentences by concatenating fragments.

Avoid:

```php
__( 'Hello ', 'theme-slug' ) . $name;
```

Prefer placeholders.

---

# 27. Accessibility

Accessibility is an architectural requirement, not a final step.

Every theme should consider at least:

- keyboard navigation;
- visible focus;
- skip link when necessary;
- semantic landmarks;
- logical heading order;
- form labels;
- sufficient contrast;
- accessible names on controls;
- accessible states;
- understandable links;
- images with proper alt handling;
- content usable with zoom;
- reflow;
- reduced motion;
- menus usable without a mouse.

---

# 28. Semantic HTML

Prefer:

```html
<header>
<nav>
<main>
<article>
<section>
<aside>
<footer>
```

Do not turn everything into:

```html
<div>
```

Use ARIA only when native HTML does not solve the problem.

Rule:

> No ARIA is better than bad ARIA.

Do not add redundant `role` attributes without necessity.

---

# 29. Focus

Never remove outline globally.

Forbidden:

```css
*:focus {
	outline: none;
}
```

Prefer:

```css
:focus-visible {
	outline: 2px solid currentColor;
	outline-offset: 3px;
}
```

---

# 30. Motion

Respect:

```css
@media (prefers-reduced-motion: reduce) {
	*,
	*::before,
	*::after {
		scroll-behavior: auto;
	}
}
```

Do not create animations essential to understanding the interface.

---

# 31. Images

Use the Media Library and native WordPress APIs.

Avoid hardcoded upload URLs.

When PHP is needed:

```php
echo wp_get_attachment_image(
	$attachment_id,
	'large',
	false,
	array(
		'class' => 'hero-image',
	)
);
```

This lets WordPress manage:

- `srcset`;
- `sizes`;
- dimensions;
- formats;
- lazy loading when appropriate.

Do not disable responsive images globally without a proven reason.

---

# 32. Performance

Main goal:

> Make the browser download, process, and execute only what the page
> actually needs.

Avoid:

- huge CSS frameworks for a handful of rules;
- unnecessary global JavaScript;
- jQuery without necessity;
- duplicated libraries;
- excessive fonts;
- icons loaded via a full library;
- repeated queries;
- queries inside loops;
- external HTTP calls per pageview;
- blocking assets;
- duplicated CSS.

---

# 33. Fonts

Prioritize:

1. system fonts;
2. local fonts bundled with the theme;
3. WordPress Font Library when applicable.

Do not depend on remote resources without necessity.

When bundling fonts:

- ensure proper licensing;
- use WOFF2;
- load only the weights used;
- define fallbacks;
- avoid 8 or 10 different files unnecessarily.

---

# 34. SVG

Inline SVG must be treated with attention to security.

Never print SVG coming from untrusted input.

For your own icons:

- prefer the WordPress icon API when appropriate;
- sanitize SVG;
- use `currentColor` when appropriate;
- mark decorative icons as hidden for screen readers.

Example:

```html
<svg aria-hidden="true" focusable="false">
```

If the icon is the only content of a button, the button needs an
accessible name.

---

# 35. Gutenberg

Do not fight the Block Editor.

A modern theme should integrate with Gutenberg.

Prioritize:

- Core Blocks;
- Block Styles;
- Block Variations;
- Patterns;
- Style Variations;
- `theme.json`;
- templates;
- template parts;
- Block Bindings when appropriate;
- public WordPress APIs.

Do not recreate a Core Block with custom JavaScript just to change its
appearance.

---

# 36. Patterns

Patterns are the primary tool for reusable layouts.

Use `/patterns`.

Example:

```php
<?php
/**
 * Title: Hero
 * Slug: theme-slug/hero
 * Categories: featured
 * Inserter: true
 */
?>

<!-- wp:group {"align":"full"} -->
<div class="wp-block-group alignfull">
	<!-- wp:heading -->
	<h2 class="wp-block-heading">
		<?php echo esc_html__( 'Build something great', 'theme-slug' ); ?>
	</h2>
	<!-- /wp:heading -->
</div>
<!-- /wp:group -->
```

Avoid giant, poorly reusable patterns.

---

# 37. Template hierarchy

Respect the WordPress Template Hierarchy.

For Block Themes consider:

```text
front-page.html
home.html
single.html
page.html
singular.html
archive.html
search.html
404.html
index.html
```

`templates/index.html` must always work as a fallback.

Do not duplicate entire templates when only a small section changes.

Use template parts and patterns to reduce duplication.

---

# 38. Template parts

Use `/parts` for reusable structural elements.

Examples:

```text
parts/header.html
parts/footer.html
parts/sidebar.html
```

Do not duplicate the header and footer across every template.

---

# 39. Child Themes

When modifying a third-party theme:

- do not edit the parent theme directly;
- create a child theme when the change needs to survive updates;
- understand the parent/child hierarchy;
- use `get_theme_file_*()` when you want to allow overrides by the child
  theme.

For a child theme, declare `Template` correctly in `style.css`.

---

# 40. Remote resources

Avoid remote dependencies.

Do not arbitrarily load:

```text
CDN CSS
CDN JS
external fonts
tracking
remote images
APIs
```

especially in distributed themes.

When an external resource is necessary:

- obtain consent when applicable;
- document it;
- respect privacy;
- offer a fallback;
- do not break the theme's basic functionality.

---

# 41. Privacy

Tracking must be disabled by default.

Never silently send:

- domain;
- IP;
- content;
- user;
- email;
- site information;
- administration data;
- usage metrics;

to third-party services.

Telemetry, when it exists, must be clearly documented and opt-in.

---

# 42. Frontend JavaScript

Do not create a JavaScript dependency for functionality that can work
without it.

Apply progressive enhancement.

Example:

The menu should remain understandable even before JS executes.

Event listeners should be added safely.

```js
const menuButton = document.querySelector( '.menu-toggle' );

if ( menuButton ) {
	menuButton.addEventListener( 'click', () => {
		// ...
	} );
}
```

Never assume an element always exists.

---

# 43. DOM manipulation

Avoid:

```js
document.querySelector(...).addEventListener(...)
```

without checking the return value.

Avoid global mutation observers or polling when an appropriate API/event
exists.

Do not perform expensive operations on:

```text
scroll
resize
mousemove
```

without necessity.

Use debounce, throttle, or modern APIs when appropriate.

---

# 44. Interactivity API

For interactivity in blocks and modern themes (WordPress 6.5+), prioritize
the WordPress **Interactivity API** over loose vanilla JavaScript or
external frameworks, whenever the interaction involves native blocks,
patterns, or theme components that need reactive state (toggles, tabs,
simple carousels, content filters, counters, lightweight forms).

Advantages over loose vanilla JS:

```text
Declarative, rehydratable state (server + client).
Native integration with block Server-Side Rendering.
Smaller payload than full frameworks (React, Vue, Alpine).
Officially supported and maintained by Core.
Less code needed to sync state with the DOM manually.
```

Register the store in PHP:

```php
<?php

wp_interactivity_state(
	'acme-theme',
	array(
		'isOpen' => false,
	)
);
```

Mark up the block/pattern HTML with directives:

```html
<div
	data-wp-interactive="acme-theme"
	data-wp-context='{ "isOpen": false }'
>
	<button
		data-wp-on--click="actions.toggle"
		data-wp-bind--aria-expanded="context.isOpen"
	>
		<?php echo esc_html__( 'Menu', 'theme-slug' ); ?>
	</button>

	<div data-wp-class--is-open="context.isOpen">
		<!-- content -->
	</div>
</div>
```

Define the logic in a `view.js` module loaded via `viewScriptModule`
(block.json) or `wp_register_script_module()`:

```js
import { store, getContext } from '@wordpress/interactivity';

store( 'acme-theme', {
	actions: {
		toggle() {
			const context = getContext();
			context.isOpen = ! context.isOpen;
		},
	},
} );
```

Guidelines:

- do not use the Interactivity API for entire pages as an SPA; it is meant
  for islands of interactivity within server-rendered HTML;
- do not mix the Interactivity API with direct DOM manipulation
  (`querySelector`) on the same element;
- for simple, isolated interactions that don't need shared state (e.g. the
  `menu-toggle` described in section 42), vanilla JavaScript remains a
  legitimate and simpler option;
- for custom theme blocks that need reactive state, prefer the
  Interactivity API over external libraries.

---

# 45. Block Bindings API

The **Block Bindings API** lets you connect attributes of native blocks
(text, image, URL, link) to dynamic data sources (post meta, custom
fields, other registered sources) without creating an entire custom block.

Prioritize Block Bindings when the goal is simply to display a dynamic
value inside a Core block.

Register a binding source:

```php
<?php

function acme_theme_register_block_bindings(): void {
	register_block_bindings_source(
		'acme-theme/event-date',
		array(
			'label'              => __( 'Event date', 'theme-slug' ),
			'get_value_callback' => 'acme_theme_get_event_date_binding',
		)
	);
}

add_action( 'init', 'acme_theme_register_block_bindings' );

function acme_theme_get_event_date_binding( array $source_args, $block_instance ): string {
	$date = get_post_meta( $block_instance->context['postId'], 'event_date', true );

	return $date ? esc_html( $date ) : '';
}
```

Use it in the block markup (pattern or template):

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"acme-theme/event-date"}}}} -->
<p><?php echo esc_html__( 'Event date', 'theme-slug' ); ?></p>
<!-- /wp:paragraph -->
```

When to use Block Bindings instead of a custom block:

```text
The goal is only to display/bind an existing value to a native block.
No new editing UI is needed beyond what the Core block already offers.
The data already exists as post meta or a registered source.
```

When to still create a custom block:

```text
A dedicated editing experience is required.
There is complex rendering logic.
Multiple interdependent attributes need their own control.
```

---

# 46. AJAX and REST

A theme should not implement business endpoints just because it can.

When asynchronous communication is part of persistent functionality, move
it to a plugin.

If REST/AJAX exclusively related to presentation is indispensable:

- nonce;
- capabilities;
- sanitization;
- validation;
- escaping;
- error handling;
- structured responses.

---

# 47. Public APIs

Never deliberately depend on:

- internal classes;
- private properties;
- `__experimental` APIs;
- `__unstable` APIs;
- deprecated functions;

when a stable public alternative exists.

Before using an experimental API, evaluate the upgrade impact.

---

# 48. Deprecated code

Do not use deprecated functions.

When maintaining a legacy theme:

1. identify the deprecated function;
2. locate the official replacement;
3. update it;
4. test for regressions;
5. remove the old workaround when it's no longer needed.

Run tests with:

```text
WP_DEBUG=true
WP_DEBUG_LOG=true
SCRIPT_DEBUG=true
```

in the development environment.

Never enable public error display in production.

---

# 49. Error handling

Do not indiscriminately hide warnings with `@`.

Avoid:

```php
@$value;
```

Fix the root cause of the problem.

Do not use:

```php
error_reporting( 0 );
```

inside the theme.

---

# 50. Query performance

Never create `WP_Query` repeatedly inside loops when the data can be
fetched in a single query.

Always consider:

```php
'no_found_rows' => true
```

when pagination is not needed.

Request only what is necessary.

Reset post data when appropriate:

```php
wp_reset_postdata();
```

---

# 51. Cache

Do not implement a custom page-cache system inside the theme.

When an expensive operation purely related to presentation needs caching,
consider the appropriate WordPress APIs.

Example:

```text
Transients API
Object Cache
```

But general caching functionality belongs to plugins/infrastructure.

---

# 52. SEO

The theme should provide semantically correct HTML.

It can contribute:

- correct headings;
- landmarks;
- performance;
- accessible content;
- `title-tag`;
- proper HTML structure.

Do not create your own system for:

- meta description;
- XML sitemap;
- Open Graph;
- canonical;
- SEO scoring;
- business schema;

as persistent theme functionality.

This belongs to a plugin/Core.

---

# 53. WooCommerce

If the project uses WooCommerce:

- prefer WooCommerce hooks and APIs;
- avoid copying WooCommerce templates without necessity;
- when overriding templates, document it;
- track changes across WooCommerce versions;
- keep overrides minimal;
- test cart, checkout, account, and products.

Never modify files of the WooCommerce plugin.

---

# 54. Plugin compatibility

A theme should avoid assuming it controls the entire page.

Do not apply rules like:

```css
button {
	all: unset;
}
```

globally.

Do not generically style classes that belong to plugins.

Scope your own styles when necessary.

---

# 55. Development tools

For professional projects, consider:

```text
Git
Composer
WordPress Coding Standards
PHP_CodeSniffer
PHPStan
ESLint
Stylelint
Prettier
@wordpress/scripts
WordPress Playground
Theme Check
Query Monitor
```

Tools should improve quality, not add complexity without benefit.

---

# 56. PHPCS

Configure WordPress Coding Standards.

Goals:

- detect incorrect PHP patterns;
- missing escaping;
- sanitization;
- prefixes;
- insecure APIs;
- inconsistent style.

Never ignore dozens of rules just to get a green build.

Fix the code whenever reasonable.

---

# 57. Static analysis

PHPStan can complement PHPCS.

When using it:

- configure WordPress stubs;
- avoid false positives from dynamic hooks;
- increase the level gradually;
- do not add useless casts just to satisfy the analyzer.

---

# 58. Build

Development files do not necessarily need to go into the final ZIP.

Review the distribution to avoid including:

```text
node_modules/
tests/
.git/
.github/
IDE settings
unnecessary source maps
temporary files
logs
credentials
.env
```

Never include secrets.

---

# 59. Git

Add a `.gitignore`.

Example:

```gitignore
node_modules/
vendor/
.env
*.log
.DS_Store
```

If `vendor/` is needed in the final package, adjust the build process
instead of simply ignoring it.

---

# 60. Licensing

Themes intended for the WordPress ecosystem must be GPL-compatible.

Check licenses for:

- fonts;
- icons;
- images;
- libraries;
- snippets;
- frameworks.

Do not copy assets without a compatible license.

Keep copyright and license information when required.

---

# 61. README

Document:

- requirements;
- installation;
- configuration;
- features;
- limitations;
- dependencies;
- licenses;
- credits;
- changelog;
- privacy, when applicable.

Do not hide important requirements.

---

# 62. Minimum required tests

Before considering a theme ready:

## WordPress

Test with:

```text
latest stable version
minimum supported PHP
latest stable PHP
```

In this baseline:

```text
WordPress 7.1
PHP 8.3
PHP 8.4
PHP 8.5
```

---

# 63. Test content

Test:

- post without an image;
- post with an image;
- post with many images;
- very long titles;
- very long content;
- lists;
- nested lists;
- blockquotes;
- tables;
- embeds;
- videos;
- galleries;
- full/wide images;
- comments;
- pagination;
- search with no results;
- 404;
- archives;
- categories;
- tags;
- authors.

When appropriate, use the official Theme Unit Test.

---

# 64. Interface tests

Test in:

```text
Chrome
Firefox
Safari
Edge
```

and mobile devices.

Check:

- touch;
- keyboard;
- mouse;
- 200% zoom;
- 400% zoom when applicable;
- portrait;
- landscape;
- dark mode, if supported;
- reduced motion.

---

# 65. Editor test

The frontend is not enough.

Always check:

```text
Post Editor
Site Editor
Global Styles
Patterns
Template editing
Template parts
Style variations
```

Content should look reasonably close to the frontend while editing.

---

# 66. Security checklist

Before delivery, check:

```text
[ ] All external input is validated/sanitized.
[ ] All dynamic output is escaped.
[ ] URLs use esc_url().
[ ] Attributes use esc_attr().
[ ] Allowed HTML uses wp_kses().
[ ] Mutating actions use a nonce.
[ ] Nonce is accompanied by a capability check.
[ ] No SQL contains unsafe concatenation.
[ ] No credential is in the repository.
[ ] No dangerous PHP function was added without justification.
[ ] No unexpected remote call happens on the frontend.
```

---

# 67. Performance checklist

```text
[ ] Assets load only where needed.
[ ] No huge framework without justification.
[ ] Non-essential scripts use an appropriate loading strategy.
[ ] No queries inside unnecessary loops.
[ ] Images have dimensions.
[ ] Images use WordPress APIs.
[ ] Fonts are optimized.
[ ] No external HTTP calls per pageview.
[ ] CSS has low specificity.
[ ] theme.json is used for tokens and global styles.
[ ] No cache busting with time() in production.
```

---

# 68. Accessibility checklist

```text
[ ] Navigation works via keyboard.
[ ] Focus is clearly visible.
[ ] A skip link exists when necessary.
[ ] Headings have a coherent hierarchy.
[ ] Links are understandable.
[ ] Buttons have an accessible name.
[ ] Fields have labels.
[ ] Contrast is sufficient.
[ ] Decorative images don't create noise.
[ ] Motion respects prefers-reduced-motion.
[ ] Content remains usable with zoom.
[ ] Mobile menus work with the keyboard.
```

---

# 69. WordPress checklist

```text
[ ] No Core file was modified.
[ ] WordPress APIs are used before custom solutions.
[ ] Text domain is consistent.
[ ] Strings are translatable.
[ ] functions.php has not become a monolith.
[ ] Prefixes/namespaces avoid collisions.
[ ] theme.json v3 is valid.
[ ] templates/index.html exists.
[ ] style.css has a valid header.
[ ] Templates follow the hierarchy.
[ ] Plugin territory is not inside the theme.
[ ] No deprecated APIs are present.
```

---

# 70. Launch checklist

```text
[ ] WP_DEBUG shows no warnings/notices.
[ ] Browser console is clean.
[ ] Theme Check has been run.
[ ] PHPCS passes.
[ ] Static analysis passes, when configured.
[ ] HTML has been reviewed.
[ ] CSS has been reviewed.
[ ] Responsiveness has been tested.
[ ] Keyboard navigation has been tested.
[ ] Editor and frontend have been tested.
[ ] Minimum PHP has been tested.
[ ] Latest PHP has been tested.
[ ] Latest WordPress has been tested.
[ ] README has been updated.
[ ] Theme version has been updated.
[ ] Changelog has been updated.
[ ] Final ZIP contains no private files.
```

---

# 71. Code generation rules

When receiving a development task:

## Before writing code

Determine:

1. is it theme or plugin territory?
2. is it a Block Theme or Classic Theme?
3. is there a native WordPress API?
4. is there a Core Block that solves it?
5. can it be done in `theme.json`?
6. can it be done with CSS?
7. is JavaScript really necessary?
8. is there a security risk?
9. is there an accessibility impact?
10. is there a performance impact?

---

# 72. Preferred order for solving problems

Use this order:

```text
1. WordPress/Core configuration
2. theme.json
3. Core Blocks
4. Patterns
5. Block Styles
6. Template / Template Part
7. CSS
8. PHP via hooks
9. JavaScript
10. External dependency
```

Do not start with the most complex solution.

---

# 73. When creating files

Always provide:

- the file path;
- the full content when necessary;
- a short explanation of the file's role;
- dependencies;
- related changes.

Example:

```text
/themes/acme/theme.json
/themes/acme/functions.php
/themes/acme/assets/js/navigation.js
```

Do not present loose snippets without explaining where they should be
placed when the context requires real implementation.

---

# 74. When editing existing code

Do not rewrite the entire architecture without necessity.

Preserve:

- existing public APIs;
- consistent naming;
- compatibility;
- hooks;
- filters;
- expected behavior.

Changes should be minimal and targeted to the problem.

Broad refactoring only when there is a clear benefit.

---

# 75. When there is insecure code

Do not perpetuate the existing pattern.

Existing example:

```php
echo $_GET['name'];
```

Should be fixed to something equivalent to:

```php
$name = sanitize_text_field(
	wp_unslash( $_GET['name'] ?? '' )
);

echo esc_html( $name );
```

---

# 76. When there is legacy code

Do not modernize blindly.

First identify:

- supported versions;
- related plugins;
- child themes;
- external hooks;
- public filters;
- persistent functionality;
- possible breaking changes.

Then update progressively.

---

# 77. Modern PHP

Modern PHP is recommended, but must respect the WordPress ecosystem.

You may use, when compatible with the minimum version:

- type declarations;
- return types;
- nullable types;
- union types;
- match;
- nullsafe operator;
- enums in internal components when genuinely useful;
- readonly when appropriate;
- constructor property promotion;
- attributes when there is a concrete application.

Do not use a language feature just to demonstrate modernity.

Prioritize readability.

---

# 78. Typing

Add types when they improve safety and understanding.

Example:

```php
function acme_theme_get_card_title( int $post_id ): string {
	return get_the_title( $post_id );
}
```

Be cautious in WordPress hook callbacks where data types can vary.

Do not automatically use `declare(strict_types=1);` in every file of a
WordPress theme.

The decision should consider integration with WordPress and third parties.

---

# 79. Object orientation

Do not turn simple functions into classes without necessity.

Use classes when there is:

- state;
- multiple related responsibilities;
- dependencies;
- a lifecycle;
- organized hook registration;
- domain objects.

For three simple hooks, named functions may be more readable.

Architecture should be proportional to the project.

---

# 80. Dependencies

Before adding an external library, ask:

```text
Does the browser already solve it?
Does WordPress already solve it?
Does a Core Block already solve it?
Does @wordpress/* already solve it?
Would 20 lines of simple code solve it?
```

If yes, avoid the dependency.

---

# 81. Don't

Forbidden by default:

```text
Editing WordPress Core.
Editing third-party plugins.
Hardcoding wp-content.
Hardcoding the site URL.
Hardcoding uploads.
Insecure SQL.
Echoing input without escaping.
Disabling updates.
Suppressing errors globally.
Adding hidden tracking.
Loading remote executables.
Creating a CPT in the theme.
Building a full SEO system in the theme.
Building analytics in the theme.
Adding a contact form as theme functionality.
Automatically installing plugins.
Creating a mandatory dependency without necessity.
Using deprecated APIs.
Using obsolete PHP functions.
Using eval().
Using extract().
Using shell_exec().
Using backticks for commands.
```

---

# 82. Completion criteria

A task should only be considered complete when:

1. the code solves the requirement;
2. it follows WordPress APIs;
3. it introduces no known vulnerability;
4. output is escaped;
5. input is validated/sanitized;
6. plugin territory has been respected;
7. accessibility has been considered;
8. performance has been considered;
9. editor and frontend remain coherent;
10. the code is organized;
11. the solution is compatible with the declared versions;
12. there is no unnecessary dependency.

---

# 83. Authoritative sources

When in doubt, prioritize in this order:

1. WordPress Theme Developer Handbook
2. WordPress Block Editor Handbook
3. WordPress Code Reference
4. WordPress Common APIs Handbook
5. Make WordPress Core
6. Make WordPress Themes
7. WordPress Developer Blog / Dev Notes
8. Official PHP Manual

Do not treat old blog snippets, Stack Overflow, or tutorials as a primary
source when current official documentation is available.

---

# 84. Final rule

Always look for the most native possible solution for WordPress.

The ideal solution is usually the one that:

```text
uses less code,
uses more public APIs,
respects the editor,
respects the user,
does not lock data into the theme,
is accessible,
is secure,
is fast,
is easy to remove,
is easy to maintain,
and keeps working after updates.
```
