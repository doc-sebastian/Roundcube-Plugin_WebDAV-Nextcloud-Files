<?php

/**
 * WebDAV / Nextcloud files plugin - English localization (default)
 */

$labels = [];

// buttons
$labels['attachfromcloud'] = 'Attach from Cloudstorage';
$labels['attachfromcloud_short'] = 'Cloudstorage';
$labels['attachfromcloud_title'] = 'Attach or link files from Cloudstorage';
$labels['savepdf'] = 'Cloud storage';
$labels['savepdf_title'] = 'Save this e-mail as PDF to Cloudstorage';
$labels['saveattachments'] = 'Attachment to cloud storage';
$labels['saveattachments_title'] = 'Save this e-mail\'s attachments to Cloudstorage';

// browser dialog
$labels['browser_title'] = 'Browse Cloudstorage';
$labels['pickfolder_title'] = 'Select a folder';
$labels['pickfolder_hint'] = 'Navigate into the folder you want, then click "Use this folder".';
$labels['home'] = 'Home';
$labels['loading'] = 'Loading...';
$labels['emptyfolder'] = 'This folder is empty.';
$labels['parentfolder'] = 'Parent folder';
$labels['newfolder'] = 'New folder';
$labels['newfolder_prompt'] = 'Name of the new folder:';

// dialog buttons
$labels['attach'] = 'Attach';
$labels['insertlink'] = 'Insert link';
$labels['selectfolder'] = 'Use this folder';
$labels['save'] = 'Save';
$labels['cancel'] = 'Cancel';
$labels['browse'] = 'Browse';

// save dialogs
$labels['saveattach_title'] = 'Save attachments to Cloudstorage';
$labels['saveattach_intro'] = 'Select the attachments to save:';
$labels['savepdf_title'] = 'Save e-mail as PDF';
$labels['savepdf_intro'] = 'The e-mail will be saved as a PDF to Cloudstorage.';
$labels['targetfolder'] = 'Target folder';
$labels['filename'] = 'File name (empty = default pattern)';

// settings
$labels['settings_section'] = 'Cloudstorage / WebDAV';
$labels['account_block'] = 'Credentials';
$labels['server_type'] = 'Server type';
$labels['type_nextcloud'] = 'Nextcloud';
$labels['type_webdav'] = 'Generic WebDAV';
$labels['server_url'] = 'Server URL';
$labels['username'] = 'Username';
$labels['apppassword'] = 'Password / app password';
$labels['apppassword_hint'] = 'Recommended: an app password (Nextcloud → Settings → Security). Leave empty to keep the stored password.';
$labels['folders_block'] = 'Folders & file names';
$labels['attach_folder'] = 'Folder for saved attachments';
$labels['pdf_folder'] = 'Folder for e-mail PDFs';
$labels['pdf_filename'] = 'File name pattern for PDFs';
$labels['pdf_filename_hint'] = 'Placeholders: %Y (year), %m (month), %d (day), %H, %M, %subject%.';

// pdf content
$labels['pdf_subject'] = 'Subject';
$labels['pdf_from'] = 'From';
$labels['pdf_to'] = 'To';
$labels['pdf_cc'] = 'Cc';
$labels['pdf_date'] = 'Date';
$labels['pdf_attachments'] = 'Attachments';
$labels['pdf_nosubject'] = 'No subject';

$messages = [];

// progress messages
$messages['attaching'] = 'Downloading attachments from Cloudstorage...';
$messages['creatinglink'] = 'Creating share link...';
$messages['saving'] = 'Saving attachments to Cloudstorage...';
$messages['savingpdf'] = 'Creating and saving PDF...';

// success
$messages['attached_ok'] = 'The files have been attached.';
$messages['link_ok'] = 'The link has been inserted.';
$messages['saved_ok'] = 'Saved $n file(s) to "$folder".';
$messages['pdf_saved_ok'] = 'The e-mail was saved as "$file" in "$folder".';

// errors
$messages['error_generic'] = 'An error occurred during the Cloudstorage request.';
$messages['error_connection'] = 'The Cloudstorage/WebDAV server could not be reached.';
$messages['error_auth'] = 'Authentication failed. Please check your credentials in the settings.';
$messages['error_notfound'] = 'The file or folder was not found.';
$messages['error_notconfigured'] = 'Cloudstorage is not configured yet. Please set it up under Settings → Nextcloud / WebDAV.';
$messages['error_noselection'] = 'Please make a selection first.';
$messages['error_nomessage'] = 'The message could not be read.';
$messages['error_noattachments'] = 'This e-mail has no attachments.';
$messages['error_toolarge'] = 'too large';
$messages['error_invalidname'] = 'Invalid folder name.';
$messages['error_savefailed'] = 'Saving failed.';
$messages['error_pdf'] = 'The PDF could not be created.';
$messages['error_someattach'] = 'Some files could not be attached:';
$messages['error_somelink'] = 'Could not create a link for some files:';
$messages['error_somesaved'] = 'Some files could not be saved:';
