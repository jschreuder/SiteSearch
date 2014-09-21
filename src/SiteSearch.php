<?php

namespace Webspot\SiteSearch;

class SiteSearch
{
    const SEARCH_MATCH_ALL = 'all';
    const SEARCH_MATCH_ANY = 'any';

    /** @var  Language\Driver */
    protected $language;

    /** @var  array  specific areas with their weight (multiplier) */
    protected $weights = [];

    /** @var  \PDO */
    protected $connection;

    /** @var  string  SQL Table name */
    protected $tableWriteName;

    /** @var  string  SQL Table name */
    protected $tableReadName;

    /**
     * Must be instantiated with a language driver, a PDO connection and the table name
     * The table name is either just a name or a set ($tableWriteName, $tableReadName) where the first
     * is used for inserts/deletes and the second for executing the search.
     *
     * @param   Language\Driver $language
     * @param   \PDO $connection
     * @param   string|string[] $tableName
     */
    public function __construct(
        Language\Driver $language,
        \PDO $connection,
        $tableName = 'sitesearch'
    ) {
        $this->language = $language;
        $this->connection = $connection;

        if (is_array($tableName)) {
            list($this->tableWriteName, $this->tableReadName) = $tableName;
        } else {
            $this->tableWriteName = $this->tableReadName = $tableName;
        }
    }

    /** @return  Language\Driver */
    private function getLanguage()
    {
        return $this->language;
    }

    /** @return  \PDO */
    private function getConnection()
    {
        return $this->connection;
    }

    /**
     * Page part cleanup & scoring functions
     *
     * @param   string  string to clean of html
     * @return  string
     */
    public function cleanString($string)
    {
        // Replace block tags <p> and line breaks with spaces
        $string = str_ireplace(
            ['<p>', '<br />', '<br/>', '<br>', '<ul>', '<ol>', '</li>', '<h1>', '<h2>', '<h3>'],
            ' ',
            $string
        );

        // Replace images with their alt or title text
        $string = preg_replace('/<img([^><]+)(title="([^><"]+)"|alt="([^><"]+)")([^><]*)[\/]?>/', ' $2 ', $string);

        // Decode all HTML special characters...
        $string = htmlspecialchars_decode($string);

        // Make all-lowercase
        $string = strtolower($string);

        // Remove accents
        $string = $this->getLanguage()->removeAccents($string);

        // Remove all dots, comma's, round brackets, and-percents
        $string = preg_replace('/([\.,\(\)\&]+)/', ' ', $string);
        // Replace all collons, semi-collons, other brackets, dashes, linebreaks and tabs with spaces
        $string = preg_replace('/([:;\{\}\[\]\/\\-' . "\n\t" . ']+)/', ' ', $string);

        // Strip all tags
        $string = filter_var(
            $string,
            FILTER_SANITIZE_STRING,
            FILTER_FLAG_NO_ENCODE_QUOTES | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
        );
        $string = preg_replace('/([^a-z0-9% ]+)/', '', $string);

        // Return cleaned up string (and remove the prefixed space)
        return $string;
    }

    /**
     * Create scores per word in string
     *
     * @param   string $string page part
     * @param   int $minLength minimal word length
     * @return  array
     */
    public function createScores($string, $minLength = 3)
    {
        // Explode the string into an array by spaces
        $words = explode(' ', $string);

        if ($minLength > 0) {
            foreach ($words as $k => $v) {
                if (strlen($v) < $minLength) {
                    unset($words[$k]);
                }
            }
        }

        // Remove noise words
        $words = $this->getLanguage()->removeNoise($words);

        // Use stemmer on all words
        $words = $this->getLanguage()->stem($words);

        // Score by number of occurances of each word
        $scores = array_count_values(array_filter($words), true);

        // Return the array of words and their scores
        return $scores;
    }

    /**
     * Set the weight of a specific part of the page
     *
     * @param   string $key
     * @param   int $weight
     * @return  SiteSearch
     */
    public function setWeight($key, $weight)
    {
        $this->weights[$key] = intval($weight);
        return $this;
    }

    /**
     * Gets the weight for the given key, defaults to 1 when not set
     *
     * @param   string $key
     * @param   int $default
     * @return  int
     */
    public function getWeight($key, $default = 1)
    {
        return array_key_exists($key, $this->weights) ? $this->weights[$key] : $default;
    }

    /**
     * Create word scores for the whole page
     *
     * @param   array $page
     * @return  array
     */
    public function scorePage(array $page)
    {
        // Create empty output variable;
        $scores = array();

        // Go through different kinds of fields
        foreach ($page as $key => $val) {
            $val = $this->cleanString($val);
            $val = $this->createScores($val);

            // Assign words to output variable
            foreach ($val as $word => $score) {
                // Multiply score with weight
                $score = $score * $this->getWeight($key);

                // Add it to the total if the key exists, create and assign score when not
                $scores[$word] = array_key_exists($word, $scores) ? $scores[$word] + $score : $score;
            }
        }

        // Return the result
        return $scores;
    }

    /**
     * Index a page
     *
     * @param   array  $page array with keys as parts for weight
     * @param   mixed  $pageId identifier, must be possible to cast to string
     * @return  SiteSearch
     */
    public function indexPage(array $page, $pageId)
    {
        $scoredPage = $this->scorePage((array)$page);

        foreach ($scoredPage as $word => $score) {
            $query = $this->getConnection()->prepare(
                "INSERT INTO {$this->tableWriteName} (word, score, page_id) VALUES (:word, :score, :page)"
            );
            $query->execute([
                ':word' => $word,
                ':score' => $score,
                ':page' => strval($pageId),
            ]);
        }

        return $this;
    }

    /**
     * Remove page from index
     *
     * @param   mixed  $pageId
     * @return  SiteSearch
     */
    public function removePage($pageId)
    {
        $query = $this->getConnection()->prepare("DELETE FROM {$this->tableWriteName} WHERE page_id = :page");
        $query->execute([':page' => $pageId]);
        return $this;
    }

    /**
     * Remove full index
     *
     * @return  SiteSearch
     */
    public function removeFullIndex()
    {
        $query = $this->getConnection()->prepare("DELETE FROM {$this->tableWriteName} WHERE 1 = 1");
        $query->execute();
        return $this;
    }

    /**
     * @param   string $search
     * @param   string $type
     * @return  array
     */
    public function search($search, $type = self::SEARCH_MATCH_ALL)
    {
        $search = $this->cleanString($search);
        $keywords = explode(' ', $search);
        $keywords = $this->getLanguage()->removeNoise($keywords);
        $keywords = $this->getLanguage()->stem($keywords);
        $keywords = array_filter(array_unique($keywords));
        if (!$keywords) {
            return [];
        }

        // Create query
        $query  = "SELECT *, COUNT(*) AS nb, SUM(score) AS weight FROM {$this->tableReadName}";
        $query .= ' WHERE word IN ('.implode(',', array_fill(0, count($keywords), '?')).')';
        $query .= ' GROUP BY page_id';
        if ($type !== self::SEARCH_MATCH_ANY) {
            $query .= ' HAVING nb = '.strval(count($keywords));
        }
        $query .= 'ORDER BY nb DESC, weight DESC';

        $query = $this->getConnection()->prepare($query);
        $query->execute($keywords);

        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }
}
