<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_aiquizgen;

use stored_file;
use ZipArchive;

defined('MOODLE_INTERNAL') || die();

/**
 * Universal multi-tier text extractor for university course resources.
 * Supports PDF (text-based, vector-curves, and scanned OCR), DOCX, PPTX, ODT, ODP, RTF, TXT, MD, CSV, TSV, HTML, and legacy Office formats.
 */
class text_extractor {

    /**
     * Extracts readable text content from a stored Moodle file.
     *
     * @param stored_file $file
     * @param int $maxchars Maximum characters to extract.
     * @return string Extracted text.
     */
    public static function extract_text(stored_file $file, int $maxchars = 8000): string {
        $tempfile = $file->copy_content_to_temp();
        if (!$tempfile || !file_exists($tempfile)) {
            return '';
        }
        try {
            return self::extract_from_path($tempfile, $file->get_filename(), $maxchars);
        } finally {
            @unlink($tempfile);
        }
    }

    /**
     * Extracts readable text content directly from a local filesystem path.
     *
     * @param string $filepath
     * @param string $filename
     * @param int $maxchars
     * @return string
     */
    public static function extract_from_path(string $filepath, string $filename = '', int $maxchars = 8000): string {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            return '';
        }
        if (empty($filename)) {
            $filename = basename($filepath);
        }
        $filename = strtolower($filename);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        switch ($ext) {
            case 'pdf':
                return self::extract_from_pdf($filepath, $maxchars);

            case 'docx':
            case 'docm':
            case 'dotx':
                return self::extract_from_docx($filepath, $maxchars);

            case 'pptx':
            case 'pptm':
            case 'potx':
                return self::extract_from_pptx($filepath, $maxchars);

            case 'odt':
            case 'ott':
            case 'odp':
            case 'otp':
                return self::extract_from_opendocument($filepath, $maxchars);

            case 'rtf':
                return self::extract_from_rtf($filepath, $maxchars);

            case 'doc':
            case 'ppt':
                return self::extract_from_legacy_office($filepath, $maxchars);

            case 'html':
            case 'htm':
            case 'xhtml':
                return self::extract_from_html($filepath, $maxchars);

            case 'txt':
            case 'md':
            case 'markdown':
            case 'csv':
            case 'tsv':
            case 'json':
            case 'xml':
            default:
                return self::extract_from_plain($filepath, $maxchars);
        }
    }

    /**
     * Checks if a CLI command is available in the current environment.
     *
     * @param string $cmd
     * @return bool
     */
    protected static function is_cmd_available(string $cmd): bool {
        if (stripos(PHP_OS, 'WIN') === 0) {
            @exec("where " . escapeshellarg($cmd) . " 2>NUL", $out, $ret);
            return $ret === 0;
        }
        @exec("which " . escapeshellarg($cmd) . " 2>/dev/null", $out, $ret);
        return $ret === 0;
    }

    /**
     * Multi-tier PDF extraction:
     * Tier 1: pdftotext (CLI)
     * Tier 2: Pure-PHP stream parser
     * Tier 3: OCR fallback via pdftoppm + tesseract (for scanned or vectorized slides)
     *
     * @param string $filepath
     * @param int $maxchars
     * @return string
     */
    protected static function extract_from_pdf(string $filepath, int $maxchars): string {
        // Tier 1: CLI pdftotext
        if (self::is_cmd_available('pdftotext')) {
            $cmd = 'pdftotext -layout ' . escapeshellarg($filepath) . ' - 2>/dev/null';
            $output = shell_exec($cmd);
            if ($output !== null) {
                $cleaned = self::clean_text($output, $maxchars);
                if (mb_strlen($cleaned) >= 50) {
                    return $cleaned;
                }
            }
        }

        // Tier 2: Pure-PHP PDF stream parser
        $content = @file_get_contents($filepath);
        if ($content) {
            $parsed = self::parse_pdf_content($content, $maxchars);
            if (mb_strlen($parsed) >= 50) {
                return $parsed;
            }
        }

        // Tier 3: OCR Fallback for scanned or vector-path PDFs (e.g. Persian university slides)
        if (self::is_cmd_available('pdftoppm') && self::is_cmd_available('tesseract')) {
            $ocrtext = self::extract_pdf_ocr($filepath, $maxchars);
            if (mb_strlen($ocrtext) >= 20) {
                return $ocrtext;
            }
        }

        return '';
    }

    /**
     * OCR fallback for scanned or vectorized slide PDFs.
     *
     * @param string $filepath
     * @param int $maxchars
     * @return string
     */
    protected static function extract_pdf_ocr(string $filepath, int $maxchars): string {
        $tempdir = make_temp_directory('aiquizgen_ocr_' . uniqid());
        $prefix = $tempdir . '/slide';

        // Render first 8 pages at 150 DPI for rapid OCR.
        $ppmcmd = 'pdftoppm -f 1 -l 8 -png -r 150 ' . escapeshellarg($filepath) . ' ' . escapeshellarg($prefix) . ' 2>/dev/null';
        @exec($ppmcmd);

        $images = glob($prefix . '-*.png');
        if (!$images) {
            @rmdir($tempdir);
            return '';
        }
        sort($images);

        $ocrtext = '';
        foreach ($images as $img) {
            $tesscmd = 'tesseract ' . escapeshellarg($img) . ' stdout -l fas+eng --psm 6 2>/dev/null';
            $output = shell_exec($tesscmd);
            if ($output) {
                $ocrtext .= "\n" . trim($output);
            }
            @unlink($img);
            if (mb_strlen($ocrtext) >= $maxchars) {
                break;
            }
        }

        foreach (glob($tempdir . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($tempdir);

        return self::clean_text($ocrtext, $maxchars);
    }

    /**
     * High-performance, backtracking-free PDF stream text extractor.
     *
     * @param string $content
     * @param int $maxchars
     * @return string
     */
    protected static function parse_pdf_content(string $content, int $maxchars): string {
        $text = '';
        $offset = 0;
        $contentlen = strlen($content);

        while ($offset < $contentlen && ($pos = strpos($content, 'stream', $offset)) !== false) {
            $streamstart = $pos + 6;
            if ($streamstart < $contentlen && $content[$streamstart] === "\r" && isset($content[$streamstart + 1]) && $content[$streamstart + 1] === "\n") {
                $streamstart += 2;
            } else if ($streamstart < $contentlen && ($content[$streamstart] === "\n" || $content[$streamstart] === "\r")) {
                $streamstart += 1;
            }

            $endpos = strpos($content, 'endstream', $streamstart);
            if ($endpos === false) {
                break;
            }

            $streamlen = $endpos - $streamstart;
            if ($streamlen > 0 && $streamlen < 5000000) {
                $streamdata = substr($content, $streamstart, $streamlen);
                $decompressed = @gzuncompress($streamdata);
                $target = $decompressed ?: $streamdata;

                if (preg_match_all('/\(([^()]*)\)\s*Tj/', $target, $sm)) {
                    $text .= ' ' . implode(' ', $sm[1]);
                } else if (preg_match_all('/\[([^\[\]]*)\]\s*TJ/', $target, $sm)) {
                    foreach ($sm[1] as $block) {
                        if (preg_match_all('/\(([^()]*)\)/', $block, $subm)) {
                            $text .= ' ' . implode('', $subm[1]);
                        }
                    }
                }
            }

            $offset = $endpos + 9;
            if (mb_strlen($text) >= $maxchars) {
                break;
            }
        }
        return self::clean_text($text, $maxchars);
    }

    /**
     * Extracts text from Office Open XML Word documents (.docx) via ZipArchive.
     *
     * @param string $filepath
     * @param int $maxchars
     * @return string
     */
    protected static function extract_from_docx(string $filepath, int $maxchars): string {
        $zip = new ZipArchive();
        $text = '';
        if ($zip->open($filepath) === true) {
            $xmlcontent = $zip->getFromName('word/document.xml');
            if ($xmlcontent) {
                $xmlcontent = str_replace(['</w:p>', '</w:tr>', '<w:tab/>', '<w:br/>'], ["\n", "\n", "\t", "\n"], $xmlcontent);
                $text .= strip_tags($xmlcontent);
            }
            for ($i = 1; $i <= 3; $i++) {
                $header = $zip->getFromName("word/header{$i}.xml");
                if ($header) {
                    $header = str_replace(['</w:p>', '</w:tr>'], "\n", $header);
                    $text .= "\n" . strip_tags($header);
                }
            }
            $zip->close();
        }
        return self::clean_text($text, $maxchars);
    }

    /**
     * Extracts text from PowerPoint slides (.pptx) via ZipArchive.
     *
     * @param string $filepath
     * @param int $maxchars
     * @return string
     */
    protected static function extract_from_pptx(string $filepath, int $maxchars): string {
        $zip = new ZipArchive();
        $text = '';
        if ($zip->open($filepath) === true) {
            for ($i = 1; $i <= $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat && preg_match('#ppt/(slides/slide\d+|notesSlides/notesSlide\d+)\.xml#', $stat['name'])) {
                    $xml = $zip->getFromIndex($i);
                    $xml = str_replace(['</a:p>', '</a:tr>', '<a:br/>'], "\n", $xml);
                    $text .= "\n" . strip_tags($xml);
                }
                if (mb_strlen($text) >= $maxchars) {
                    break;
                }
            }
            $zip->close();
        }
        return self::clean_text($text, $maxchars);
    }

    /**
     * Extracts text from OpenDocument files (.odt, .odp) via ZipArchive.
     *
     * @param string $filepath
     * @param int $maxchars
     * @return string
     */
    protected static function extract_from_opendocument(string $filepath, int $maxchars): string {
        $zip = new ZipArchive();
        $text = '';
        if ($zip->open($filepath) === true) {
            $xml = $zip->getFromName('content.xml');
            if ($xml) {
                $xml = str_replace(
                    ['</text:p>', '</text:h>', '</table:table-row>', '<text:line-break/>', '<text:tab/>'],
                    ["\n", "\n", "\n", "\n", "\t"],
                    $xml
                );
                $text = strip_tags($xml);
            }
            $zip->close();
        }
        return self::clean_text($text, $maxchars);
    }

    /**
     * Extracts text from Rich Text Format (.rtf).
     *
     * @param string $filepath
     * @param int $maxchars
     * @return string
     */
    protected static function extract_from_rtf(string $filepath, int $maxchars): string {
        $content = @file_get_contents($filepath);
        if (!$content) {
            return '';
        }
        $content = preg_replace('/\\\\(par|line)\b/i', "\n", $content);
        $content = preg_replace('/\\\\tab\b/i', "\t", $content);
        $content = preg_replace_callback('/\\\\\'([0-9a-fA-F]{2})/', function($m) {
            return chr(hexdec($m[1]));
        }, $content);
        $content = preg_replace_callback('/\\\\u([0-9]+)\??/', function($m) {
            return mb_chr((int)$m[1], 'UTF-8');
        }, $content);
        $content = preg_replace('/\\\\[a-z0-9\-]+ ?/i', '', $content);
        $content = str_replace(['{', '}'], '', $content);
        return self::clean_text($content, $maxchars);
    }

    /**
     * Fallback extractor for legacy binary office files (.doc, .ppt).
     *
     * @param string $filepath
     * @param int $maxchars
     * @return string
     */
    protected static function extract_from_legacy_office(string $filepath, int $maxchars): string {
        $content = @file_get_contents($filepath);
        if (!$content) {
            return '';
        }
        preg_match_all('/[\x20-\x7E\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]{4,}/u', $content, $matches);
        $text = implode(' ', $matches[0] ?? []);
        return self::clean_text($text, $maxchars);
    }

    /**
     * Extracts text from HTML documents.
     *
     * @param string $filepath
     * @param int $maxchars
     * @return string
     */
    protected static function extract_from_html(string $filepath, int $maxchars): string {
        $content = @file_get_contents($filepath);
        if ($content === false) {
            return '';
        }
        $content = preg_replace('/<\/(p|div|h[1-6]|li|tr|blockquote)>/i', "\n", $content);
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
        return self::clean_text(strip_tags($content), $maxchars);
    }

    /**
     * Extracts text from plain text formats (.txt, .md, .csv, .tsv, etc.).
     *
     * @param string $filepath
     * @param int $maxchars
     * @return string
     */
    protected static function extract_from_plain(string $filepath, int $maxchars): string {
        $content = @file_get_contents($filepath);
        if ($content === false) {
            return '';
        }
        return self::clean_text($content, $maxchars);
    }

    /**
     * Normalizes and limits extracted text.
     *
     * @param string $text
     * @param int $maxchars
     * @return string
     */
    public static function clean_text(string $text, int $maxchars): string {
        $text = preg_replace('/[^\P{C}\n\r\t]+/u', ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/(\r?\n){3,}/', "\n\n", $text);
        $text = trim($text);

        if (mb_strlen($text) > $maxchars) {
            $text = mb_substr($text, 0, $maxchars) . "\n...[truncated]";
        }

        return $text;
    }
}
