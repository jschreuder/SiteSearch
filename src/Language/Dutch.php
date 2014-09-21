<?php

namespace Webspot\SiteSearch\Language;

class Dutch implements Driver
{
    /** @var  string[] */
    private $noiseWords = [
        'aan', 'alle', 'ben', 'bij', 'dan', 'dat', 'de', 'deze', 'die', 'dit', 'door', 'dus', 'een', 'elk', 'elke',
        'en', 'ga', 'gaan', 'gaat', 'geen', 'ging', 'gingen', 'had', 'hadden', 'heb', 'hebben', 'hebt', 'het', 'hij',
        'in', 'is', 'je', 'kan', 'kon', 'konden', 'kunnen', 'kunt', 'maar', 'mag', 'maken', 'meer', 'met', 'moet',
        'mocht', 'mochten', 'moeten', 'mogen', 'naar', 'niet', 'nog', 'of', 'ook', 'op', 'over', 'te', 'tot', 'u',
        'van', 'veel', 'voor', 'vooral', 'waren', 'was', 'wat', 'welke', 'werd', 'werden', 'wil', 'wilde', 'willen',
        'wilden', 'word', 'worden', 'wordt', 'zal', 'zijn', 'zoals', 'zou', 'zouden', 'zullen',
    ];

    /** @var  array */
    private $accentReplacements = [
        'ä' => 'a',
        'á' => 'a',
        'à' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ë' => 'e',
        'é' => 'e',
        'è' => 'e',
        'ê' => 'e',
        'ï' => 'i',
        'í' => 'i',
        'ì' => 'i',
        'î' => 'i',
        'ö' => 'o',
        'ó' => 'o',
        'ò' => 'o',
        'ô' => 'o',
        'ü' => 'u',
        'ú' => 'u',
        'ù' => 'u',
        'û' => 'u',
        'ç' => 'c',
        'ñ' => 'n',
    ];

    /** @var  bool */
    protected $useStep2 = false;

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
        return strtr($text, $this->accentReplacements);
    }

    /** {@inheritdoc} */
    public function stem(array $words)
    {
        foreach ($words as $key => $word) {
            $words[$key] = $this->stemWord($word, true);
        }
        return $words;
    }

    /**
     * @param   string $word
     * @return  string
     */
    private function stemWord($word)
    {
        $this->useStep2 = false;

        // Start with removing accented suffixes
        $word = $this->stemStep0($word);

        // Put initial y, y after a vowel, and i between vowels into upper case (treat as consonants).
        $word = preg_replace(
            array('/^y|(?<=[aeiouyè])y/u', '/(?<=[aeiouyè])i(?=[aeiouyè])/u'),
            array('Y', 'I'),
            $word
        );

        /* R1 is the region after the first non-vowel following a vowel, or is the
            null region at the end of the word if there is no such non-vowel. */
        $region1 = 0;
        if (preg_match('/[aeiouyè][^aeiouyè]/u', $word, $matches, PREG_OFFSET_CAPTURE)) {
            $region1 = $matches[0][1] + 2;
        }

        /* R2 is the region after the first non-vowel following a vowel in R1, or is
            the null region at the end of the word if there is no such non-vowel. */
        $region2 = 0;
        if (preg_match('/[aeiouyè][^aeiouyè]/u', $word, $matches, PREG_OFFSET_CAPTURE, $region1)) {
            $region2 = $matches[0][1] + 2;
        }

        // Steps 1-4: suffix removal
        $word = $this->stemStep1($word, $region1);
        $word = $this->stemStep2($word, $region1);
        $word = $this->stemStep3($word, $region1, $region2);
        $word = $this->stemStep4($word);

        // Return I en Y that were treated as consonants to lowercase
        $word = str_replace(array('Y', 'I'), array('y', 'i'), $word);

        return $word;
    }

    /**
     * Step 0: accented suffixes
     *
     * @param   string $word
     * @return  string
     */
    private function stemStep0($word)
    {
        return preg_replace('/eën$/u', 'e', preg_replace('/(ieel|iële|ieën)$/u', 'ie', $word));
    }

    /**
     * Step 1: Search for the longest among the following suffixes, and perform the action indicated
     *
     * @param   string $word
     * @param   int $region1
     * @return  string
     */
    private function stemStep1($word, $region1)
    {
        if ($region1) {
            if (preg_match('/heden$/u', $word, $matches, 0, $region1)) {
                // -heden
                return preg_replace('/heden$/u', 'heid', $word, -1, $count);
            } elseif (preg_match('/(?<=[^aeiouyè]|gem)ene?$/u', $word, $matches, 0, $region1)) {
                // -en(e)
                return static::unDouble(preg_replace('/ene?$/u', '', $word, -1, $count));
            } elseif (preg_match('/(?<=[^jaeiouyè])se?$/u', $word, $matches, 0, $region1)) {
                // -s(e)
                return rtrim(preg_replace('/se?$/u', '', $word, -1, $count), "'");
            } elseif (preg_match('/dt$/u', $word, $matches, 0, $region1)) {
                // -d(t)
                return preg_replace('/dt$/u', 'd', $word, -1, $count);
            } elseif (preg_match('/[^aeiouyè]ie$/', $word, $matches, 0, $region1)) {
                // -Ci(e)
                return preg_replace('/ie$/', 'i', $word, -1, $count);
            } elseif (preg_match('/[^aeiouyè]isch[e]?$/', $word, $matches, 0, $region1)) {
                // -Ci(sch(e))
                return preg_replace('/isch[e]?$/', 'i', $word, -1, $count);
            }
        }
        return $word;
    }

    /**
     * Step 2: Delete suffix e if in R1 and preceded by a non-vowel, and then undouble the ending
     *
     * @param   string $word
     * @param   int $region1
     * @return  string
     */
    private function stemStep2($word, $region1)
    {
        if ($region1) {
            if (preg_match('/(?<=[^aeiouyè])e$/u', $word, $matches, 0, $region1)) {
                $this->useStep2 = true;
                return static::unDouble(preg_replace('/e$/u', '', $word, -1));
            }
        }
        return $word;
    }

    /**
     * Step 3a: delete heid if in R2 and not preceded by c, and treat a preceding en as in step 1(b)
     * Step 3b: search for the longest among the following suffixes, and perform the action indicated.
     *
     * @param   string $word
     * @param   int $region1
     * @param   int $region2
     * @return  string
     */
    private function stemStep3($word, $region1, $region2)
    {
        if ($region2) {
            if (preg_match('/(?<!c)heid$/u', $word, $matches, 0, $region2)) {
                $word = preg_replace('/heid$/u', '', $word, -1, $count);
                if (preg_match('/en$/u', $word, $matches, 0, $region1)) {
                    $word = static::unDouble(preg_replace('/en$/u', '', $word, -1, $count));
                }
            }
        }

        if ($region2) {
            if (preg_match('/baar$/u', $word, $matches, 0, $region2)) {
                // -baar
                $word = preg_replace('/baar$/u', '', $word, -1, $count);
            } elseif (preg_match('/lijk$/u', $word, $matches, 0, $region2)) {
                // -lijk
                $word = static::stemStep2(preg_replace('/lijk$/u', '', $word, -1, $count), $region1, $region2);
            } elseif (preg_match('/(end|ing)$/u', $word, $matches, 0, $region2)) {
                // -end / -ing
                $word = preg_replace('/(end|ing)$/u', '', $word, -1, $count);
                // -ig
                if (preg_match('/(?<!e)ig$/u', $word, $matches, 0, $region2)) {
                    $word = preg_replace('/ig$/u', '', $word, -1, $count);
                }
            } elseif (preg_match('/(?<!e)ig$/u', $word, $matches, 0, $region2)) {
                // -ig
                $word = preg_replace('/ig$/u', '', $word, -1, $count);
            } elseif ($this->useStep2 && preg_match('/bar$/u', $word, $matches, 0, $region2)) {
                // -bar
                $word = preg_replace('/bar$/u', '', $word, -1, $count);
            }
        }

        return $word;
    }

    /**
     * Step 4: undouble vowel
     * If the words ends CVD, where C is a non-vowel, D is a non-vowel other than
     * I, and V is double a, e, o or u, remove one of the vowels from V
     * (for example, maan -> man, brood -> brod).
     *
     * @param   string $word
     * @return  string
     */
    private function stemStep4($word)
    {
        if (preg_match('/[^aeiouyè](aa|ee|oo|uu)[^Iaeiouyè]$/u', $word)) {
            $word = substr($word, 0, -2) . str_replace(array('s', 'f'), array('z', 'v'), substr($word, -1));
        }
        return $word;
    }

    /**
     * Un-double double consonants at word's end
     *
     * @param   string $word
     * @return  string
     */
    private function unDouble($word)
    {
        return preg_match('/(bb|dd|gg|kk|ll|mm|nn|pp|rr|ss|tt|zz)$/u', $word) ? substr($word, 0, -1) : $word;
    }
}
