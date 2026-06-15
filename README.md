# WebDAV / Nextcloud Files – Roundcube Plugin

Integrates **Nextcloud** (or any **WebDAV** server) with Roundcube.

---

## Features

- **Attach files from Nextcloud** – A **"Attach from Nextcloud"** button right
  next to "Attach" in the compose window. Opens a file browser with folder
  navigation (single-click opens folders), breadcrumb path, file/folder icons
  and multi-selection.
- **Insert share links** – In the same browser, a "Insert link" button inserts
  public Nextcloud share links (OCS API; existing links are reused).
- **Save attachments to Nextcloud** – When reading an e-mail, a **"Attachment
  to cloud storage"** button appears next to the attachment list. The dialog
  lets you choose attachments and a target folder. Also works via the context
  menu / "More" actions in the main message list.
- **Save e-mail as PDF to Nextcloud** – A **"PDF to cloud storage"** icon
  (cloud with upload arrow) in the message view and in the context menu of the
  message list. Target folder and file name are configurable (default pattern
  `%Y_%m_%d eMail # %subject%`; folder is selectable via the browser).
- **Per-mail-account credentials** – Settings are under "Nextcloud / WebDAV".
  The password is **encrypted** (like the mail password) and never sent to the
  browser.

## Requirements

| | |
|---|---|
| Roundcube | 1.5 or newer (Elastic and Larry) |
| PHP | 7.3+ with **curl** and **dom** extensions |
| Server | A Nextcloud instance or a WebDAV server |
| Optional | **wkhtmltopdf** for high-quality HTML PDFs (without it, a built-in text PDF generator is used) |

## Installation

1. Copy the `webdav_files` folder into the plugin directory:

   ```bash
   cp -r webdav_files /var/www/roundcube/plugins/
   ```

2. (Optional) Create the config and adjust the defaults:

   ```bash
   cd /var/www/roundcube/plugins/webdav_files
   cp config.inc.php.dist config.inc.php
   nano config.inc.php
   ```

3. Enable the plugin in `config/config.inc.php`:

   ```php
   $config['plugins'][] = 'webdav_files';
   ```

4. Clear the browser cache / reload Roundcube.

## Configuration

**PDF file name pattern** – Placeholders: `%Y` (year), `%m` (month), `%d` (day),
`%H`, `%M`, `%subject%` (subject). Default: `%Y_%m_%d eMail # %subject%` → e.g.
`2026_06_13 eMail # Quote Project X.pdf`.

**Security:** The cloud password is encrypted with `rcmail::encrypt()` — the
same method and key (`des_key` in `config.inc.php`) that Roundcube uses for the
IMAP password. It never leaves the server in plaintext and is only indicated as
a placeholder in the settings form.

## Usage

### User setup

Under **Settings → Nextcloud / WebDAV**:

- **Server type**: Nextcloud or generic WebDAV
- **Server URL**: e.g. `https://cloud.example.com` (for Nextcloud the base URL
  is sufficient; the WebDAV path `remote.php/dav/files/<user>` is appended
  automatically)
- **Username** and **App password**

> **Tip:** In Nextcloud under *Settings → Security → App password*, generate a
> dedicated app password instead of using the main password.

Folders for saved attachments and PDFs as well as the PDF file name pattern can
also be set there; folders are selectable directly in the cloud via a "Browse"
button.

### Composing e-mail

1. In the compose window, click **"Attach from Nextcloud"**
2. Navigate through the folders in the file browser and select file(s)
3. Either **attach** them or insert them as a share link via **"Insert link"**

### Reading e-mail

1. To save an attachment: click **"Attachment to cloud storage"**, choose the
   target folder
2. To save as PDF: click **"PDF to cloud storage"**, choose the target folder
   and file name

## Troubleshooting

- Errors are logged to `logs/webdav_files.log`.
- For self-signed certificates:
  `$config['webdav_files_verify_ssl'] = false;`
- Share links only work with Nextcloud (OCS API), not with plain WebDAV.
- High-quality HTML PDFs: `$config['webdav_files_wkhtmltopdf'] = '/usr/bin/wkhtmltopdf';`

## Author

**Sebastian Fischer** post@scriptometer.de

## License

MIT License with Non-Commercial restriction – see [LICENSE](LICENSE).
