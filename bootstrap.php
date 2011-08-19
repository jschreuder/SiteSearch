<?php

Autoloader::add_core_namespace('SiteSearch');

Autoloader::add_classes(array(
	'SiteSearch\\SiteSearch'  => __DIR__.'/classes/sitesearch.php',
	
	'SiteSearch\\SiteSearch_Stemmer_Driver'   => __DIR__.'/classes/sitesearch/stemmer/driver.php',
	'SiteSearch\\SiteSearch_Stemmer_Dutch'    => __DIR__.'/classes/sitesearch/stemmer/dutch.php',
	'SiteSearch\\SiteSearch_Stemmer_English'  => __DIR__.'/classes/sitesearch/stemmer/english.php',
));


/* End of file bootstrap.php */