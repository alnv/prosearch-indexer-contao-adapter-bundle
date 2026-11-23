<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

class Text
{

    public static function tokenize($strString): string
    {

        $strString = str_replace(["\r", "\n"], " ", $strString);
        $strString = preg_replace('/\s+/', ' ', $strString);

        return trim($strString);
    }

    /**
     * Makes a string valid UTF-8 without losing characters.
     *
     * Text extracted from PDFs (smalot/pdfparser) can mix valid UTF-8 with
     * single Windows-1252 bytes, e.g. 0xFC for "ü" or 0xA9 for "©", when a
     * font has no ToUnicode map. Such a string breaks json_encode() and the
     * document is not indexed. Valid UTF-8 sequences are kept as they are,
     * every stray byte is converted from Windows-1252.
     */
    public static function toValidUtf8($strString): string
    {

        $strString = (string)$strString;

        if (\mb_check_encoding($strString, 'UTF-8')) {
            return $strString;
        }

        $strFixed = \preg_replace_callback(
            '/[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}|([\x80-\xFF])/',
            function ($arrMatch) {
                return isset($arrMatch[1]) ? \mb_convert_encoding($arrMatch[1], 'UTF-8', 'Windows-1252') : $arrMatch[0];
            },
            $strString
        );

        return $strFixed ?? \mb_scrub($strString, 'UTF-8');
    }
}