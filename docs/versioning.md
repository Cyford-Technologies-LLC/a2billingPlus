# Versioning Policy

A2BillingPlus uses semantic versioning after the first stable release.

Before `v1.0.0`, use pre-release tags:

- `v0.x.0-alpha.N` for sandbox validation builds
- `v0.x.0-beta.N` for operator-preview builds
- `v0.x.0-rc.N` for release candidates

After `v1.0.0`:

- patch releases contain bug fixes, compatibility fixes, and documentation
- minor releases add backwards-compatible features and modules
- major releases may remove deprecated legacy flows or require manual migration

Every tag must include release notes, migration notes, and verification output.
