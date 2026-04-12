---
name: commit-tag-release
description: Full release flow - bump version, commit, create git tag, and push to trigger GitHub release workflow. Usage - /commit-tag-release patch, /commit-tag-release minor, /commit-tag-release major
user-invocable: true
disable-model-invocation: false
---

# Commit, Tag & Release

Complete release flow that bumps the version and pushes a tag to trigger the GitHub Actions release workflow.

## Arguments

The user provides the bump type as an argument: `patch`, `minor`, or `major`.
If no argument is provided, default to `patch`.

## Steps

### 1. Run /bump-version skill

Use the Skill tool to invoke `bump-version` with the provided bump type argument. This updates the version in all 3 files:
- `ihumbak-woo-conta-api.php` header
- `IHUMBAK_WCA_VERSION` constant
- `composer.json`

### 2. Verify changes

Run `git diff` to confirm exactly 3 version changes were made and nothing else was modified.

### 3. Commit

Stage only the two changed files and create a commit:

```
git add ihumbak-woo-conta-api.php composer.json
git commit -m "chore: bump version to X.Y.Z"
```

### 4. Create git tag

```
git tag vX.Y.Z
```

### 5. Confirm before push

**STOP and ask the user for confirmation** before pushing. Show:
- The new version number
- The commit that will be pushed
- The tag that will be pushed
- Reminder that pushing the tag triggers the GitHub Actions release workflow (`.github/workflows/release.yml`)

### 6. Push (only after user confirms)

```
git push origin <current-branch>
git push origin vX.Y.Z
```

### 7. Report

Show the user:
- New version: X.Y.Z
- Tag: vX.Y.Z
- Link to GitHub Actions to monitor the release build
