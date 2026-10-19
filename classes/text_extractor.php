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

        // Default fallback: strip non-printable characters.
        return self::clean_text($file->get_content(), $maxchars);
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
        $content = $file->get_content();
        $text = '';

        // Match all decompressed text blocks (BT ... ET) or streams.
        if (preg_match_all('/\((.*?)\)\s*Tj/s', $content, $matches)) {
            $text = implode(' ', $matches[1]);
        } else if (preg_match_all('/\[(.*?)\]\s*TJ/s', $content, $matches)) {
            foreach ($matches[1] as $block) {
                if (preg_match_all('/\((.*?)\)/s', $block, $submatches)) {
                    $text .= ' ' . implode('', $submatches[1]);
                }
            }
        }

        if (empty(trim($text))) {
            // Decompress FlateDecode streams if available in PHP.
            if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $content, $streams)) {
                foreach ($streams[1] as $stream) {
                    $decompressed = @gzuncompress($stream);
                    if ($decompressed) {
                        if (preg_match_all('/\((.*?)\)\s*Tj/s', $decompressed, $sm)) {
                            $text .= ' ' . implode(' ', $sm[1]);
                        }
                    }
                }
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
