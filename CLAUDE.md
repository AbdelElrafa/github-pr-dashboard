# GitHub PR Dashboard

A static HTML dashboard for viewing GitHub pull requests. No build step, no backend - just a single HTML file.

## Project Structure

```
index.html    # The entire application
README.md     # Documentation
```

## Tech Stack

- **Alpine.js** (via CDN) - Reactivity and state management
- **Tailwind CSS** (via CDN) - Styling
- **GitHub GraphQL API** - Data fetching

## Key Features

- Two PR tables: "My PRs" and "Review Requests"
- Filters: search, repositories, authors, reviewers
- Hide options: approved PRs (to main/other), drafts
- Auto-refresh with configurable intervals
- 5-minute client-side data caching
- Dark/light theme toggle
- Copy branch names (optionally with `git checkout` prefix)
- All settings persisted in localStorage

## Development

No build step required. Just edit `index.html` and refresh the browser.

To serve locally:
```bash
npx serve .
```

## localStorage Keys

- `github_token` - GitHub personal access token
- `github_organizations` - Comma-separated org names
- `github_pr_cache_*` - Cached PR data (5 min TTL)
- `github_pr_filters` - Saved filter state
- `copy_with_git_checkout` - Branch copy preference
- `theme` - Light/dark theme preference
