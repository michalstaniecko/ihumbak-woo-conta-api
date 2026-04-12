---
name: bump-version
description: Bump plugin version (major, minor, or patch). Updates version in all required files and optionally creates a git tag. Usage - /bump-version patch, /bump-version minor, /bump-version major
user-invocable: true
disable-model-invocation: false
---

# Bump Plugin Version

Bump the plugin version following semver (major.minor.patch).

## Arguments

The user provides the bump type as an argument: `patch`, `minor`, or `major`.
If no argument is provided, default to `patch`.

## Files to update

All three locations MUST be updated to the same version:

1. **`ihumbak-woo-conta-api.php`** — plugin header comment `Version: X.Y.Z`
2. **`ihumbak-woo-conta-api.php`** — constant `define( 'IHUMBAK_WCA_VERSION', 'X.Y.Z' );`
3. **`composer.json`** — field `"version": "X.Y.Z"`

## Steps

1. **Read current version** from `IHUMBAK_WCA_VERSION` constant in `ihumbak-woo-conta-api.php`
2. **Calculate new version** based on bump type:
   - `patch`: 0.1.0 -> 0.1.1
   - `minor`: 0.1.0 -> 0.2.0
   - `major`: 0.1.0 -> 1.0.0
3. **Update all 3 locations** using the Edit tool
4. **Show the change** — display old version -> new version
5. **Ask the user** if they want to:
   - Commit the version bump
   - Create a git tag `vX.Y.Z` (which triggers the release workflow)
