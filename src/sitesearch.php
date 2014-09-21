<?php

namespace Webspot\SiteSearch;

class SiteSearch
{

    protected static $_instance;

    protected static $_instances = array();

    public static function _init()
    {
        \Config::load('sitesearch', true);
    }

    public static function factory($name = 'default')
    {
        if (static::instance($name)) {
            throw new \RuntimeException('Instance "' . $name . '" already exists.');
        }

        static::$_instances[$name] = new static($name);
        return static::$_instances[$name];
    }

    public static function instance($name = null)
    {
        if (is_null($name)) {
            if (empty(static::$_instance)) {
                static::$_instance = static::factory();
            }
            return static::$_instance;
        } elseif (array_key_exists($name, static::$_instances)) {
            return static::$_instances[$name];
        }

        return false;
    }

    /**
     * @var  string  Language
     */
    protected $language;

    /**
     * @var  array  specific areas with their weight (multiplier)
     */
    protected $weights = array();

    /**
     * @var  string  SQL Table name
     */
    protected $table_name;

    /**
     * @var  string  connection name
     */
    protected $connection;

    /**
     * @var  array  optional table to join on the result
     */
    protected $join = array();

    public function __construct($name = 'default')
    {
        $this->language = \Config::get('sitesearch.' . $name . '.language', null);
        $this->weights = \Config::get('sitesearch.' . $name . '.weights', array());
        $this->table_name = \Config::get('sitesearch.' . $name . '.table_name', 'search_table');
        $this->join = \Config::get(
            'sitesearch.' . $name . '.join',
            array(
                'join_table' => null,
                'join_column' => 'id',
                'select_columns' => '*'
            )
        );
    }

    /**
     * Page part cleanup & scoring functions
     *
     * @param   string  string to clean of html
     * @return  string
     */
    public function clean_string($string)
    {
        // Replace paragraph <p> and linebreak <br> tags with spaces
        // And add a space in front of the first word (for noise word removal, requires spaces on both side of the word)
        $string = str_ireplace(
            array(
                '<p>',
                '<br />',
                '<br/>',
                '<br>',
                '<ul>',
                '<ol>',
                '</li>',
                '<h1>',
                '<h2>',
                '<h3>'
            ),
            ' ',
            ' ' . $string
        );

        // Replace images with their alt or title text
        $string = preg_replace('/<img([^><]+)(title="([^><"]+)"|alt="([^><"]+)")([^><]*)[\/]?>/', ' $2 ', $string);
        $string = preg_replace('/(alt=|title=)/', '', $string);

        // Strip all tags
        $string = strip_tags($string);

        // Decode all HTML special characters...
        $string = htmlspecialchars_decode($string);

        // Make all-lowercase
        $string = strtolower($string);

        // Remove all dots, comma's, round brackets, and-percents
        $string = preg_replace('/([\.,\(\)\&]+)/', ' ', $string);
        // Replace all collons, semi-collons, other brackets, dashes, linebreaks and tabs with spaces
        $string = preg_replace('/([:;\{\}\[\]\/\\-' . "\n\t" . ']+)/', ' ', $string);

        // Final cleanup
        $string = \Inflector::ascii($string);
        $string = preg_replace('/([^a-z0-9% ]+)/', '', $string);

        // Return cleaned up string (and remove the prefixed space)
        return $string;
    }

    /**
     * Create scores per word in string
     *
     * @param   string  page part
     * @param   int     minimal word length
     * @return  array
     */
    public function create_scores($string, $min_strlen = 3)
    {
        // Explode the string into an array by spaces
        $array = explode(' ', $string);

        if ($min_strlen > 0) {
            foreach ($array as $k => $v) {
                if (strlen($v) < $min_strlen) {
                    unset($array[$k]);
                }
            }
        }

        // Remove noise words
        $array = $this->remove_noise($array);

        // Use stemmer on all words
        $array = $this->parse_stemmer($array);

        // Score by number of occurances of each word
        $array = array_count_values(array_filter($array), true);

        // Return the array of words and their scores
        return $array;
    }

    /**
     * Set the weight of a specific part of the page
     *
     * @param   string
     * @param   int
     * @return  SiteSearch
     */
    public function set_weight($key, $weight)
    {
        $this->weights[$key] = (int)$weight;

        return $this;
    }

    /**
     * Remove all currents weights
     *
     * @return  SiteSearch
     */
    public function reset_weights()
    {
        $this->weights = array();

        return $this;
    }

    /**
     * Create word scores for the whole page
     *
     * @param   array
     * @return  array
     */
    public function score_page(array $page)
    {
        // Create empty output variable;
        $array = array();

        // Go through different kinds of fields
        foreach ($page as $key => $val) {
            // Cleanup page
            $val = $this->clean_string($val);

            // Get words and their scores from the field
            $val = $this->create_scores($val);

            // Set score multiplier with weight when set (with $this->set_weight())
            $weight = array_key_exists($key, $this->weights) ? $this->weights[$key] : 1;

            // Assign words to output variable
            foreach ($val as $word => $score) {
                // Multiply score with weight
                $score = $score * $weight;

                // Add it to the total if the key exists, create and assign score when not
                $array[$word] = array_key_exists($word, $array) ? $array[$word] + $score : $score;
            }
        }

        // Return the result
        return $array;
    }

    /**
     * Remove noise words from array
     *
     * @param  array
     */
    public function remove_noise(array $array, $filter_keys = false)
    {
        $lang = $this->language ?: \Config::get('language');
        \Lang::load('sitesearch', 'sitesearch.' . $lang, $lang);
        $noise_words = (array)\Lang::line('sitesearch.' . $lang . '.noise_words', array());

        if ($filter_keys) {
            foreach ($noise_words as $noise) {
                unset($array[$noise]);
            }
        } else {
            $array = array_filter(
                $array,
                function ($val) use ($noise_words) {
                    return in_array($val, $noise_words);
                }
            );
        }

        return $array;
    }

    /**
     * Stem all the words
     *
     * @param $array
     * @return array
     */
    public function parse_stemmer($words)
    {
        $stemmer = 'SiteSearch_Stemmer_' . ucfirst($this->language);
        if (method_exists($stemmer, 'stem')) {
            $words = $stemmer::stem($words);
        }

        return $words;
    }

    /**
     * Index a page
     *
     * @param   array  page array with keys as parts for weight
     * @param   mixed  page identifier
     * @param   bool   removes page before indexing
     * @return  SiteSearch
     */
    public function index_page(array $page, $page_id, $with_removal = false)
    {
        $with_removal and $this->remove_page($page_id);
        $scored_page = $this->score_page((array)$page);

        foreach ($scored_page as $word => $score) {
            \DB::insert($this->table_name)->set(
                array(
                    'word' => $word,
                    'score' => $score,
                    'page_id' => $page_id
                )
            )->execute($this->connection);
        }

        return $this;
    }

    /**
     * Remove page from index
     *
     * @param   mixed  page identifier
     * @return  SiteSearch
     */
    public function remove_page($page_id)
    {
        \DB::delete($this->table_name)->where('page_id', $page_id)->execute($this->connection);

        return $this;
    }

    /**
     * Remove full index
     *
     * @return  SiteSearch
     */
    public function remove_full_index()
    {
        \DB::delete($this->table_name)->execute($this->connection);

        return $this;
    }

    /**
     * Set join table for result
     *
     * @param   string  table name to join
     * @param   string  column for joining
     * @param   array   columns to select
     * @return  SiteSearch
     */
    public function search_set_join($join_table, $join_column = null, $select_columns = null)
    {
        $this->join['join_table'] = $join_table;
        !empty($join_column) and $this->join['join_column'] = $join_column;
        !empty($select_columns) and $this->join['select_columns'] = $select_columns;

        return $this;
    }

    public function search($string, $and = true)
    {
        // First do cleanup
        $string = $this->clean_string($string);

        // Create array of keywords
        $keywords = explode(' ', $string);

        // remove noise words
        $keywords = $this->remove_noise($keywords);

        // Use stem parser
        $keywords = $this->parse_stemmer($keywords);

        // Remove duplicate keywords and empty values
        $keywords = array_filter(array_unique($keywords));

        // Create query
        $query = \DB::select(
            \DB::expr($this->table_name . '.*'),
            \DB::expr('COUNT(*) AS `nb`'),
            \DB::expr('SUM(`' . $this->table_name . '`.`score`) AS `weight`')
        )->from($this->table_name);

        if (!empty($this->join)) {
            $query->join($this->join['join_table'])->on($this->join['join_column']);
            $query->select_array($this->join['select_columns']);
        }

        $query->where('word', 'IN', $keywords);
        if ($and) {
            $query->having('nb', count($keywords));
        }
        $query->group_by('page_id')
            ->order_by('nb', 'desc')
            ->order_by('weight', 'desc');

        return $query->execute($this->connection)->as_array();
    }

    /**
     * Original example function
     *
     * @param $pagename
     * @return void
     */
    public function add_page($pagename)
    {
        $this
            ->set_weight('short_title', 3)
            ->set_weight('title', 2)
            ->set_weight('meta_keywords', 3)
            ->set_weight('meta_description', 2);

        $q = Db::find('structure')->where('name', $pagename)->related('structure_links')->getOne();

        $page = new \stdClass();
        $page->title = $q->title;
        $page->short_title = $q->short_title;
        $page->meta_keywords = $q->meta_keywords;
        $page->meta_description = $q->meta_description;
        $page->content = '';
        foreach ($q->structure_links as $sq) {
            if (!empty($this->{'mod_' . $sq->module}) || file_exists(APPPATH . 'models/mod_' . $sq->module . '.php')) {
                $output = Ci::mod($sq->module)->view($sq->id, $sq->module_id, $sq->placement, $sq->params);

                if (empty($output->noindex)) {
                    $page->content .= Ci::view('default/' . $sq->module, $output, true);
                }
            }
        }

        $this->index_page($page, $q->name);
    }
}
