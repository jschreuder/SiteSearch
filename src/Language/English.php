<?php

namespace Webspot\SiteSearch\Language;

class English implements Driver
{
    /** @var  string[] */
    private $noiseWords = [
        'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves', 'you', 'your', 'yours', 'yourself', 'yourselves',
        'he', 'him', 'his', 'himself', 'she', 'her', 'hers', 'herself', 'it', 'its', 'itself', 'they', 'them', 'their',
        'theirs', 'themselves', 'what', 'which', 'who', 'whom', 'this', 'that', 'these', 'those', 'am', 'is', 'are',
        'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'having', 'do', 'does', 'did', 'doing', 'a', 'an',
        'the', 'and', 'but', 'if', 'or', 'because', 'as', 'until', 'while', 'of', 'at', 'by', 'for', 'with', 'about',
        'against', 'between', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'to', 'from', 'up',
        'down', 'in', 'out', 'on', 'off', 'over', 'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when',
        'where', 'why', 'how', 'all', 'any', 'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no',
        'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very',
    ];

    /** {@inheritdoc} */
    public function removeNoise(array $words)
    {
        foreach ($this->noiseWords as $noise) {
            unset($words[$noise]);
        }
        return $words;
    }

    /** {@inheritdoc} */
    public function removeAccents($text)
    {
        // no accents in English
        return $text;
    }

    /** {@inheritdoc} */
    public function stem(array $words)
    {
        $words = (array)$words;
        foreach ($words as $key => $word) {
            $words[$key] = \Porter::stem($word, true);
        }
        return $words;
    }
}
