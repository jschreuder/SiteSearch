SiteSearch
==========

SiteSearch is part of a legacy project of mine when I had nothing like Elastic
Search or Solr available. The idea is to index pages by removing accents,
stemming all words, removing noise words and scoring by number of occurrences
and part of the data.

Each language needs its own driver. There's a Dutch & an English one included.

Install
-------

Just add to your composer.json the following requires (porter-stemmer only
necessary for English driver):

.. code-block:: javascript

   {
       "require": {
            "webspot/sitesearch": "dev-master",
            "camspiers/porter-stemmer": "~1.0"
       }
   }

And you need a table, which you may rename. Also the type of the identifier may
be changed.

.. code-block:: sql

  CREATE TABLE IF NOT EXISTS `sitesearch` (
      `word` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
      `score` int(11) NOT NULL,
      `page_id` int(11) COLLATE utf8_unicode_ci NOT NULL,
      PRIMARY KEY (`word`,`page_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

Simple Example
--------------

.. code-block:: php

  <?php

  use Webspot\SiteSearch;

  $language = new SiteSearch\Language\English();
  $pdo = new PDO('sqlite::memory:');

  $search = new SiteSearch\SiteSearch($language, $pdo);

  $query = $pdo->query('SELECT id, title, body FROM pages');
  foreach ($query->fetch(PDO::FETCH_ASSOC) as $page) {
      $search->indexPage($page, $page['id']);
  }

Note that all values in the `$page` array will be indexed.

After which you can run a search using:

.. code-block:: php

  // search for results containing all words
  $search->search($input);

  // search for results containing any of the words
  $search->search($input, SiteSearch\SiteSearch::SEARCH_MATCH_ANY);

The results will look like the following:

.. code-block:: php

  [
      'page_id' => 6,
      'word' => 'example',
      'score' => 3,
      'matched' => 1,
      'weight' => 7,
  ]

The `page_id` is probably what you were looking for, the `word` and `score`
are just from one of the matches. The `matched` key contains the number of
keywords that were matched on the page, when matching all the words this
will always be the same as the number of keywords. And lastly the `weight`
key will contain the cumulative score of all matched words on the page.

The results are ordered by the number of matches first and the weight second.

More Advanced Example
---------------------

This example will demonstrate 2 more advanced usages: using a different table
for fetching the results than when indexing; and scoring different parts of an
input object with different weights.

You use a different table for searching to fetch some more properties alongside
the search results. For example the page title, slug & short description. To
make this work you can create a view that does a join for you:

.. code-block:: sql

  CREATE
    ALGORITHM = UNDEFINED
    VIEW `sitesearchresult`
    AS SELECT * FROM `sitesearch` INNER JOIN `pages` ON `sitesearch`.`page_id` = `pages`.`id`

Now we can use this new view for fetching when configuring the search object:

.. code-block:: php

  <?php

  use Webspot\SiteSearch;

  $language = new SiteSearch\Language\English();
  $pdo = new PDO('sqlite::memory:');

  $search = new SiteSearch\SiteSearch($language, $pdo, ['sitesearch', 'sitesearchresult']);

When executing a search now the result will also include any columns joined on
the `sitesearch` table in the view. But let's assume that we want to score the
title as 5 times more important than the body and any keywords as 3 times more
important.

For this we must add weights before calling `indexPage()`:

.. code-block:: php

  $search
    ->setWeight('title', 5)
    ->setWeight('keywords', 3)
    ->setWeight('body', 1);

The body is actually unnecessary, as it defaults to 1 when not set.
