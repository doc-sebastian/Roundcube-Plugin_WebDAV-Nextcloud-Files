<?php

/**
 * Minimal dependency-free PDF writer used by the webdav_files plugin to
 * create a text-based "PDF print" of an e-mail.
 *
 * Produces PDF 1.4, A4, monospaced (Courier/Courier-Bold), WinAnsi encoding
 * (covers German umlauts; other characters are transliterated).
 *
 * For full HTML rendering, configure wkhtmltopdf in the plugin config -
 * this class is the fallback that always works.
 *
 * @license GNU GPLv3+
 */
class webdav_files_pdf
{
    const PAGE_W = 595.28;   // A4 width in points
    const PAGE_H = 841.89;   // A4 height in points
    const MARGIN = 56;
    const SIZE   = 9;        // font size
    const LEAD   = 12.5;     // line height

    private $lines = [];     // list of [cp1252-text, bold]
    private $title = '';

    public function set_title($title)
    {
        $this->title = (string) $title;
    }

    /**
     * Add (possibly multi-line) UTF-8 text; lines are wrapped automatically.
     */
    public function add($text, $bold = false)
    {
        $max = (int) floor((self::PAGE_W - 2 * self::MARGIN) / (self::SIZE * 0.6));

        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
            $line = $this->to_cp1252($line);

            if ($line === '') {
                $this->lines[] = ['', (bool) $bold];
                continue;
            }

            foreach (explode("\n", wordwrap($line, $max, "\n", true)) as $wrapped) {
                $this->lines[] = [$wrapped, (bool) $bold];
            }
        }
    }

    /**
     * Add a horizontal separator line
     */
    public function add_separator()
    {
        $max = (int) floor((self::PAGE_W - 2 * self::MARGIN) / (self::SIZE * 0.6));
        $this->lines[] = [str_repeat('-', $max), false];
    }

    /**
     * Render the document, returns the PDF as a binary string
     */
    public function render()
    {
        $per_page = (int) floor((self::PAGE_H - 2 * self::MARGIN) / self::LEAD);
        $lines    = $this->lines ?: [['', false]];
        $chunks   = array_chunk($lines, max(1, $per_page));

        $objects    = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>';

        $kids = [];
        $n    = 5;

        foreach ($chunks as $chunk) {
            $stream = "BT\n/F1 " . self::SIZE . " Tf\n" . self::LEAD . " TL\n"
                . self::MARGIN . ' ' . (self::PAGE_H - self::MARGIN) . " Td\n";

            $bold = false;
            foreach ($chunk as $line) {
                if ($line[1] !== $bold) {
                    $bold    = $line[1];
                    $stream .= '/F' . ($bold ? 2 : 1) . ' ' . self::SIZE . " Tf\n";
                }
                $stream .= '(' . $this->escape($line[0]) . ") Tj T*\n";
            }

            $stream .= 'ET';

            $objects[$n] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
            $objects[$n + 1] = '<< /Type /Page /Parent 2 0 R'
                . ' /MediaBox [0 0 ' . self::PAGE_W . ' ' . self::PAGE_H . ']'
                . ' /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >>'
                . ' /Contents ' . $n . ' 0 R >>';

            $kids[] = ($n + 1) . ' 0 R';
            $n += 2;
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';

        $info = $n;
        $objects[$info] = '<< /Title (' . $this->escape($this->to_cp1252($this->title)) . ')'
            . ' /Producer (Roundcube webdav_files plugin)'
            . ' /CreationDate (D:' . date('YmdHis') . ') >>';

        ksort($objects);

        $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $num => $content) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $content . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $max  = max(array_keys($objects));

        $pdf .= "xref\n0 " . ($max + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", isset($offsets[$i]) ? $offsets[$i] : 0);
        }

        $pdf .= "trailer\n<< /Size " . ($max + 1) . ' /Root 1 0 R /Info ' . $info . " 0 R >>\n"
            . "startxref\n" . $xref . "\n%%EOF";

        return $pdf;
    }

    // ------------------------------------------------------------------

    private function to_cp1252($text)
    {
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', (string) $text);

        if ($converted === false) {
            // strip everything outside printable ASCII as last resort
            $converted = preg_replace('/[^\x20-\x7E]/', '?', (string) $text);
        }

        return $converted;
    }

    private function escape($text)
    {
        return strtr((string) $text, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }
}
