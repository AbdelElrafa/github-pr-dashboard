# GitHub PR Dashboard

A Laravel + Livewire dashboard for managing your GitHub pull requests across multiple organizations.

## Features

- View PRs you've authored and PRs where you're requested as a reviewer
- See approval status, CI/CD status, and merge conflict indicators
- Track unresolved comments per PR
- Filter by approved PRs (to master vs other branches) and drafts
- Configurable auto-refresh (1, 3, 5, or 10 minutes)
- Support for multiple GitHub organizations

## Requirements

- PHP 8.5
- Composer
- Node.js & npm
- GitHub CLI (`gh`) installed

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/AbdelElrafa/github-pr-dashboard.git
   cd github-pr-dashboard
   ```

2. Install dependencies:
   ```bash
   composer install
   npm install
   ```

3. Set up environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure GitHub access in `.env`:
   ```bash
   # Get your token from the GitHub CLI
   gh auth token
   ```
   ```env
   GITHUB_TOKEN=your_token_here
   GITHUB_ORGANIZATIONS=org1,org2  # Optional: comma-separated, leave empty for all
   ```

5. Build assets:
   ```bash
   npm run build
   ```

6. Start the server:
   ```bash
   php artisan serve
   ```

7. Visit http://localhost:8000

## Configuration

| Variable | Description |
|----------|-------------|
| `GITHUB_TOKEN` | Your GitHub personal access token (get via `gh auth token`) |
| `GITHUB_ORGANIZATIONS` | Comma-separated list of orgs to filter by. Leave empty to show PRs from all accessible repos. |

## License

MIT
