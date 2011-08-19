<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class MP_Search
{
	public $ci;
	protected $__lang;
	protected $__weight;
	protected $__table;
	protected $__join;
	
	// Initialization functions
	// ------------------------------------------------------------------
	
	public function __construct($lang = NULL)
	{
		// Instanciate ci, db, config and weight variables
		$this->__weight = array();
		$this->__join = array('join_table' => NULL, 'join_column' => 'id', 'select_columns' => '*');
		
		// Load language files for stemmer and noise words when available
		if (empty($lang))
			$lang = Ci::config('language');
		$this->load_language($lang);
		
		// Set database table for search words
		$this->__table == Ci::config('mp_search_table', 'mp_search_'.$lang);
		if ($this->__table === FALSE)
			$this->__table = 'search_words';
	}
	
	// Loads a corresponding language file
	public function load_language($lang)
	{
		// Check whether language specific settings exist and load
		if (file_exists(APPPATH.'config/mp_search_'.$lang.'.php'))
		{
			Ci::lib('load')->config('mp_search_'.$lang, TRUE);
			$this->__lang = $lang;
			// Also set database table if language specific has been set
			if (Ci::config('mp_search_table', 'mp_search_'.$lang) !== FALSE)
				$this->__table = Ci::config('mp_search_table', 'mp_search_'.$lang);
			return TRUE;
		}
		else
			return FALSE;
	}
	
	// Keyword cleanup & scoring functions
	// ------------------------------------------------------------------
	
	public function clean_string($string)
	{
		// Replace paragraph <p> and linebreak <br> tags with spaces
		// And add a space in front of the first word (for noise word removal, requires spaces on both side of the word)
		$string = str_ireplace(
			array('<p>', '<br />', '<br/>', '<br>', '<ul>', '<ol>', '</li>', '<h1>', '<h2>', '<h3>'),
			array(' ',   ' ',      ' ',     ' ',    ' ',    ' ',    ' ',     ' ',    ' ',    ' '),
			' '.$string);
		
		// Replace images with their alt or title text
		$string = preg_replace('/<img([^><]+)(title="([^><"]+)"|alt="([^><"]+)")([^><]*)[\/]?>/', '$2 ', $string);
		$string = preg_replace('/(alt=|title=)/', '', $string);
		
		// Strip all tags
		$string = strip_tags($string);
		
		// Decode all HTML special characters...
		$string = htmlspecialchars_decode($string);
		// ... and remove all entities left
		$string = preg_replace('/&([a-zA-Z0-9]+);/', '', $string);
		
		// Make all-lowercase
		$string = strtolower($string);
		
		// Remove all dots, comma's, round brackets, and-percents
		$string = preg_replace('/([\.,\(\)\&]+)/', ' ', $string);
		// Replace all collons, semi-collons, other brackets, dashes, linebreaks and tabs with spaces
		$string = preg_replace('/([:;\{\}\[\]\/\\-'."\n\t".']+)/', ' ', $string);
		
		// Final cleanup
		$string = preg_replace('/([^a-z0-9äáàâãëéèêïíìîöóòôüúùûçñ ]+)/', '', $string);
		
		// Remove noise words
		$string = $this->remove_noise($string);
		
		// Return cleaned up string (and remove the prefixed string)
		return substr($string, 1);
	}
	
	public function create_scores($string, $min_strlen = 3)
	{
		// Explode the string into an array by spaces
		$array = explode(' ', $string);
		
		if ($min_strlen > 0)
		{
			foreach($array as $k => $v)
			{
				if (strlen($v) < $min_strlen)
					unset($array[$k]);
			}
		}
		
		// Use stemmer on all words
		$array = $this->parse_stemmer($array);
		
		// Score by number of occurances of each word
		$array = array_count_values($array);
		unset($array['']);
		
		// Return the array of words and their scores
		return $array;
	}
	
	// Use set_weight() to give certain page attributes more weight
	public function set_weight($key, $weight)
	{
		$this->__weight[$key] = $weight;
		
		return $this;
	}
	
	// Use reset_weight() to reset everything from set_weight()
	public function reset_weight()
	{
		$this->__weight = array();
		
		return $this;
	}
	
	public function score_page($page)
	{
		// Create empty output variable;
		$array = array();
		
		// Go through different kinds of fields
		foreach ($page as $key => $val)
		{
			// Cleanup page
			$val = $this->clean_string($val);
			
			// Get words and their scores from the field
			$val = $this->create_scores($val);
			
			// Set score multiplier with weight when set (with $this->set_weight())
			if ( ! empty($this->__weight[$key]))
				$weight = $this->__weight[$key];
			else
				$weight = 1;
			
			// Assign words to output variable
			foreach($val as $word => $score)
			{
				// Multiply score with weight
				$score = $score * $weight;
				
				// Add it to the total if the key exists, create and assign score when not
				if (array_key_exists($word, $array))
					$array[$word] += $score;
				else
					$array[$word] = $score;
			}
		}
		
		// Return the result
		return $array;
	}
	
	// Language specific functions for stemming and noise words removal
	// ------------------------------------------------------------------
	
	public function remove_noise($string)
	{
		$noise_words = Ci::config('mp_search_noise_words_'.$this->__lang, 'mp_search_'.$this->__lang);
		
		if (is_array($noise_words))
		{
			foreach($noise_words as $noise)
				$string = str_replace(' '.$noise.' ', ' ', $string);
		}
		
		return $string;
	}
	
	public function parse_stemmer($array)
	{
		$parse_stemmer = 'MP_Search_parse_stemmer_'.$this->__lang;
		if (function_exists($parse_stemmer))
		{
			foreach($array as $k => $v)
			{
				$array[$k] = $parse_stemmer($v);
			}
		}
		
		return $array;
	}
	
	// Database functions
	// ------------------------------------------------------------------
	
	public function index_page($page, $page_id, $with_removal = FALSE)
	{
		if ($with_removal === TRUE)
			$this->remove_page($page_id);
		
		$scored_page = $this->score_page($page);
		
		$q = Db::getConnection()->insert($this->__table);
		foreach ($scored_page as $word => $score)
		{
			$insert = array(
				'word' => $word,
				'score' => $score,
				'page_id' => $page_id
			);
			$q->add($insert);
		}
		$q->execute();
		
		return $this;
	}
	
	public function remove_page($page_id)
	{
		Db::getConnection()->delete($this->__table, array('page_id'=>$page_id));
		
		return $this;
	}
	
	public function remove_full_index()
	{
		Db::getConnection()->query('DELETE FROM `'.Db::getConnection()->dbprefix.$this->__table.'`');
		
		return $this;
	}
	
	public function search_set_join($join_table, $join_column = NULL, $select_columns = NULL)
	{
		$this->__join['join_table'] = $join_table;
		
		if ( ! empty($join_column))
			$this->__join['join_column'] = $join_column;
		
		if ( ! empty($select_columns))
			$this->__join['select_columns'] = $select_columns;
		
		return $this;
	}
	
	public function search($string, $and = TRUE)
	{
		// First do cleanup
		$string = $this->clean_string($string);
		
		// Create array of keywords
		$keywords = explode(' ', $string);
		
		// Use stem parser
		$keywords = $this->parse_stemmer($keywords);
		
		// Remove duplicate keywords
		$keywords = array_filter(array_unique($keywords));
		
		// Create query
		$q = Db::getConnection()->select()->escape(FALSE);
		$q->from(array($this->__table=>$this->__table), array());
		$q->column('`'.$this->__table.'`.`page_id` AS `page_id`')
			->column('COUNT(*) AS `nb`')
			->column('SUM(`'.$this->__table.'`.`score`) AS `weight`');
		$q->escape(TRUE);
		if ( ! empty($this->__join['join_table']))
		{
			$q->join(array($this->__join['join_table']=>$this->__join['join_table']),
				$this->__join['join_table'].'.'.$this->__join['join_column'].' = '.$this->__table.'.page_id',
				$this->__join['select_columns'],
				'');
		}
		$q->whereIn('word', $keywords);
		if ($and === TRUE)
			$q->having('nb', count($keywords));
		$q->groupBy('page_id');
		$q->orderBy('nb', 'desc');
		$q->orderBy('weight', 'desc');
		
		return $q->get();
	}
	
	// MijnPraktijk Functies
	// ------------------------------------------------------------------
	
	public function add_page($pagename)
	{
		$this
			->set_weight('short_title', 3)
			->set_weight('title', 2)
			->set_weight('meta_keywords', 3)
			->set_weight('meta_description', 2);
		
		$q = Db::find('structure')->where('name', $pagename)->related('structure_links')->getOne();
		
		$page->title			= $q->title;
		$page->short_title		= $q->short_title;
		$page->meta_keywords	= $q->meta_keywords;
		$page->meta_description	= $q->meta_description;
		$page->content			= '';
		foreach($q->structure_links as $sq)
		{
			if ( ! empty($this->{'mod_'.$sq->module}) || file_exists(APPPATH.'models/mod_'.$sq->module.'.php'))
			{
				$output = Ci::mod($sq->module)->view($sq->id, $sq->module_id, $sq->placement, $sq->params);
				
				if (empty($output->noindex))
					$page->content .= Ci::view('default/'.$sq->module, $output, TRUE);
			}
		}
		
		$this->index_page($page, $q->name);
	}
}