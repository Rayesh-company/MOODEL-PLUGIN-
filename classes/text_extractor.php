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
 * Robust, zero-dependency text extractor for course resources (PDF, DOCX, PPTX, TXT, HTML).
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
        $mimetype = $file->get_mimetype();
        $filename = strtolower($file->get_filename());

        if (str_ends_with($filename, '.txt') || $mimetype === 'text/plain') {
            return self::clean_text($file->get_content(), $maxchars);
        }

        if (str_ends_with($filename, '.html') || str_ends_with($filename, '.htm') || $mimetype === 'text/html') {
            return self::clean_text(strip_tags($file->get_content()), $maxchars);
        }

        if (str_ends_with($filename, '.docx') || $mimetype === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return self::extract_from_docx($file, $maxchars);
        }

        if (str_ends_with($filename, '.pptx') || $mimetype === 'application/vnd.openxmlformats-officedocument.presentationml.presentation') {
            return self::extract_from_pptx($file, $maxchars);
        }

        if (str_ends_with($filename, '.pdf') || $mimetype === 'application/pdf') {
            return self::extract_from_pdf($file, $maxchars);
        }

        return self::clean_text($file->get_content(), $maxchars);
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
        if (!file_exists($filepath)) {
            return '';
        }
        if (empty($filename)) {
            $filename = basename($filepath);
        }
        $filename = strtolower($filename);

        if (str_ends_with($filename, '.txt') || str_ends_with($filename, '.md')) {
            return self::clean_text((string) file_get_contents($filepath), $maxchars);
        }

        if (str_ends_with($filename, '.html') || str_ends_with($filename, '.htm')) {
            return self::clean_text(strip_tags((string) file_get_contents($filepath)), $maxchars);
        }

        if (str_ends_with($filename, '.docx')) {
            $zip = new ZipArchive();
            $text = '';
            if ($zip->open($filepath) === true) {
                $xmlcontent = $zip->getFromName('word/document.xml');
                if ($xmlcontent) {
                    $xmlcontent = str_replace(['</w:p>', '</w:tr>'], "\n", $xmlcontent);
                    $text = strip_tags($xmlcontent);
                }
                $zip->close();
            }
            return self::clean_text($text, $maxchars);
        }

        if (str_ends_with($filename, '.pptx')) {
            $zip = new ZipArchive();
            $text = '';
            if ($zip->open($filepath) === true) {
                for ($i = 1; $i <= $zip->numFiles; $i++) {
                    $slide = $zip->getFromName("ppt/slides/slide{$i}.xml");
                    if ($slide) {
                        $slide = str_replace(['</a:p>', '</a:tr>'], "\n", $slide);
                        $text .= "\n" . strip_tags($slide);
                    }
                }
                $zip->close();
            }
            return self::clean_text($text, $maxchars);
        }

        if (str_ends_with($filename, '.pdf')) {
            return self::parse_pdf_content((string) file_get_contents($filepath), $maxchars);
        }

        return self::clean_text((string) file_get_contents($filepath), $maxchars);
    }

    /**
     * Extracts text from Office Open XML Word documents (.docx) via ZipArchive.
     */
    protected static function extract_from_docx(stored_file $file, int $maxchars): string {
        $tempfile = $file->copy_content_to_temp();
        $zip = new ZipArchive();
        $text = '';

        if ($zip->open($tempfile) === true) {
            $xmlcontent = $zip->getFromName('word/document.xml');
            if ($xmlcontent) {
                // Convert <w:p> to newlines.
                $xmlcontent = str_replace(['</w:p>', '</w:tr>'], "\n", $xmlcontent);
                $text = strip_tags($xmlcontent);
            }
            $zip->close();
        }
        @unlink($tempfile);

        return self::clean_text($text, $maxchars);
    }

    /**
     * Extracts text from PowerPoint slides (.pptx) via ZipArchive.
     */
    protected static function extract_from_pptx(stored_file $file, int $maxchars): string {
        $tempfile = $file->copy_content_to_temp();
        $zip = new ZipArchive();
        $text = '';

        if ($zip->open($tempfile) === true) {
            for ($i = 1; $i <= $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat && preg_match('#ppt/slides/slide\d+\.xml#', $stat['name'])) {
                    $xml = $zip->getFromIndex($i);
                    $xml = str_replace('</a:p>', "\n", $xml);
                    $text .= "\n" . strip_tags($xml);
                }
            }
            $zip->close();
        }
        @unlink($tempfile);

        return self::clean_text($text, $maxchars);
    }

    /**
     * Pure-PHP PDF stream text extractor for text-based PDF files.
     */
    protected static function extract_from_pdf(stored_file $file, int $maxchars): string {
        return self::parse_pdf_content($file->get_content(), $maxchars);
    }

    /**
     * High-performance, backtracking-free PDF stream text extractor.
     */
    protected static function parse_pdf_content(string $content, int $maxchars): string {
        $text = '';
        $offset = 0;
        $contentlen = strlen($content);

        // High-performance O(N) stream scanner via strpos to eliminate regex backtracking.
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

                // Non-backtracking character classes: [^()]* and [^\[\]]*
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
     * Normalizes and limits extracted text.
     */
    protected static function clean_text(string $text, int $maxchars): string {
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
