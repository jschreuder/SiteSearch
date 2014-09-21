<?php

namespace Webspot\SiteSearch\Language;

interface Driver
{

    /**
     * Stem the given word
     *
     * @param   string  word
     * @return  string  stem of the word
     */
    public static function stem($word);
}
