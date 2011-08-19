<?php

namespace SiteSearch;

class SiteSearch_Stemmer_Dutch implements SiteSearch_Stemmer_Driver {
	
	protected static $use_step_2 = false;
	
	public static function stem($word)
	{
		static::$use_step_2 = FALSE;
		
		// Start with removing accented suffixes
		$word = static::step_0($word);
			
		// Cleanup accents
		$word = str_replace(
					array('ä','á','à','â','ã','ë','é','è','ê','ï','í','ì','î','ö','ó','ò','ô','ü','ú','ù','û','ç','ñ'),
					array('a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','u','u','u','u','c','n'),
					$word);
		
		// Put initial y, y after a vowel, and i between vowels into upper case (treat as consonants).
		$word = preg_replace(array('/^y|(?<=[aeiouyè])y/u', '/(?<=[aeiouyè])i(?=[aeiouyè])/u'),
					array('Y', 'I'),
					$word);
		
		/* R1 is the region after the first non-vowel following a vowel, or is the
			null region at the end of the word if there is no such non-vowel. */
		$r1 = 0;
		if (preg_match('/[aeiouyè][^aeiouyè]/u', $word, $matches, PREG_OFFSET_CAPTURE))
		{
			$r1 = $matches[0][1] + 2;
		}
		
		/* R2 is the region after the first non-vowel following a vowel in R1, or is
			the null region at the end of the word if there is no such non-vowel. */
		$r2 = 0;
		if (preg_match('/[aeiouyè][^aeiouyè]/u', $word, $matches, PREG_OFFSET_CAPTURE, $r1))
		{
			$r2 = $matches[0][1] + 2;
		}
		
		// Steps 1-4: suffix removal
		$word = static::step_1($word, $r1, $r2);
		$word = static::step_2($word, $r1, $r2);
		$word = static::step_3($word, $r1, $r2);
		$word = static::step_4($word, $r1, $r2);
		
		// Return I en Y that were treated as consonants to lowercase
		$word = str_replace(array('Y', 'I'), array('y', 'i'), $word);
		
		return $word;
	}
	
	protected static function step_0($word)
	{
		// Step 0: accented suffixes
		return preg_replace('/eën$/u', 'e', preg_replace('/(ieel|iële|ieën)$/u', 'ie', $word));
	}
	
	protected static function step_1($word, $r1, $r2)
	{
		// Step 1:
		// Search for the longest among the following suffixes, and perform the action indicated
		if ($r1)
		{
			// -heden
			if (preg_match('/heden$/u', $word, $matches, 0, $r1))
			{
				return preg_replace('/heden$/u', 'heid', $word, -1, $count);
			}
			// -en(e)
			elseif (preg_match('/(?<=[^aeiouyè]|gem)ene?$/u', $word, $matches, 0, $r1))
			{
				return static::undouble(preg_replace('/ene?$/u', '', $word, -1, $count));
			}
			// -s(e)
			elseif (preg_match('/(?<=[^jaeiouyè])se?$/u', $word, $matches, 0, $r1))
			{
				return rtrim(preg_replace('/se?$/u', '', $word, -1, $count), "'");
			}
			// -d(t)
			elseif (preg_match('/dt$/u', $word, $matches, 0, $r1))
			{
				return preg_replace('/dt$/u', 'd', $word, -1, $count);
			}
			// -Ci(e)
			elseif (preg_match('/[^aeiouyè]ie$/', $word, $matches, 0, $r1))
			{
				return preg_replace('/ie$/', 'i', $word, -1, $count);
			}
			// -Ci(sch(e))
			elseif (preg_match('/[^aeiouyè]isch[e]?$/', $word, $matches, 0, $r1))
			{
				return preg_replace('/isch[e]?$/', 'i', $word, -1, $count);
			}
		}
		return $word;
	}
	
	protected static function step_2($word, $r1, $r2)
	{
		// Step 2:
		// Delete suffix e if in R1 and preceded by a non-vowel, and then undouble the ending
		if ($r1)
		{
			if (preg_match('/(?<=[^aeiouyè])e$/u', $word, $matches, 0, $r1))
			{
				// TODO: this should be here to make any sense
				// global $_dutchstemmer_step2;
				static::$use_step_2 = TRUE;
				return static::undouble(preg_replace('/e$/u', '', $word, -1, $count));
			}
		}
		return $word;
	}
	
	protected static function step_3($word, $r1, $r2)
	{
		// Step 3a: heid
		// delete heid if in R2 and not preceded by c, and treat a preceding en as in step 1(b)
		if ($r2)
		{
			if (preg_match('/(?<!c)heid$/u', $word, $matches, 0, $r2))
			{
				$word = preg_replace('/heid$/u', '', $word, -1, $count);
				if (preg_match('/en$/u', $word, $matches, 0, $r1))
				{
					$word = static::undouble(preg_replace('/en$/u', '', $word, -1, $count));
				}
			}
		}
		
		// Step 3b: d-suffixes (*)
		// Search for the longest among the following suffixes, and perform the action indicated.
		if ($r2)
		{
			// -baar
			if (preg_match('/baar$/u', $word, $matches, 0, $r2))
			{
				$word = preg_replace('/baar$/u', '', $word, -1, $count);
			}
			// -lijk
			elseif (preg_match('/lijk$/u', $word, $matches, 0, $r2))
			{
				$word = static::step_2(preg_replace('/lijk$/u', '', $word, -1, $count), $r1, $r2);
			}
			// -end / -ing
			elseif (preg_match('/(end|ing)$/u', $word, $matches, 0, $r2))
			{
				$word = preg_replace('/(end|ing)$/u', '', $word, -1, $count);
				// -ig
				if (preg_match('/(?<!e)ig$/u', $word, $matches, 0, $r2))
				{
					$word = preg_replace('/ig$/u', '', $word, -1, $count);
				}
			}
			// -ig
			elseif (preg_match('/(?<!e)ig$/u', $word, $matches, 0, $r2))
			{
				$word = preg_replace('/ig$/u', '', $word, -1, $count);
			}
			// -bar
			elseif (static::$use_step_2 && preg_match('/bar$/u', $word, $matches, 0, $r2))
			{
				$word = preg_replace('/bar$/u', '', $word, -1, $count);
			}
		}
		
		return $word;
	}
	
	protected static function step_4($word, $r1, $r2)
	{
		// Step 4: undouble vowel
		// If the words ends CVD, where C is a non-vowel, D is a non-vowel other than
		// I, and V is double a, e, o or u, remove one of the vowels from V 
		// (for example, maan -> man, brood -> brod).
		if (preg_match('/[^aeiouyè](aa|ee|oo|uu)[^Iaeiouyè]$/u', $word))
		{
			$word = substr($word, 0, -2) . str_replace(array('s', 'f'), array('z', 'v'), substr($word, -1));
		}
		return $word;
	}
	
	protected static function undouble($word)
	{
		return preg_match('/(bb|dd|gg|kk|ll|mm|nn|pp|rr|ss|tt|zz)$/u', $word) ? substr($word, 0, -1) : $word;
	}
}