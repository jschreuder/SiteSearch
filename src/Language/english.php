<?php

namespace Webspot\SiteSearch\Language;

class English implements Driver
{
    public static function stem($words)
    {
        $words = (array)$words;
        foreach ($words as $key => $word) {
            $words[$key] = \Porter::stem($word, true);
        }
        return $words;
    }
}
