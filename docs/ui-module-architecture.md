# Modular UI Architecture

A2BillingPlus UI replacements must be modular like the backend workflow code.
New screens should share the `A2BillingPlus\Module\Ui` theme registry instead
of embedding one-off CSS or hardcoded brand assets in page scripts.

## Theme Selection

The active theme is selected with `A2BP_UI_THEME`.
The active menu style is selected with `A2BP_UI_MENU_STYLE`. When no explicit
menu style is saved, the active theme's `menu_style` manifest value is used.

Available built-in themes:

- `a2billingplus`: default modern operations theme.
- `classic`: compact A2Billing-style theme for operators who prefer the old
  gray, blue, and red admin look on new modular screens.
- `legacy`: compatibility asset path for legacy screens during migration.

If a requested theme is missing, the registry falls back to `a2billingplus`.

## Adding A Theme

1. Add theme assets under `admin/Public/ui/themes/<theme-id>/`.
2. Register a `Theme` in `ThemeRegistry::default()` or in deployment-specific
   bootstrap code.
3. Set `A2BP_UI_THEME=<theme-id>`.
4. Keep workflow logic in modules/controllers; theme files should only affect
   presentation.

## Theme Package Direction

Manual filesystem installation is acceptable for development, but production UI
theme management should support operator-driven install flows.

Theme packages should converge on a single manifest contract that defines at
least:

- theme ID, name, version, and supported UI contract version
- stylesheet entrypoints, default `menu_style`, and optional asset directories
- author/vendor metadata
- built-in vs custom theme status

The planned admin theme management flow should support:

- listing installed themes and the active theme
- uploading or installing a theme package
- validating manifest/schema compatibility before activation
- rejecting invalid or incomplete packages cleanly
- rolling back failed installs
- blocking removal of the active theme and required built-in fallback themes

The first implementation lives at `admin/Public/A2B_ui_theme_manager.php`. It
lists installed themes, uploads zip packages, validates `theme.json`, extracts
the package into `admin/Public/ui/themes/<theme-id>/`, and lets operators
activate the installed theme through the shared modular admin shell.

## Menu Styles

Menu layout is separate from theme color and typography. The shared stylesheet
`admin/Public/ui/menu-styles.css` defines the supported menu layouts:

- `side-rail`: full left-side operations rail plus top utility bar.
- `topbar`: horizontal menu mode that hides expanded legacy submenu groups.
- `compact`: narrow sidebar for dense operator screens.
- `split`: wider split navigation intended for branded/dark operations themes.

Themes may declare a default menu style in `theme.json`:

```json
{
  "id": "tenant-midnight",
  "name": "Tenant Midnight",
  "version": "1.0.0",
  "ui_contract_version": "1",
  "stylesheet": "theme.css",
  "menu_style": "split"
}
```

Admins can override the theme default from the modular navigation selector.
Saving a new theme resets the menu style to that theme's default; saving a menu
style stores `A2BP_UI_MENU_STYLE` explicitly.

## Screen Rule

Modernized screens should render through module-backed services, use shared UI
classes from the active theme, hide secrets by default, and remain covered by
seeded crawl checks before the legacy page is retired.

## Modular Navigation

Modernized pages should use `A2BillingPlus\Module\Ui\NavigationRegistry` and
`NavigationRenderer` instead of embedding menu markup in each page. The first
admin implementation renders the common operations/customer/rates links and an
admin theme selector inside the modular navigation bar.

The selector posts `form_action=set_ui_theme` and stores `A2BP_UI_THEME` in
the local `.env` file when it is writable. Legacy Smarty menus remain available
until each workflow is replaced.

## Current Modular Admin Screens

- `A2B_provider_setup.php`: provider registration, status, rate preview, and
  provisioning setup.
- `A2B_payment_workspace.php`: hosted/tokenized payment activity and
  reconciliation totals.
- `A2B_customer_workspace.php` and `A2B_customer_detail.php`: customer search,
  account review, status, and core contact updates.
- `A2B_rate_workspace.php`: rate row search, tariff plan/group review, and
  destination coverage lookup through the rate module services.
- `A2B_telephony_workspace.php`: DID, trunk, SIP/IAX account review plus
  Asterisk launch-readiness checks through the telephony module services.
- `A2B_ui_theme_manager.php`: theme package upload/install and activation.
