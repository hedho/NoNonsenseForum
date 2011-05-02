<?php //display a particular thread’s contents
/* ====================================================================================================================== */
/* NoNonsense Forum © Copyright (CC-BY) Kroc Camen 2011
   licenced under Creative Commons Attribution 3.0 <creativecommons.org/licenses/by/3.0/deed.en_GB>
   you may do whatever you want to this code as long as you give credit to Kroc Camen, <camendesign.com>
*/

require_once './shared.php';

//which thread to show
$FILE = (preg_match ('/^[^.\/]+$/', @$_GET['file']) ? $_GET['file'] : '') or die ('Malformed request');
$xml  = simplexml_load_file ("$FILE.rss", 'allow_prepend') or die ('Malformed XML');

//get the post message, the other fields (name / pass) are retrieved automatically in 'shared.php'
define ('TEXT', mb_substr (@$_POST['text'], 0, SIZE_TEXT, 'UTF-8'));

/* ====================================================================================================================== */

//was the submit button clicked? (and is the info valid?)
if (FORUM_ENABLED && NAME && PASS && AUTH && TEXT && @$_POST['email'] == 'example@abc.com') {
	//ignore a double-post (could be an accident with the back button)
	if (!(
		NAME == $xml->channel->item[0]->author &&
		formatText (TEXT) == $xml->channel->item[0]->description
	)) {
		//where to?
		$page = ceil (count ($xml->channel->item) / FORUM_POSTS);
		$url  = FORUM_URL.PATH_URL.$FILE.($page > 1 ? "?page=$page" : '')."#".base_convert (microtime (), 10, 36);
		
		//add the comment to the thread
		$item = $xml->channel->prependChild ('item');
		$item->addChild ('title',	safeHTML (TEMPLATE_RE.$xml->channel->title));
		$item->addChild ('link',	$url);
		$item->addChild ('author',	safeHTML (NAME));
		$item->addChild ('pubDate',	gmdate ('r'));
		$item->addChild ('description',	safeHTML (formatText (TEXT)));
		
		//save
		file_put_contents ("$FILE.rss", $xml->asXML (), LOCK_EX);
		
		//regenerate the folder's RSS file
		indexRSS ();
	} else {
		//if a double-post, link back to the previous item
		$url = $xml->channel->item[0]->link;
	}
	
	//refresh page to see the new post added
	header ('Location: '.$url, true, 303);
	exit;
}

/* ====================================================================================================================== */

//info for the site header
$HEADER = array (
	'THREAD'	=> safeHTML ($xml->channel->title),
	'PAGE'		=> PAGE,
	'RSS'		=> "$FILE.rss",
	'PATH'		=> safeHTML (PATH),
	'PATH_URL'	=> safeHTML (PATH_URL)
);

/* original post
   ---------------------------------------------------------------------------------------------------------------------- */
//take the first post from the thread (removing it from the rest)
$thread = $xml->channel->xpath ('item');
$post   = array_pop ($thread);

//prepare the first post, which on this forum appears above all pages of replies
$POST = array (
	'TITLE'		=> safeHTML ($xml->channel->title),
	'AUTHOR'	=> safeHTML ($post->author),
	'DATETIME'	=> gmdate ('r', strtotime ($post->pubDate)),
	'TIME'		=> date (DATE_FORMAT, strtotime ($post->pubDate)),
	'DELETE_URL'	=> '/action.php?delete&amp;path='.safeURL (PATH)."&amp;file=$FILE",
	'APPEND_URL'	=> '/action.php?append&amp;path='.safeURL (PATH)."&amp;file=$FILE&amp;id="
			  .substr (strstr ($post->link, '#'), 1),
	'TEXT'		=> $post->description,
	'ID'		=> substr (strstr ($post->link, '#'), 1)
);

//remember the original poster’s name, for marking replies by the OP
$author = (string) $post->author;

/* replies
   ---------------------------------------------------------------------------------------------------------------------- */
if (count ($thread)) {
	//sort the other way around
	//<stackoverflow.com/questions/2119686/sorting-an-array-of-simplexml-objects/2120569#2120569>
	foreach ($thread as &$node) $sort_proxy[] = strtotime ($node->pubDate);
	array_multisort ($sort_proxy, SORT_ASC, $thread);
	
	//paging
	$PAGES  = pageList (PAGE, ceil (count ($thread) / FORUM_POSTS));
	$thread = array_slice ($thread, (PAGE-1) * FORUM_POSTS, FORUM_POSTS);
	
	//index number of the replies, accounting for which page we are on
	$no = (PAGE-1) * FORUM_POSTS;
	foreach ($thread as &$post) $POSTS[] = array (
		'AUTHOR'	=> safeHTML ($post->author),
		'DATETIME'	=> gmdate ('r', strtotime ($post->pubDate)),
		'TIME'		=> date (DATE_FORMAT, strtotime ($post->pubDate)),
		'TEXT'		=> $post->description,
		'DELETED'	=> (bool) $post->xpath ("category[text()='deleted']"),
		'DELETE_URL'	=> '/action.php?delete&amp;path='.safeURL (PATH)."&amp;file=$FILE&amp;id="
				  .substr (strstr ($post->link, '#'), 1),
		'APPEND_URL'	=> '/action.php?append&amp;path='.safeURL (PATH)."&amp;file=$FILE&amp;id="
				  .substr (strstr ($post->link, '#'), 1),
		'OP'		=> $post->author == $author,
		'NO'		=> ++$no,
		'ID'		=> substr (strstr ($post->link, '#'), 1)
	);
}

/* reply form
   ---------------------------------------------------------------------------------------------------------------------- */
if (FORUM_ENABLED) $FORM = array (
	'NAME'	=> safeString (NAME),
	'PASS'	=> safeString (PASS),
	'TEXT'	=> safeString (TEXT),
	'ERROR'	=> empty ($_POST) ? ERROR_NONE
		 : (!NAME ? ERROR_NAME
		 : (!PASS ? ERROR_PASS
		 : (!TEXT ? ERROR_TEXT
		 : ERROR_AUTH)))
);

//all the data prepared, now output the HTML
include FORUM_ROOT.'/themes/'.FORUM_THEME.'/thread.inc.php';

?>