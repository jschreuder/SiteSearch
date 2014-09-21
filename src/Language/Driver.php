<?php

namespace Webspot\SiteSearch\Language;

interface Driver
{
    /**
     * Stem the given words
     *
     * @param   string[]  $words
     * @return  string[]
     */
    public function stem(array $words);

    /**
     * Takes an array of words and removes the noise words
     *
     * @param   string[] $words
     * @return  string[]
     */
    public function removeNoise(array $words);

    /**
     * Takes a full text and replaces all accented characters with unaccented versions
     *
     * @param   string $text
     * @return  string
     */
    public function removeAccents($text);
}
