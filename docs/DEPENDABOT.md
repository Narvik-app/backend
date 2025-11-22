# Dependabot Configuration

This repository uses GitHub Dependabot to automatically keep dependencies up to date.

## Overview

Dependabot is configured to monitor three types of dependencies:

1. **Composer (PHP) Dependencies** - All PHP packages defined in `composer.json`
2. **Docker Dependencies** - Base Docker images used in `Dockerfile`
3. **GitHub Actions Dependencies** - GitHub Actions used in workflows

## Configuration Files

### `.github/dependabot.yml`

The main configuration file that defines:
- Which package ecosystems to monitor
- Update schedule (weekly on Saturdays at 3:00 AM UTC)
- Pull request limits
- Assignees and labels
- Dependency grouping rules
- Ignored major version updates for critical frameworks

### `.github/workflows/dependabot.yml`

An automated workflow that:
- **DISABLED** - Auto-merge functionality has been disabled
- Now only adds informational comments to new Dependabot PRs
- Requires manual review and merging of all dependency updates

## Update Schedule

All dependency updates are checked **weekly on Saturdays at 3:00 AM UTC**.

## Dependency Grouping

To reduce PR noise, related dependencies are grouped together:

### Composer Dependencies
- **Symfony packages** (`symfony/*`) - grouped for minor/patch updates
- **API Platform packages** (`api-platform/*`) - grouped for minor/patch updates
- **Doctrine packages** (`doctrine/*`) - grouped for minor/patch updates
- **Development dependencies** - all dev dependencies grouped for minor/patch updates

### GitHub Actions
- All GitHub Actions updates are grouped together for minor/patch updates

## Major Version Updates

Major version updates for critical frameworks are **ignored** by default to prevent breaking changes:
- Symfony (`symfony/*`)
- API Platform (`api-platform/*`)
- Doctrine (`doctrine/*`)

These must be updated manually when ready to handle potential breaking changes.

## Auto-Merge Behavior

**Auto-merge has been DISABLED**. All dependency updates now require manual review and approval.

### Current Workflow:
1. **For All Updates:**
   - Dependabot creates a pull request
   - Automated workflow adds an informational comment
   - **Manual review and testing required**
   - **Manual merging required**

### Why Auto-Merge Was Disabled:
- Ensures proper testing of dependency updates
- Allows for review of changelogs and potential breaking changes
- Provides better control over update timing
- Reduces risk of introducing unexpected issues

## Pull Request Assignment

Pull requests are automatically assigned to the project owner:
- **Primary Assignee:** `froozeify` (Project Owner)
- All dependency update PRs will be assigned for review

## Pull Request Limits

To avoid overwhelming the repository with PRs:
- **Composer**: Maximum 10 open PRs
- **Docker**: Maximum 5 open PRs
- **GitHub Actions**: Maximum 5 open PRs

## Commit Message Format

All Dependabot commits follow the format:
```
chore(deps): <scope>: <description>
```

This follows the Conventional Commits specification and clearly identifies dependency updates.

## Labels

Dependabot PRs are automatically labeled:
- `dependencies` - All dependency updates
- `composer` - PHP/Composer updates
- `docker` - Docker image updates
- `github-actions` - GitHub Actions updates

## Manual Review Process

When a Dependabot PR is created:

1. **Review the Changes**
   - Check the dependency changelog
   - Review version compatibility
   - Look for potential breaking changes

2. **Test the Updates**
   - Run the test suite locally or in CI
   - Verify all functionality still works
   - Check for any deprecation warnings

3. **Merge When Ready**
   - Once testing is complete and approved
   - Use the "Merge pull request" button
   - Choose merge strategy (squash recommended)

## Troubleshooting

### Dependabot is not creating PRs

1. Check the [Dependabot logs](https://github.com/Narvik-app/backend/network/updates) in the repository
2. Verify the `dependabot.yml` syntax is correct
3. Ensure the repository has Dependabot enabled in Settings

### PRs are not being assigned

1. Verify the assignee username in `dependabot.yml` is correct
2. Check that the assignee has push access to the repository
3. Ensure GitHub usernames match exactly (case-sensitive)

### Too many PRs being created

Adjust the `open-pull-requests-limit` values in `dependabot.yml` or add more dependencies to the `ignore` list.

### Manual merge conflicts

1. Update your local branch with the latest main
2. Resolve any conflicts
3. Re-run tests to ensure everything still works
4. Push the resolved changes

## Re-enabling Auto-Merge (If Needed)

To re-enable auto-merge functionality in the future:

1. Edit `.github/workflows/dependabot.yml`
2. Restore the auto-approve and auto-merge steps
3. Update permissions to include `contents: write` and `pull-requests: write`
4. Test the configuration with a small subset of dependencies

**Note:** Only re-enable auto-merge after thorough testing and when confident in the stability of the update process.

## Disabling Dependabot

To temporarily disable Dependabot:
1. Go to repository Settings → Code security and analysis
2. Toggle "Dependabot version updates" off

Or permanently by removing the `.github/dependabot.yml` file.

## Additional Resources

- [Dependabot Documentation](https://docs.github.com/en/code-security/dependabot)
- [Configuration Options](https://docs.github.com/en/code-security/dependabot/dependabot-version-updates/configuration-options-for-the-dependabot.yml-file)
- [Manual Dependency Management](https://docs.github.com/en/code-security/dependabot/working-with-dependabot/managing-pull-requests-for-dependency-updates)
