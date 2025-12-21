# GitHub PR Dashboard

A lightweight, static HTML dashboard for managing your GitHub pull requests. No backend required - runs entirely in your browser.

## Features

- View PRs you've authored and PRs where you're requested as a reviewer
- Filter by repositories, authors, and reviewers
- See approval status, CI/CD status, and merge conflict indicators
- Track unresolved comments per PR
- Hide approved PRs and drafts
- Configurable auto-refresh (1, 3, 5, or 10 minutes)
- Support for multiple GitHub organizations
- Dark mode support
- Copy branch names (optionally with `git checkout` command)
- All settings persisted in localStorage

## Usage

1. Open `index.html` in your browser (or host it anywhere)

2. Click the settings icon and enter your GitHub token:
   ```bash
   # Get your token from the GitHub CLI
   gh auth token
   ```

3. Optionally add organization names to filter by specific orgs

4. That's it! Your PRs will load automatically.

## Hosting Options

Since this is a static HTML file, you can:

- Open it directly in your browser (`file://`)
- Serve it locally: `npx serve .`
- Host on GitHub Pages
- Host on any static file server (Netlify, Vercel, S3, etc.)

## Tech Stack

- [Alpine.js](https://alpinejs.dev/) - Reactivity (via CDN)
- [Tailwind CSS](https://tailwindcss.com/) - Styling (via CDN)
- GitHub GraphQL API - Data fetching

## Privacy

Your GitHub token is stored in your browser's localStorage and never sent anywhere except directly to GitHub's API. All data processing happens client-side.

## License

MIT

## Credits

Built by [Abdel Elrafa](https://github.com/AbdelElrafa) and [Claude Code](https://claude.ai/code)
