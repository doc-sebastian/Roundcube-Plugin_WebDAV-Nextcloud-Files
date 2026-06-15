# WebDAV / Nextcloud Files – Roundcube-Plugin

Bindet **Nextcloud** (oder einen beliebigen **WebDAV**-Server) an Roundcube an.

---

## Funktionen

- **Dateien aus Nextcloud anhängen** – ein Button **„Aus Nextcloud anhängen"**
  direkt neben „Anhängen" im Verfassen-Fenster. Öffnet einen Datei-Browser mit
  Ordner-Navigation (Einfachklick öffnet Ordner), Brotkrümelpfad,
  Datei-/Ordner-Icons und Mehrfachauswahl.
- **Freigabelinks einfügen** – im selben Browser per Button „Link einfügen" als
  öffentliche Nextcloud-Freigabelinks (OCS-API; bestehende Links werden
  wiederverwendet).
- **Anhänge in Nextcloud speichern** – beim Lesen einer E-Mail ein Button
  **„Anhang in Cloudspeicher"** an der Anhang-Liste. Auswahl der Anhänge und des
  Zielordners per Dialog. Funktioniert auch über das Kontextmenü / die
  „Mehr"-Aktionen der Nachrichtenliste in der Hauptansicht.
- **E-Mail als PDF in Nextcloud speichern** – ein Icon **„PDF in Cloudspeicher"**
  in der Nachrichtenansicht sowie im Kontextmenü der Nachrichtenliste.
  Zielordner und Dateiname konfigurierbar (Standardmuster
  `%Y_%m_%d eMail # %subject%`), Ordner per Browser wählbar.
- **Zugangsdaten pro Mailaccount** – Einstellungen unter „Nextcloud / WebDAV",
  Passwort **verschlüsselt** gespeichert (wie das Mail-Passwort), nie an den
  Browser übertragen.

## Voraussetzungen

| | |
|---|---|
| Roundcube | 1.5 oder neuer (Elastic und Larry) |
| PHP | 7.3+ mit **curl**- und **dom**-Erweiterung |
| Server | Eine Nextcloud-Instanz oder ein WebDAV-Server |
| Optional | **wkhtmltopdf** für hochwertige HTML-PDFs (ohne wird ein eingebauter Text-PDF-Generator verwendet) |

## Installation

1. Den Ordner `webdav_files` in das Plugin-Verzeichnis kopieren:

   ```bash
   cp -r webdav_files /var/www/roundcube/plugins/
   ```

2. (Optional) Konfiguration anlegen und Vorgaben anpassen:

   ```bash
   cd /var/www/roundcube/plugins/webdav_files
   cp config.inc.php.dist config.inc.php
   nano config.inc.php
   ```

3. Plugin in `config/config.inc.php` aktivieren:

   ```php
   $config['plugins'][] = 'webdav_files';
   ```

4. Browser-Cache leeren / Roundcube neu laden.

## Konfiguration

**PDF-Dateinamensmuster** – Platzhalter: `%Y` (Jahr), `%m` (Monat), `%d` (Tag),
`%H`, `%M`, `%subject%` (Betreff). Standard: `%Y_%m_%d eMail # %subject%` → z. B.
`2026_06_13 eMail # Angebot Projekt X.pdf`.

**Sicherheit:** Das Cloud-Passwort wird mit `rcmail::encrypt()` verschlüsselt –
demselben Verfahren und Schlüssel (`des_key` in `config.inc.php`), das Roundcube
für das IMAP-Passwort verwendet. Es verlässt den Server nie in Klartext und wird
im Einstellungsformular nur als Platzhalter angedeutet.

## Benutzung

### Einrichtung durch den Benutzer

Unter **Einstellungen → Nextcloud / WebDAV**:

- **Servertyp**: Nextcloud oder allgemeines WebDAV
- **Server-URL**: z. B. `https://cloud.example.com` (für Nextcloud reicht die
  Basis-URL, der WebDAV-Pfad `remote.php/dav/files/<user>` wird automatisch
  ergänzt)
- **Benutzername** und **App-Passwort**

> **Tipp:** In Nextcloud unter *Einstellungen → Sicherheit → App-Passwort* ein
> dediziertes App-Passwort erzeugen, statt des Hauptpassworts.

Ordner für gespeicherte Anhänge und PDFs sowie das PDF-Dateinamensmuster lassen
sich ebenfalls dort einstellen; die Ordner sind per „Durchsuchen"-Button direkt
in der Cloud auswählbar.

### E-Mail verfassen

1. Im Verfassen-Fenster auf **„Aus Nextcloud anhängen"** klicken
2. Im Datei-Browser durch die Ordner navigieren und Datei(en) auswählen
3. Entweder **Anhängen** oder per **„Link einfügen"** als Freigabelink

### E-Mail lesen

1. Anhang zum Speichern: Auf **„Anhang in Cloudspeicher"** klicken, Zielordner
   wählen
2. Als PDF speichern: Auf **„PDF in Cloudspeicher"** klicken, Zielordner und
   Dateiname wählen

## Fehlersuche

- Fehler werden nach `logs/webdav_files.log` protokolliert.
- Bei selbstsigniertem Zertifikat:
  `$config['webdav_files_verify_ssl'] = false;`
- Freigabelinks funktionieren nur mit Nextcloud (OCS-API), nicht mit reinem
  WebDAV.
- Hochwertige HTML-PDFs: `$config['webdav_files_wkhtmltopdf'] = '/usr/bin/wkhtmltopdf';`

## Autor

**Sebastian Fischer** post@scriptometer.de

## Lizenz

MIT-Lizenz mit Zusatz zur nicht-kommerziellen Nutzung – siehe [LICENSE_DE](LICENSE_DE).
