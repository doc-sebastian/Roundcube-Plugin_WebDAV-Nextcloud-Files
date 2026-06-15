<?php

/**
 * Minimal WebDAV / Nextcloud client used by the webdav_files plugin.
 *
 * Supports: PROPFIND (listing), GET (download to stream), PUT (upload from
 * file with conflict-free naming), MKCOL (recursive folder creation) and
 * Nextcloud OCS public share links.
 *
 * @license GNU GPLv3+
 */
class webdav_files_client
{
    private $base;      // base URL as configured by the user (no trailing slash)
    private $dav_root;  // absolute URL of the WebDAV root (no trailing slash)
    private $type;      // 'nextcloud' | 'webdav'
    private $user;
    private $pass;
    private $verify;
    private $timeout;

    public function __construct($url, $user, $pass, $type = 'nextcloud', $verify = true, $timeout = 90)
    {
        $url = rtrim(trim($url), '/');

        if (!preg_match('#^https?://#i', $url)) {
            throw new Exception('Invalid server URL', 1000);
        }

        $this->base    = $url;
        $this->user    = $user;
        $this->pass    = $pass;
        $this->type    = $type === 'webdav' ? 'webdav' : 'nextcloud';
        $this->verify  = (bool) $verify;
        $this->timeout = max(10, (int) $timeout);

        // For Nextcloud, derive the WebDAV endpoint from the base URL,
        // unless the user already entered a full remote.php URL.
        if ($this->type === 'nextcloud' && !preg_match('#/remote\.php/#', $url)) {
            $this->dav_root = $url . '/remote.php/dav/files/' . rawurlencode($user);
        } else {
            $this->dav_root = $url;
        }
    }

    public function get_type()
    {
        return $this->type;
    }

    /**
     * Normalize a path: removes '.', '..' and duplicate slashes.
     */
    public static function normalize($path)
    {
        $out = [];

        foreach (explode('/', str_replace('\\', '/', (string) $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($out);
                continue;
            }
            $out[] = $segment;
        }

        return '/' . implode('/', $out);
    }

    /**
     * List a directory (PROPFIND, depth 1)
     *
     * @return array of ['name', 'path', 'folder' => bool, 'size' => int]
     */
    public function list_dir($path)
    {
        $path = self::normalize($path);

        $body = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<d:propfind xmlns:d="DAV:"><d:prop>'
            . '<d:resourcetype/><d:getcontentlength/><d:getcontenttype/>'
            . '</d:prop></d:propfind>';

        $res = $this->request('PROPFIND', rtrim($this->url($path), '/') . '/', [
            'headers' => ['Depth: 1', 'Content-Type: application/xml; charset=utf-8'],
            'body'    => $body,
        ]);

        if ($res['code'] != 207) {
            $this->throw_http($res['code']);
        }

        $doc = new DOMDocument();
        if (!@$doc->loadXML($res['body'])) {
            throw new Exception('Invalid WebDAV response', 500);
        }

        $root_path = $this->decode_path((string) parse_url($this->dav_root, PHP_URL_PATH));
        $req       = trim($path, '/');
        $entries   = [];

        foreach ($doc->getElementsByTagNameNS('DAV:', 'response') as $response) {
            $hrefs = $response->getElementsByTagNameNS('DAV:', 'href');
            if (!$hrefs->length) {
                continue;
            }

            $href = trim($hrefs->item(0)->textContent);
            if (preg_match('#^https?://#i', $href)) {
                $href = (string) parse_url($href, PHP_URL_PATH);
            }
            $href = $this->decode_path($href);

            if ($root_path !== '' && strpos($href, $root_path) === 0) {
                $href = substr($href, strlen($root_path));
            }

            $rel = trim($href, '/');
            if ($rel === $req) {
                continue; // the requested directory itself
            }

            $is_folder = $response->getElementsByTagNameNS('DAV:', 'collection')->length > 0;
            $size      = 0;

            $len = $response->getElementsByTagNameNS('DAV:', 'getcontentlength');
            if ($len->length) {
                $size = (int) $len->item(0)->textContent;
            }

            $parts = explode('/', $rel);

            $entries[] = [
                'name'   => end($parts),
                'path'   => '/' . $rel,
                'folder' => $is_folder,
                'size'   => $size,
            ];
        }

        usort($entries, function ($a, $b) {
            if ($a['folder'] != $b['folder']) {
                return $a['folder'] ? -1 : 1;
            }
            return strnatcasecmp($a['name'], $b['name']);
        });

        return $entries;
    }

    /**
     * Download a file into the given stream
     */
    public function get($path, $fp)
    {
        $res = $this->request('GET', $this->url(self::normalize($path)), ['fp' => $fp]);

        if ($res['code'] != 200) {
            $this->throw_http($res['code']);
        }
    }

    /**
     * Upload a local file. Returns true on success, false on name conflict
     * (when $overwrite is false).
     */
    public function put($path, $file, $overwrite = false)
    {
        $fp = @fopen($file, 'r');
        if (!$fp) {
            throw new Exception('Cannot read local temp file', 500);
        }

        $headers = ['Content-Type: application/octet-stream'];
        if (!$overwrite) {
            $headers[] = 'If-None-Match: *';
        }

        $res = $this->request('PUT', $this->url(self::normalize($path)), [
            'headers'    => $headers,
            'infile'     => $fp,
            'infilesize' => filesize($file),
        ]);

        fclose($fp);

        if (in_array($res['code'], [200, 201, 204])) {
            return true;
        }
        if ($res['code'] == 412) {
            return false; // file exists
        }

        $this->throw_http($res['code']);
    }

    /**
     * Upload a file without overwriting anything; appends " (2)", " (3)", ...
     * on conflicts. Returns the final file name.
     */
    public function put_unique($dir, $name, $file)
    {
        $info = pathinfo($name);
        $base = isset($info['filename']) && $info['filename'] !== '' ? $info['filename'] : 'file';
        $ext  = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
        $dir  = rtrim(self::normalize($dir), '/');

        for ($i = 0; $i < 100; $i++) {
            $try = $i === 0 ? $base . $ext : $base . ' (' . ($i + 1) . ')' . $ext;

            if ($this->put($dir . '/' . $try, $file, false)) {
                return $try;
            }
        }

        throw new Exception('Too many file name conflicts', 500);
    }

    /**
     * Check whether a path exists (PROPFIND, depth 0)
     */
    public function exists($path)
    {
        $res = $this->request('PROPFIND', $this->url(self::normalize($path)), [
            'headers' => ['Depth: 0', 'Content-Type: application/xml; charset=utf-8'],
            'body'    => '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>',
        ]);

        if ($res['code'] == 207) {
            return true;
        }
        if ($res['code'] == 404) {
            return false;
        }

        $this->throw_http($res['code']);
    }

    /**
     * Create a folder including all missing parent folders
     */
    public function mkdirs($path)
    {
        $segments = array_filter(explode('/', self::normalize($path)), 'strlen');
        $current  = '';

        foreach ($segments as $segment) {
            $current .= '/' . $segment;
            $res = $this->request('MKCOL', $this->url($current));

            // 201 = created, 405 = already exists
            if (!in_array($res['code'], [201, 405])) {
                $this->throw_http($res['code']);
            }
        }
    }

    /**
     * Create a single folder
     */
    public function mkdir($path)
    {
        $res = $this->request('MKCOL', $this->url(self::normalize($path)));

        if (!in_array($res['code'], [201, 405])) {
            $this->throw_http($res['code']);
        }
    }

    /**
     * Create (or reuse) a Nextcloud public share link for the given path
     * via the OCS API. Returns the public URL.
     */
    public function create_share($path)
    {
        if ($this->type !== 'nextcloud') {
            throw new Exception('Share links require Nextcloud', 1004);
        }

        $path    = self::normalize($path);
        $api     = $this->ocs_base() . '/ocs/v2.php/apps/files_sharing/api/v1/shares';
        $headers = ['OCS-APIRequest: true', 'Accept: application/json'];

        // reuse an existing public link if one exists
        $res = $this->request('GET', $api . '?format=json&reshares=true&path=' . rawurlencode($path), [
            'headers' => $headers,
        ]);

        if ($res['code'] == 200) {
            $json   = json_decode($res['body'], true);
            $shares = isset($json['ocs']['data']) && is_array($json['ocs']['data']) ? $json['ocs']['data'] : [];

            foreach ($shares as $share) {
                if (isset($share['share_type']) && (int) $share['share_type'] === 3 && !empty($share['url'])) {
                    return $share['url'];
                }
            }
        }

        // create a new public link (read-only)
        $res = $this->request('POST', $api . '?format=json', [
            'headers' => array_merge($headers, ['Content-Type: application/x-www-form-urlencoded']),
            'body'    => http_build_query(['path' => $path, 'shareType' => 3, 'permissions' => 1]),
        ]);

        $json = json_decode($res['body'], true);

        if ($res['code'] == 200 && !empty($json['ocs']['data']['url'])) {
            return $json['ocs']['data']['url'];
        }

        if (in_array($res['code'], [401, 403])) {
            $this->throw_http($res['code']);
        }

        $message = isset($json['ocs']['meta']['message']) ? $json['ocs']['meta']['message'] : ('HTTP ' . $res['code']);
        throw new Exception('Creating the share link failed: ' . $message, 1004);
    }

    // ------------------------------------------------------------------
    // internals
    // ------------------------------------------------------------------

    private function ocs_base()
    {
        return preg_replace('#/remote\.php/.*$#', '', $this->base);
    }

    /**
     * Build the encoded WebDAV URL for a (decoded) path
     */
    private function url($path)
    {
        $segments = array_filter(explode('/', (string) $path), 'strlen');
        $encoded  = array_map('rawurlencode', $segments);

        return $this->dav_root . ($encoded ? '/' . implode('/', $encoded) : '');
    }

    private function decode_path($path)
    {
        return implode('/', array_map('rawurldecode', explode('/', (string) $path)));
    }

    /**
     * Execute a HTTP request via curl
     *
     * @return array ['code' => int, 'body' => string|null]
     */
    private function request($method, $url, $opts = [])
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => empty($opts['fp']),
            CURLOPT_USERPWD        => $this->user . ':' . $this->pass,
            CURLOPT_HTTPHEADER     => isset($opts['headers']) ? $opts['headers'] : [],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $this->verify,
            CURLOPT_SSL_VERIFYHOST => $this->verify ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        if (isset($opts['body']) && $opts['body'] !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
        }

        if (!empty($opts['fp'])) {
            curl_setopt($ch, CURLOPT_FILE, $opts['fp']);
        }

        if (!empty($opts['infile'])) {
            curl_setopt($ch, CURLOPT_UPLOAD, true);
            curl_setopt($ch, CURLOPT_INFILE, $opts['infile']);
            curl_setopt($ch, CURLOPT_INFILESIZE, (int) $opts['infilesize']);
        }

        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new Exception("Connection failed: $error", 599);
        }

        return ['code' => $code, 'body' => is_string($body) ? $body : null];
    }

    private function throw_http($code)
    {
        throw new Exception('HTTP error ' . $code, $code);
    }
}
