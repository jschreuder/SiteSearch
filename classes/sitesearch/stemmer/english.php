<?php

namespace SiteSearch;

class SiteSearch_Stemmer_English implements SiteSearch_Stemmer_Driver {

	public static function _init()
	{
		require_once PKGPATH.'sitesearch'.DS.'vendor'.DS.'porterstemmer.php';
	}

	public static function stem($words)
	{
		$words = (array) $words;
		foreach ($words as $key => $word)
		{
			$words[$key] = \PorterStemmer::stem($word, true);
		}
		return $words;
	}
}

// end of file english.php