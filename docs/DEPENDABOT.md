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
- Update schedule (weekly on Mondays at 3:00 AM UTC)
- Pull request limits
- Reviewers and labels
- Dependency grouping rules
- Ignored major version updates for critical frameworks

### `.github/workflows/dependabot-auto-merge.yml`

An automated workflow that:
- Waits for CI tests to pass on Dependabot PRs
- Automatically approves and merges minor and patch updates
- Adds a warning comment on major version updates that require manual review

## Update Schedule

All dependency updates are checked **weekly on Mondays at 3:00 AM UTC**.

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

The auto-merge workflow will automatically:

1. **For Minor and Patch Updates:**
   - Wait for all CI checks to pass
   - Automatically approve the PR
   - Enable auto-merge with squash strategy
   - Merge once all checks are green

2. **For Major Updates:**
   - Add a warning comment
   - Require manual review and approval
   - Will not auto-merge

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

## Reviewers

PRs are automatically assigned to the `Narvik-app/maintainers` team for review.

## Troubleshooting

### Dependabot is not creating PRs

1. Check the [Dependabot logs](https://github.com/Narvik-app/backend/network/updates) in the repository
2. Verify the `dependabot.yml` syntax is correct
3. Ensure the repository has Dependabot enabled in Settings

### Auto-merge is not working

1. Verify the branch protection rules allow auto-merge
2. Check that all required CI checks are passing
3. Ensure the `GITHUB_TOKEN` has sufficient permissions

### Too many PRs being created

Adjust the `open-pull-requests-limit` values in `dependabot.yml` or add more dependencies to the `ignore` list.

## Disabling Dependabot

To temporarily disable Dependabot:
1. Go to repository Settings → Code security and analysis
2. Toggle "Dependabot version updates" off

Or permanently by removing the `.github/dependabot.yml` file.

## Additional Resources

- [Dependabot Documentation](https://docs.github.com/en/code-security/dependabot)
- [Configuration Options](https://docs.github.com/en/code-security/dependabot/dependabot-version-updates/configuration-options-for-the-dependabot.yml-file)
- [Auto-merge Best Practices](https://docs.github.com/en/code-security/dependabot/working-with-dependabot/automating-dependabot-with-github-actions)
