# Arcane Voice Feedback - Auto-Update Setup

This plugin now supports automatic updates from GitHub! Here's how to set it up:

## Quick Setup (3 Steps)

### 1. Create a GitHub Repository

1. Go to [GitHub](https://github.com) and create a new repository
2. Name it: `arcane-voice-feedback` (or any name you prefer)
3. Set it to **Public** (required for auto-updates to work)

### 2. Update Plugin Settings

Open `arcane-voice-feedback.php` and find these lines near the top of the class:

```php
private $github_username = 'yourusername'; // Change this to your GitHub username
private $github_repo = 'arcane-voice-feedback'; // Change this to your GitHub repo name
```

Replace:
- `yourusername` with your actual GitHub username
- `arcane-voice-feedback` with your repository name (if different)

### 3. Create Releases on GitHub

When you want to release an update:

1. Go to your GitHub repository
2. Click **Releases** → **Create a new release**
3. Create a tag (e.g., `v1.2.1`, `v1.3.0`, etc.)
4. Title: Version name (e.g., "Version 1.2.1")
5. Description: What's new in this version
6. Click **Publish release**

## How It Works

- WordPress will check your GitHub repository for new releases
- When a new version is available, it shows up in the WordPress updates screen
- Users can update with one click, just like official WordPress plugins
- The plugin automatically downloads from your GitHub releases

## Version Format

Always use this format for tags: `v1.2.0`
- Must start with lowercase `v`
- Use semantic versioning (major.minor.patch)
- Examples: `v1.2.0`, `v1.2.1`, `v2.0.0`

## Alternative: Without GitHub

If you don't want to use GitHub, you can:

1. Remove the auto-update code from the plugin
2. Or set up your own update server
3. Or distribute updates manually

## Current Version

**Version 1.2.0** - Auto-update support added

## Features

- ✅ Automatic update checks
- ✅ One-click updates from WordPress admin
- ✅ Changelog display in update screen
- ✅ Version compatibility checking
- ✅ Works with WordPress 5.0+

## Support

For issues or questions, create an issue in your GitHub repository.

---

**Note:** The auto-update feature requires the plugin to be hosted in a public GitHub repository. If you want to keep it private, you'll need to set up authentication tokens (more advanced setup).
