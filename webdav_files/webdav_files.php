<?php

/**
 * WebDAV / Nextcloud files plugin for Roundcube
 *
 * Features:
 *  - Attach files from Nextcloud/WebDAV to composed messages (file browser)
 *  - Insert public share links to Nextcloud files into composed messages
 *  - Save attachments from a viewed e-mail to Nextcloud/WebDAV
 *  - Save a PDF print of a viewed e-mail to Nextcloud/WebDAV
 *  - Per-account credentials, encrypted like Roundcube's own passwords
 *
 * @author  Custom
 * @license GNU GPLv3+
 */
class webdav_files extends rcube_plugin
{
    public $task = 'mail|settings';

    /** @var rcmail */
    private $rc;

    /** @var webdav_files_client|null */
    private $client = null;

    public function init()
    {
        $this->rc = rcmail::get_instance();

        $this->load_config();
        $this->add_texts('localization/', true);

        require_once __DIR__ . '/lib/webdav_files_client.php';

        if ($this->rc->task == 'mail') {
            // shared AJAX endpoints (browser + actions)
            $this->register_action('plugin.webdav_files.browse', [$this, 'action_browse']);
            $this->register_action('plugin.webdav_files.attach', [$this, 'action_attach']);
            $this->register_action('plugin.webdav_files.link', [$this, 'action_link']);
            $this->register_action('plugin.webdav_files.save_attachments', [$this, 'action_save_attachments']);
            $this->register_action('plugin.webdav_files.list_attachments', [$this, 'action_list_attachments']);
            $this->register_action('plugin.webdav_files.save_pdf', [$this, 'action_save_pdf']);
            $this->register_action('plugin.webdav_files.mkdir', [$this, 'action_mkdir']);

            if ($this->rc->action == 'compose') {
                $this->include_assets();
                $this->add_compose_buttons();
                $this->set_browser_env();
            }
            elseif (in_array($this->rc->action, ['show', 'preview'])) {
                $this->include_assets();
                $this->add_message_buttons();
                $this->set_message_env();
            }
            elseif ($this->rc->action == '' || $this->rc->action == 'list') {
                // main mailbox view: provide commands for the message-list
                // context menu and the "More" toolbar menu
                $this->include_assets();
                $this->add_list_buttons();
                $this->set_message_env();
            }
        }
        elseif ($this->rc->task == 'settings') {
            // load assets on every settings page so the section list icon is
            // present on the sections index too
            $this->include_stylesheet($this->local_skin_path() . '/webdav_files.css');
            $this->include_script('webdav_files.js');
            $this->add_hook('preferences_sections_list', [$this, 'prefs_section']);
            $this->add_hook('preferences_list', [$this, 'prefs_list']);
            $this->add_hook('preferences_save', [$this, 'prefs_save']);
        }
    }

    private function include_assets()
    {
        $this->include_script('webdav_files.js');
        $this->include_stylesheet($this->local_skin_path() . '/webdav_files.css');
    }

    /**
     * Compose toolbar: "Attach from Nextcloud" button next to "Attach"
     */
    private function add_compose_buttons()
    {
        $this->add_button([
            'type'       => 'link',
            'command'    => 'plugin.webdav_files.open_browser',
            'class'      => 'button webdav-files attach-cloud disabled',
            'classact'   => 'button webdav-files attach-cloud',
            'classsel'   => 'button webdav-files attach-cloud pressed',
            'label'      => 'webdav_files.attachfromcloud',
            'title'      => 'webdav_files.attachfromcloud_title',
            'innerclass' => 'inner',
        ], 'toolbar');
    }

    /**
     * Message view: PDF-export button (top-right, with the view buttons).
     * The "save attachments to cloud" button is injected next to the
     * attachment list by JavaScript (more reliable across skins).
     */
    private function add_message_buttons()
    {
        // top-right toolbar (next to reply/forward/open-in-new-window)
        $this->add_button([
            'type'       => 'link',
            'command'    => 'plugin.webdav_files.save_pdf',
            'class'      => 'button webdav-files save-pdf disabled',
            'classact'   => 'button webdav-files save-pdf',
            'classsel'   => 'button webdav-files save-pdf pressed',
            'label'      => 'webdav_files.savepdf',
            'title'      => 'webdav_files.savepdf_title',
            'innerclass' => 'inner',
        ], 'toolbar');
    }

    /**
     * Main mailbox view: the PDF command appears in the message-list "More"
     * toolbar menu / right-click menu. The save-attachments command stays
     * registered (so it can appear in the right-click menu) but gets no
     * top toolbar button, as it is not needed there.
     */
    private function add_list_buttons()
    {
        $this->add_button([
            'type'       => 'link',
            'command'    => 'plugin.webdav_files.save_pdf',
            'class'      => 'button webdav-files save-pdf disabled',
            'classact'   => 'button webdav-files save-pdf',
            'label'      => 'webdav_files.savepdf',
            'title'      => 'webdav_files.savepdf_title',
            'innerclass' => 'inner',
        ], 'toolbar');
    }

    /**
     * Configuration shared by message view and list view
     */
    private function set_message_env()
    {
        $this->rc->output->set_env('webdav_files_configured', $this->is_configured());
        $this->rc->output->set_env('webdav_files', [
            'configured'    => $this->is_configured(),
            'attach_folder' => $this->rc->config->get('webdav_files_attach_folder', '/Mail/Attachments'),
            'pdf_folder'    => $this->rc->config->get('webdav_files_pdf_folder', '/Mail/PDF'),
        ]);
        $this->rc->output->add_label('webdav_files.saveattachments', 'webdav_files.savepdf');
    }

    /**
     * Pass configuration (default folders, configured-state) to the frontend
     */
    private function set_browser_env()
    {
        $this->rc->output->set_env('webdav_files', [
            'configured'    => $this->is_configured(),
            'attach_folder' => $this->rc->config->get('webdav_files_attach_folder', '/Mail/Attachments'),
            'pdf_folder'    => $this->rc->config->get('webdav_files_pdf_folder', '/Mail/PDF'),
            'start_path'    => '/',
        ]);
        $this->rc->output->add_label('webdav_files.attachfromcloud', 'webdav_files.attachfromcloud_title');
    }

    // ==================================================================
    // AJAX actions
    // ==================================================================

    /**
     * Browse a directory in the cloud
     */
    public function action_browse()
    {
        if (!($client = $this->get_client())) {
            return $this->send_error($this->gettext('error_notconfigured'));
        }

        $path = (string) rcube_utils::get_input_value('_path', rcube_utils::INPUT_POST);
        $path = webdav_files_client::normalize($path === '' ? '/' : $path);

        try {
            $entries = $client->list_dir($path);
        } catch (Exception $e) {
            return $this->send_error($this->translate_exception($e));
        }

        $this->rc->output->command('plugin.webdav_files.browser_data', [
            'path'    => $path,
            'entries' => $entries,
        ]);
        $this->rc->output->send();
    }

    /**
     * Create a folder in the cloud
     */
    public function action_mkdir()
    {
        if (!($client = $this->get_client())) {
            return $this->send_error($this->gettext('error_notconfigured'));
        }

        $path = webdav_files_client::normalize((string) rcube_utils::get_input_value('_path', rcube_utils::INPUT_POST));
        $name = trim((string) rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST, true));

        if ($name === '' || strpbrk($name, '/\\') !== false) {
            return $this->send_error($this->gettext('error_invalidname'));
        }

        try {
            $client->mkdir($path . '/' . $name);
        } catch (Exception $e) {
            return $this->send_error($this->translate_exception($e));
        }

        $this->action_browse_to($client, $path);
    }

    private function action_browse_to($client, $path)
    {
        try {
            $entries = $client->list_dir($path);
        } catch (Exception $e) {
            return $this->send_error($this->translate_exception($e));
        }

        $this->rc->output->command('plugin.webdav_files.browser_data', [
            'path'    => $path,
            'entries' => $entries,
        ]);
        $this->rc->output->send();
    }

    /**
     * Download files from the cloud and attach them to the current compose draft
     */
    public function action_attach()
    {
        if (!($client = $this->get_client())) {
            return $this->send_error($this->gettext('error_notconfigured'));
        }

        $paths      = (array) rcube_utils::get_input_value('_paths', rcube_utils::INPUT_POST);
        $compose_id = (string) rcube_utils::get_input_value('_id', rcube_utils::INPUT_POST);

        if (empty($paths) || $compose_id === '') {
            return $this->send_error($this->gettext('error_generic'));
        }

        $session_key = 'compose_data_' . $compose_id;

        if (!isset($_SESSION[$session_key])) {
            $this->log("compose session '$session_key' not found");
            return $this->send_error($this->gettext('error_generic'));
        }

        $limit    = $this->attach_limit();
        $attached = [];
        $errors   = [];

        foreach ($paths as $path) {
            $path = webdav_files_client::normalize($path);
            $name = basename($path);

            $tmp = $this->temp_file();
            $fp  = fopen($tmp, 'w');

            if (!$fp) {
                $this->log("cannot open temp file for '$name'");
                $errors[] = $name;
                continue;
            }

            try {
                $client->get($path, $fp);
                fclose($fp);
            } catch (\Throwable $e) {
                if (is_resource($fp)) {
                    fclose($fp);
                }
                @unlink($tmp);
                $this->log("download of '$path' failed: " . $e->getMessage());
                $errors[] = $name;
                continue;
            }

            $size = filesize($tmp);

            if ($limit && $size > $limit) {
                @unlink($tmp);
                $errors[] = $name . ' (' . $this->gettext('error_toolarge') . ')';
                continue;
            }

            $ctype = rcube_mime::file_content_type($tmp, $name) ?: 'application/octet-stream';

            $attachment = [
                'path'     => $tmp,
                'size'     => $size,
                'name'     => $name,
                'mimetype' => $ctype,
                'group'    => $compose_id,
            ];

            $this->log("Adding attachment by direct session assignment: $name, size: $size, mimetype: $ctype, temp: $tmp");
            
            // Generate attachment ID
            $id = 'webdav_' . md5($name . microtime());
            
            // Prepare attachment for session storage
            $attachment_data = [
                'path'     => $tmp,
                'size'     => $size,
                'name'     => $name,
                'mimetype' => $ctype,
                'group'    => $compose_id,
                'id'       => $id
            ];
            
            // Directly add to session - bypass the problematic hook
            $this->rc->session->append($session_key . '.attachments', $id, $attachment_data);

            $attached[] = [
                'id'       => $id,
                'name'     => $name,
                'size'     => $this->rc->show_bytes((int) $size),
                'mimetype' => $ctype,
            ];
            
            $this->log("Successfully added attachment '$name' with ID '$id'");
        }

        $this->rc->output->command('plugin.webdav_files.attached', [
            'attachments' => $attached,
            'errors'      => $errors,
        ]);
        $this->rc->output->send();
    }

    /**
     * Create a temporary file in Roundcube's temp dir and return its path.
     * Avoids version-specific helpers; tempnam() always works.
     */
    private function temp_file($suffix = '')
    {
        $dir = $this->rc->config->get('temp_dir');
        if (!$dir || !is_dir($dir) || !is_writable($dir)) {
            $dir = sys_get_temp_dir();
        }

        $tmp = tempnam($dir, 'webdav_');
        if ($tmp === false) {
            throw new Exception('Cannot create temporary file', 500);
        }

        if ($suffix !== '') {
            $with = $tmp . $suffix;
            if (@rename($tmp, $with)) {
                $tmp = $with;
            }
        }

        return $tmp;
    }

    private function attach_limit()
    {
        $max    = (int) $this->rc->config->get('webdav_files_max_attach_size', 0);
        $rc_max = parse_bytes(ini_get('upload_max_filesize'));

        if ($max > 0) {
            return $rc_max ? min($max, $rc_max) : $max;
        }

        return $rc_max ?: 0;
    }

    /**
     * Create public share links for the selected files
     */
    public function action_link()
    {
        if (!($client = $this->get_client())) {
            return $this->send_error($this->gettext('error_notconfigured'));
        }

        $paths = (array) rcube_utils::get_input_value('_paths', rcube_utils::INPUT_POST);

        if (empty($paths)) {
            return $this->send_error($this->gettext('error_generic'));
        }

        $links  = [];
        $errors = [];

        foreach ($paths as $path) {
            $path = webdav_files_client::normalize($path);
            try {
                $links[] = ['name' => basename($path), 'url' => $client->create_share($path)];
            } catch (Exception $e) {
                $errors[] = basename($path);
            }
        }

        $this->rc->output->command('plugin.webdav_files.links', [
            'links'  => $links,
            'errors' => $errors,
        ]);
        $this->rc->output->send();
    }

    /**
     * Return the list of attachments of a viewed message (used by the dialog)
     */
    public function action_list_attachments()
    {
        $message = $this->load_message();

        if (!$message) {
            return $this->send_error($this->gettext('error_nomessage'));
        }

        $list = [];
        foreach ((array) $message->attachments as $part) {
            $list[] = [
                'mime_id'  => $part->mime_id,
                'name'     => $this->part_filename($part),
                'size'     => $this->rc->show_bytes((int) $part->size),
                'mimetype' => $part->mimetype,
            ];
        }

        $this->rc->output->command('plugin.webdav_files.attachment_list', [
            'attachments' => $list,
            'subject'     => (string) $message->subject,
        ]);
        $this->rc->output->send();
    }

    /**
     * Save selected attachments of a viewed message to the cloud
     */
    public function action_save_attachments()
    {
        if (!($client = $this->get_client())) {
            return $this->send_error($this->gettext('error_notconfigured'));
        }

        $message = $this->load_message();
        if (!$message) {
            return $this->send_error($this->gettext('error_nomessage'));
        }

        $folder    = $this->target_folder((string) rcube_utils::get_input_value('_folder', rcube_utils::INPUT_POST));
        $selected  = (array) rcube_utils::get_input_value('_parts', rcube_utils::INPUT_POST);
        $filenames = (array) rcube_utils::get_input_value('_filenames', rcube_utils::INPUT_POST);
        
        $this->log("Saving attachments to folder: $folder");
        $this->log("Selected attachments: " . implode(', ', $selected));
        $this->log("Custom filenames: " . json_encode($filenames));

        try {
            $client->mkdirs($folder);
        } catch (\Throwable $e) {
            $this->log('mkdirs failed: ' . $e->getMessage());
            return $this->send_error($this->translate_exception($e));
        }

        $saved  = [];
        $errors = [];

        foreach ((array) $message->attachments as $part) {
            if (!empty($selected) && !in_array($part->mime_id, $selected)) {
                continue;
            }

            $originalName = $this->part_filename($part);
            
            // Use custom filename if provided, otherwise use original name
            $name = isset($filenames[$part->mime_id]) ? $filenames[$part->mime_id] : $originalName;
            
            if (isset($filenames[$part->mime_id]) && $filenames[$part->mime_id] !== $originalName) {
                $this->log("Using custom filename for attachment {$part->mime_id}: {$filenames[$part->mime_id]} (original: $originalName)");
            }
            
            $tmp  = $this->temp_file();
            $fp   = fopen($tmp, 'w');

            if (!$fp) {
                $errors[] = $name;
                continue;
            }

            try {
                $message->get_part_body($part->mime_id, false, 0, $fp);
                fclose($fp);
                $final   = $client->put_unique($folder, $name, $tmp);
                $saved[] = $final;
            } catch (\Throwable $e) {
                if (is_resource($fp)) {
                    fclose($fp);
                }
                $this->log("saving attachment '$name' failed: " . $e->getMessage());
                $errors[] = $name;
            }

            @unlink($tmp);
        }

        if (empty($saved) && !empty($errors)) {
            return $this->send_error($this->gettext('error_savefailed'));
        }

        $this->rc->output->command('plugin.webdav_files.saved', [
            'folder' => $folder,
            'saved'  => $saved,
            'errors' => $errors,
        ]);
        $this->rc->output->send();
    }

    /**
     * Save a PDF print of the viewed message to the cloud
     */
    public function action_save_pdf()
    {
        if (!($client = $this->get_client())) {
            return $this->send_error($this->gettext('error_notconfigured'));
        }

        $message = $this->load_message();
        if (!$message) {
            return $this->send_error($this->gettext('error_nomessage'));
        }

        $folder   = $this->target_folder((string) rcube_utils::get_input_value('_folder', rcube_utils::INPUT_POST), 'pdf');
        $filename = $this->build_pdf_filename($message,
            (string) rcube_utils::get_input_value('_filename', rcube_utils::INPUT_POST, true));

        $tmp = $this->temp_file();

        try {
            $this->render_pdf($message, $tmp);
        } catch (\Throwable $e) {
            @unlink($tmp);
            $this->log('PDF rendering failed: ' . $e->getMessage());
            return $this->send_error($this->gettext('error_pdf'));
        }

        try {
            $client->mkdirs($folder);
            $final = $client->put_unique($folder, $filename, $tmp);
        } catch (\Throwable $e) {
            @unlink($tmp);
            $this->log('PDF upload failed: ' . $e->getMessage());
            return $this->send_error($this->translate_exception($e));
        }

        @unlink($tmp);

        $this->rc->output->command('plugin.webdav_files.pdf_saved', [
            'folder' => $folder,
            'file'   => $final,
        ]);
        $this->rc->output->send();
    }

    // ==================================================================
    // PDF rendering
    // ==================================================================

    private function render_pdf($message, $target)
    {
        $bin = trim((string) $this->rc->config->get('webdav_files_wkhtmltopdf', ''));

        if ($bin !== '' && is_executable($bin)) {
            if ($this->render_pdf_wkhtmltopdf($message, $target, $bin)) {
                return;
            }
        }

        $this->render_pdf_builtin($message, $target);
    }

    /**
     * High quality rendering via wkhtmltopdf (if configured)
     */
    private function render_pdf_wkhtmltopdf($message, $target, $bin)
    {
        $html = $this->message_html($message);
        $in   = $this->temp_file('.html');
        file_put_contents($in, $html);

        $cmd = escapeshellarg($bin) . ' --quiet --encoding utf-8 --enable-local-file-access '
            . escapeshellarg($in) . ' ' . escapeshellarg($target) . ' 2>/dev/null';

        @exec($cmd, $out, $code);
        @unlink($in);

        return $code === 0 && file_exists($target) && filesize($target) > 0;
    }

    /**
     * Always-available text PDF fallback
     */
    private function render_pdf_builtin($message, $target)
    {
        require_once __DIR__ . '/lib/webdav_files_pdf.php';

        $pdf = new webdav_files_pdf();
        $pdf->set_title((string) $message->subject);

        $pdf->add($this->gettext('pdf_subject') . ': ' . $message->subject, true);
        $pdf->add($this->gettext('pdf_from') . ': ' . $this->header_text($message, 'from'));
        $pdf->add($this->gettext('pdf_to') . ': ' . $this->header_text($message, 'to'));
        if ($cc = $this->header_text($message, 'cc')) {
            $pdf->add($this->gettext('pdf_cc') . ': ' . $cc);
        }
        $pdf->add($this->gettext('pdf_date') . ': ' . $this->rc->format_date($message->headers->date));

        if (!empty($message->attachments)) {
            $names = [];
            foreach ($message->attachments as $part) {
                $names[] = $this->part_filename($part);
            }
            $pdf->add($this->gettext('pdf_attachments') . ': ' . implode(', ', $names));
        }

        $pdf->add_separator();
        $pdf->add('');
        $pdf->add($this->message_plaintext($message));

        file_put_contents($target, $pdf->render());
    }

    private function message_plaintext($message)
    {
        $text = (string) $message->first_text_part();

        if ($text === '' && ($html = $message->first_html_part())) {
            $h2t  = new rcube_html2text($html, false, true);
            $text = $h2t->get_text();
        }

        return $text;
    }

    private function message_html($message)
    {
        $body = $message->first_html_part();
        if ($body === null || $body === '') {
            $body = '<pre style="white-space:pre-wrap;font-family:monospace">'
                . rcube::Q($this->message_plaintext($message)) . '</pre>';
        }

        $head = '<table style="border-collapse:collapse;font:13px sans-serif;margin-bottom:1em">'
            . $this->html_row($this->gettext('pdf_subject'), $message->subject)
            . $this->html_row($this->gettext('pdf_from'), $this->header_text($message, 'from'))
            . $this->html_row($this->gettext('pdf_to'), $this->header_text($message, 'to'))
            . $this->html_row($this->gettext('pdf_date'), $this->rc->format_date($message->headers->date))
            . '</table><hr>';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
            . '<body style="font:13px sans-serif;color:#000">' . $head . $body . '</body></html>';
    }

    private function html_row($label, $value)
    {
        return '<tr><td style="font-weight:bold;padding:2px 8px 2px 0;vertical-align:top">'
            . rcube::Q($label) . ':</td><td style="padding:2px 0">' . rcube::Q($value) . '</td></tr>';
    }

    private function header_text($message, $field)
    {
        // get_header() returns the decoded header value (addresses incl. names)
        $value = $message->get_header($field);

        return $value !== null ? (string) $value : '';
    }

    /**
     * Build the PDF filename from the configurable pattern.
     * Placeholders: %Y %m %d %H %M %subject%
     * Default pattern: "%Y_%m_%d eMail # %subject%"
     */
    private function build_pdf_filename($message, $override = '')
    {
        $pattern = $override !== ''
            ? $override
            : (string) $this->rc->config->get('webdav_files_pdf_filename', '%Y_%m_%d eMail # %subject%');

        $ts = time();
        if (!empty($message->headers->date)) {
            $parsed = @strtotime($message->headers->date);
            if ($parsed) {
                $ts = $parsed;
            }
        }
        $subject = trim((string) $message->subject);
        if ($subject === '') {
            $subject = $this->gettext('pdf_nosubject');
        }

        $name = strtr($pattern, [
            '%Y'        => date('Y', $ts),
            '%m'        => date('m', $ts),
            '%d'        => date('d', $ts),
            '%H'        => date('H', $ts),
            '%M'        => date('i', $ts),
            '%subject%' => $subject,
        ]);

        // strip characters that are problematic in file names
        $name = preg_replace('#[/\\\\:*?"<>|\r\n\t]+#', ' ', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $name = mb_substr($name, 0, 150);

        if (strtolower(substr($name, -4)) !== '.pdf') {
            $name .= '.pdf';
        }

        return $name;
    }

    // ==================================================================
    // Settings
    // ==================================================================

    public function prefs_section($p)
    {
        $p['list']['webdav_files'] = [
            'id'      => 'webdav_files',
            'section' => $this->gettext('settings_section'),
            'class'   => 'webdav_files',
        ];

        return $p;
    }

    public function prefs_list($p)
    {
        if ($p['section'] != 'webdav_files') {
            return $p;
        }

        $config  = $this->rc->config;
        $options = [];

        $type = new html_select(['name' => '_wdf_type', 'id' => 'wdf-type']);
        $type->add($this->gettext('type_nextcloud'), 'nextcloud');
        $type->add($this->gettext('type_webdav'), 'webdav');
        $options['type'] = [
            'title'   => html::label('wdf-type', $this->gettext('server_type')),
            'content' => $type->show($config->get('webdav_files_type', 'nextcloud')),
        ];

        $url = new html_inputfield([
            'name' => '_wdf_url', 'id' => 'wdf-url', 'size' => 50,
            'placeholder' => 'https://cloud.example.com',
        ]);
        $options['url'] = [
            'title'   => html::label('wdf-url', $this->gettext('server_url')),
            'content' => $url->show((string) $config->get('webdav_files_url', '')),
        ];

        $user = new html_inputfield(['name' => '_wdf_user', 'id' => 'wdf-user', 'size' => 30, 'autocomplete' => 'off']);
        $options['user'] = [
            'title'   => html::label('wdf-user', $this->gettext('username')),
            'content' => $user->show((string) $config->get('webdav_files_user', '')),
        ];

        // never echo the stored password; show a placeholder if one is set
        $has_pass = (string) $config->get('webdav_files_pass', '') !== '';
        $pass = new html_passwordfield([
            'name' => '_wdf_pass', 'id' => 'wdf-pass', 'size' => 30, 'autocomplete' => 'new-password',
            'placeholder' => $has_pass ? str_repeat('•', 8) : '',
        ]);
        $options['pass'] = [
            'title'   => html::label('wdf-pass', $this->gettext('apppassword')),
            'content' => $pass->show() . html::span('wdf-hint', rcube::Q($this->gettext('apppassword_hint'))),
        ];

        $p['blocks']['webdav_files_account'] = [
            'name'    => rcube::Q($this->gettext('account_block')),
            'options' => $options,
        ];

        // --- folders / filename block -----------------------------------
        $options = [];

        $att_folder = new html_inputfield(['name' => '_wdf_attach_folder', 'id' => 'wdf-attach-folder', 'size' => 40]);
        $options['attach_folder'] = [
            'title'   => html::label('wdf-attach-folder', $this->gettext('attach_folder')),
            'content' => $att_folder->show((string) $config->get('webdav_files_attach_folder', '/Mail/Attachments'))
                . $this->browse_button('wdf-attach-folder'),
        ];

        $pdf_folder = new html_inputfield(['name' => '_wdf_pdf_folder', 'id' => 'wdf-pdf-folder', 'size' => 40]);
        $options['pdf_folder'] = [
            'title'   => html::label('wdf-pdf-folder', $this->gettext('pdf_folder')),
            'content' => $pdf_folder->show((string) $config->get('webdav_files_pdf_folder', '/Mail/PDF'))
                . $this->browse_button('wdf-pdf-folder'),
        ];

        $pdf_name = new html_inputfield([
            'name' => '_wdf_pdf_filename', 'id' => 'wdf-pdf-filename', 'size' => 40,
        ]);
        $options['pdf_filename'] = [
            'title'   => html::label('wdf-pdf-filename', $this->gettext('pdf_filename')),
            'content' => $pdf_name->show((string) $config->get('webdav_files_pdf_filename', '%Y_%m_%d eMail # %subject%'))
                . html::span('wdf-hint', rcube::Q($this->gettext('pdf_filename_hint'))),
        ];

        $p['blocks']['webdav_files_folders'] = [
            'name'    => rcube::Q($this->gettext('folders_block')),
            'options' => $options,
        ];

        // load the folder-browser assets on the settings page too
        $this->include_assets();
        $this->rc->output->set_env('webdav_files', ['configured' => $this->is_configured()]);

        return $p;
    }

    private function browse_button($target)
    {
        $label = rcube::Q($this->gettext('browse'));
        $cmd   = rcube::JQ("plugin.webdav_files.pick_folder");
        $tgt   = rcube::JQ($target);

        return ' <a href="#" class="button wdf-browse-folder" '
            . 'onclick="return rcmail.command(\'' . $cmd . '\', \'' . $tgt . '\')">'
            . $label . '</a>';
    }

    public function prefs_save($p)
    {
        if ($p['section'] != 'webdav_files') {
            return $p;
        }

        $config = $this->rc->config;

        $p['prefs']['webdav_files_type'] =
            rcube_utils::get_input_value('_wdf_type', rcube_utils::INPUT_POST) === 'webdav' ? 'webdav' : 'nextcloud';
        $p['prefs']['webdav_files_url'] =
            rtrim(trim((string) rcube_utils::get_input_value('_wdf_url', rcube_utils::INPUT_POST)), '/');
        $p['prefs']['webdav_files_user'] =
            trim((string) rcube_utils::get_input_value('_wdf_user', rcube_utils::INPUT_POST));

        // only update the password if a new one was entered; encrypt it
        $pass = (string) rcube_utils::get_input_value('_wdf_pass', rcube_utils::INPUT_POST);
        if ($pass !== '') {
            $p['prefs']['webdav_files_pass'] = $this->rc->encrypt($pass);
        }

        $p['prefs']['webdav_files_attach_folder'] = webdav_files_client::normalize(
            (string) rcube_utils::get_input_value('_wdf_attach_folder', rcube_utils::INPUT_POST));
        $p['prefs']['webdav_files_pdf_folder'] = webdav_files_client::normalize(
            (string) rcube_utils::get_input_value('_wdf_pdf_folder', rcube_utils::INPUT_POST));

        $name = trim((string) rcube_utils::get_input_value('_wdf_pdf_filename', rcube_utils::INPUT_POST, true));
        $p['prefs']['webdav_files_pdf_filename'] = $name !== '' ? $name : '%Y_%m_%d eMail # %subject%';

        return $p;
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * Per-account credentials are read from user prefs first, falling back to
     * server-wide config defaults. The password is decrypted on demand and
     * never sent to the browser.
     */
    private function is_configured()
    {
        $config = $this->rc->config;
        return (string) $config->get('webdav_files_url', '') !== ''
            && (string) $config->get('webdav_files_user', '') !== ''
            && (string) $config->get('webdav_files_pass', '') !== '';
    }

    private function get_client()
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if (!$this->is_configured()) {
            return null;
        }

        $config = $this->rc->config;
        $pass   = $this->rc->decrypt((string) $config->get('webdav_files_pass', ''));

        try {
            $this->client = new webdav_files_client(
                (string) $config->get('webdav_files_url', ''),
                (string) $config->get('webdav_files_user', ''),
                (string) $pass,
                (string) $config->get('webdav_files_type', 'nextcloud'),
                (bool) $config->get('webdav_files_verify_ssl', true),
                (int) $config->get('webdav_files_timeout', 90)
            );
        } catch (Exception $e) {
            $this->log($e->getMessage());
            return null;
        }

        return $this->client;
    }

    /**
     * Resolve the target folder: explicit param (from the folder browser) wins,
     * otherwise the configured default for the given purpose is used.
     */
    private function target_folder($param, $purpose = 'attach')
    {
        $param = trim($param);
        if ($param !== '') {
            return webdav_files_client::normalize($param);
        }

        $default = $purpose === 'pdf'
            ? $this->rc->config->get('webdav_files_pdf_folder', '/Mail/PDF')
            : $this->rc->config->get('webdav_files_attach_folder', '/Mail/Attachments');

        return webdav_files_client::normalize($default);
    }

    private function load_message()
    {
        $uid  = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_GPC);
        $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_GPC);

        if (!$uid) {
            return null;
        }

        if ($mbox) {
            $this->rc->storage->set_folder($mbox);
        }

        $message = new rcube_message($uid, $mbox);

        return empty($message->headers) ? null : $message;
    }

    private function part_filename($part)
    {
        $name = $part->filename;

        if ($name === null || $name === '') {
            $ext  = (array) rcube_mime::get_mime_extensions($part->mimetype);
            $name = 'attachment-' . $part->mime_id . (count($ext) ? '.' . $ext[0] : '');
        }

        return $name;
    }

    private function translate_exception(Exception $e)
    {
        switch ($e->getCode()) {
            case 401:
            case 403:
                return $this->gettext('error_auth');
            case 404:
                return $this->gettext('error_notfound');
            case 599:
            case 1000:
                return $this->gettext('error_connection');
            default:
                $this->log($e->getMessage());
                return $this->gettext('error_generic');
        }
    }

    private function send_error($message)
    {
        $this->rc->output->command('plugin.webdav_files.error', ['message' => $message]);
        $this->rc->output->send();
    }

    private function log($message)
    {
        rcube::write_log('webdav_files', $message);
    }
}
