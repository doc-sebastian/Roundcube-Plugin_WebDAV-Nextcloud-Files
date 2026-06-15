<?php

/**
 * WebDAV / Nextcloud files plugin - German localization
 */

$labels = [];

// buttons
$labels['attachfromcloud'] = 'Aus Cloudspeicher anhängen';
$labels['attachfromcloud_short'] = 'Datei aus Cloudspeicher';
$labels['attachfromcloud_title'] = 'Dateien aus Cloudspeicher anhängen oder verlinken';
$labels['savepdf'] = 'Cloudspeicher';
$labels['savepdf_title'] = 'Diese E-Mail als PDF in Cloudspeicher speichern';
$labels['saveattachments'] = 'Anhang in Cloudspeicher';
$labels['saveattachments_title'] = 'Anhänge dieser E-Mail in Cloudspeicher speichern';

// browser dialog
$labels['browser_title'] = 'Cloudspeicher durchsuchen';
$labels['pickfolder_title'] = 'Ordner auswählen';
$labels['pickfolder_hint'] = 'In den gewünschten Ordner navigieren und dann „Diesen Ordner wählen" klicken.';
$labels['home'] = 'Start';
$labels['loading'] = 'Wird geladen...';
$labels['emptyfolder'] = 'Dieser Ordner ist leer.';
$labels['parentfolder'] = 'Übergeordneter Ordner';
$labels['newfolder'] = 'Neuer Ordner';
$labels['newfolder_prompt'] = 'Name des neuen Ordners:';

// dialog buttons
$labels['attach'] = 'Anhängen';
$labels['insertlink'] = 'Link einfügen';
$labels['selectfolder'] = 'Diesen Ordner wählen';
$labels['save'] = 'Speichern';
$labels['cancel'] = 'Abbrechen';
$labels['browse'] = 'Durchsuchen';

// save dialogs
$labels['saveattach_title'] = 'Anhänge in Cloudspeicher speichern';
$labels['saveattach_intro'] = 'Wählen Sie die zu speichernden Anhänge:';
$labels['savepdf_title'] = 'E-Mail als PDF speichern';
$labels['savepdf_intro'] = 'Die E-Mail wird als PDF im Cloudspeicher gespeichert.';
$labels['targetfolder'] = 'Zielordner';
$labels['filename'] = 'Dateiname (leer = Standardmuster)';

// settings
$labels['settings_section'] = 'Cloudspeicher / WebDAV';
$labels['account_block'] = 'Zugangsdaten';
$labels['server_type'] = 'Servertyp';
$labels['type_nextcloud'] = 'Nextcloud';
$labels['type_webdav'] = 'Allgemeines WebDAV';
$labels['server_url'] = 'Server-URL';
$labels['username'] = 'Benutzername';
$labels['apppassword'] = 'Passwort / App-Passwort';
$labels['apppassword_hint'] = 'Empfohlen: ein App-Passwort (Nextcloud → Einstellungen → Sicherheit). Leer lassen, um das gespeicherte Passwort beizubehalten.';
$labels['folders_block'] = 'Ordner & Dateinamen';
$labels['attach_folder'] = 'Ordner für gespeicherte Anhänge';
$labels['pdf_folder'] = 'Ordner für E-Mail-PDFs';
$labels['pdf_filename'] = 'Dateinamensmuster für PDFs';
$labels['pdf_filename_hint'] = 'Platzhalter: %Y (Jahr), %m (Monat), %d (Tag), %H, %M, %subject% (Betreff).';

// pdf content
$labels['pdf_subject'] = 'Betreff';
$labels['pdf_from'] = 'Von';
$labels['pdf_to'] = 'An';
$labels['pdf_cc'] = 'Kopie';
$labels['pdf_date'] = 'Datum';
$labels['pdf_attachments'] = 'Anhänge';
$labels['pdf_nosubject'] = 'Ohne Betreff';

$messages = [];

// progress messages
$messages['attaching'] = 'Anhänge werden aus Cloudspeicher geladen...';
$messages['creatinglink'] = 'Freigabelink wird erstellt...';
$messages['saving'] = 'Anhänge werden in Cloudspeicher gespeichert...';
$messages['savingpdf'] = 'PDF wird erstellt und gespeichert...';

// success
$messages['attached_ok'] = 'Die Dateien wurden angehängt.';
$messages['link_ok'] = 'Der Link wurde eingefügt.';
$messages['saved_ok'] = '$n Datei(en) wurden in „$folder" gespeichert.';
$messages['pdf_saved_ok'] = 'Die E-Mail wurde als „$file" in „$folder" gespeichert.';

// errors
$messages['error_generic'] = 'Bei der Cloudspeicher-Anfrage ist ein Fehler aufgetreten.';
$messages['error_connection'] = 'Der Cloudspeicher-/WebDAV-Server konnte nicht erreicht werden.';
$messages['error_auth'] = 'Anmeldung fehlgeschlagen. Bitte Zugangsdaten in den Einstellungen prüfen.';
$messages['error_notfound'] = 'Die Datei oder der Ordner wurde nicht gefunden.';
$messages['error_notconfigured'] = 'Cloudspeicher ist noch nicht eingerichtet. Bitte unter Einstellungen → Nextcloud / WebDAV konfigurieren.';
$messages['error_noselection'] = 'Bitte zuerst eine Auswahl treffen.';
$messages['error_nomessage'] = 'Die Nachricht konnte nicht gelesen werden.';
$messages['error_noattachments'] = 'Diese E-Mail enthält keine Anhänge.';
$messages['error_toolarge'] = 'zu groß';
$messages['error_invalidname'] = 'Ungültiger Ordnername.';
$messages['error_savefailed'] = 'Das Speichern ist fehlgeschlagen.';
$messages['error_pdf'] = 'Das PDF konnte nicht erstellt werden.';
$messages['error_someattach'] = 'Einige Dateien konnten nicht angehängt werden:';
$messages['error_somelink'] = 'Für einige Dateien konnte kein Link erstellt werden:';
$messages['error_somesaved'] = 'Einige Dateien konnten nicht gespeichert werden:';
