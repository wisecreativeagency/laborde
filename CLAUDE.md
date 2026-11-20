# Laborde Marcet — notes for Claude

- Staging: https://labordemarcet.wisecreativeagency.com/ (behind Cloudflare)
- Server: Plesk, SSH alias `wise-plesk` (see ~/.ssh/config, key auth, BatchMode OK)
- Docroot: `~/labordemarcet.wisecreativeagency.com` (= /var/www/vhosts/wisecreativeagency.com/labordemarcet.wisecreativeagency.com)
- WP-CLI: `ssh wise-plesk 'cd ~/labordemarcet.wisecreativeagency.com && wp ...'`
- Same server hosts other live sites: never touch anything outside the Laborde docroot.
- Never commit wp-config.php, credentials or uploads.
- Custom theme/plugins must be prefixed `laborde` to be tracked (see .gitignore).
