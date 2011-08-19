<?php

namespace SiteSearch;

class SiteSearch_Stemmer_English implements SiteSearch_Stemmer_Driver {
	
	public static function _init()
	{
		require_once PKGPATH.'sitesearch'.DS.'vendor'.DS.'porterstemmer.php';
	}

	public static function stem($word)
	{
		return \PorterStemmer::stem($word, true);
	}
}

// end of file english.php