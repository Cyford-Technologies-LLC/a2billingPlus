# Modular UI Architecture

A2BillingPlus UI replacements must be modular like the backend workflow code.
New screens should share the `A2BillingPlus\Module\Ui` theme registry instead
of embedding one-off CSS or hardcoded brand assets in page scripts.

## Theme Selection

The active theme is selected with `A2BP_UI_THEME`.

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
