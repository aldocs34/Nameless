<?php
/*
 *	Made by Samerton
 *  https://github.com/NamelessMC/Nameless/
 *  NamelessMC version 2.0.0-pr8
 *
 *  License: MIT
 *
 *  Latest Posts Widget
 */
class LatestPostsWidget extends WidgetBase {
	private $_smarty, $_language, $_cache, $_user;

    public function __construct($pages = array(), $latest_posts_language, $by_language, $smarty, $cache, $user, $language){
    	$this->_smarty = $smarty;
    	$this->_cache = $cache;
    	$this->_user = $user;
    	$this->_language = $language;

        parent::__construct($pages);

        // Get order
        $order = DB::getInstance()->query('SELECT `order` FROM nl2_widgets WHERE `name` = ?', array('Latest Posts'))->first();
		// Get location
		$location = DB::getInstance()->query('SELECT `location` FROM nl2_widgets WHERE `name` = ?', array('Latest Posts'))->first();

        // Set widget variables
        $this->_module = 'Forum';
        $this->_name = 'Latest Posts';
        $this->_location = $location->location;
        $this->_description = 'Display latest posts from your forum.';
        $this->_order = $order->order;

        $this->_smarty->assign(array(
        	'LATEST_POSTS' => $latest_posts_language,
	        'BY' => $by_language
        ));
    }

    public function initialise(){
	    require_once(ROOT_PATH . '/modules/Forum/classes/Forum.php');
	    $forum = new Forum();
	    $queries = new Queries();
	    $timeago = new Timeago(TIMEZONE);

	    if($this->_user->isLoggedIn()) {
		    $user_group = $this->_user->data()->group_id;
		    $secondary_groups = $this->_user->data()->secondary_groups;
	    } else {
		    $user_group = null;
		    $secondary_groups = null;
	    }

	    if($user_group){
		    $cache_name = 'forum_discussions_' . $user_group . '-' . $secondary_groups;
	    } else {
		    $cache_name = 'forum_discussions_guest';
	    }

	    $this->_cache->setCache($cache_name);

	    if($this->_cache->isCached('discussions')){
		    $template_array = $this->_cache->retrieve('discussions');

	    } else {
		    // Generate latest posts
		    $discussions = $forum->getLatestDiscussions($user_group, $secondary_groups, ($this->_user->isLoggedIn() ? $this->_user->data()->id : 0));

		    $n = 0;
		    // Calculate the number of discussions to display (5 max)
		    if(count($discussions) <= 5){
			    $limit = count($discussions);
		    } else {
			    $limit = 5;
		    }

		    $template_array = array();

		    // Generate an array to pass to template
		    while($n < $limit){
			    // Get the name of the forum from the ID
			    $forum_name = $queries->getWhere('forums', array('id', '=', $discussions[$n]['forum_id']));
			    $forum_name = Output::getPurified(htmlspecialchars_decode($forum_name[0]->forum_title));

			    // Get the number of replies
			    $posts = $queries->getWhere('posts', array('topic_id', '=', $discussions[$n]['id']));
			    $posts = count($posts);

			    // Get the last reply user's avatar
			    $last_reply_avatar = $this->_user->getAvatar($discussions[$n]['topic_last_user'], "../", 64);

			    // Is there a label?
			    if($discussions[$n]['label'] != 0){ // yes
				    // Get label
				    $label = $queries->getWhere('forums_topic_labels', array('id', '=', $discussions[$n]['label']));
				    if(count($label)){
					    $label = $label[0];

					    $label_html = $queries->getWhere('forums_labels', array('id', '=', $label->label));
					    if(count($label_html)){
						    $label_html = $label_html[0]->html;
						    $label = str_replace('{x}', Output::getClean($label->name), $label_html);
					    } else $label = '';
				    } else $label = '';
			    } else { // no
				    $label = '';
			    }

			    // Add to array
			    $template_array[] = array(
				    'topic_title' => Output::getClean($discussions[$n]['topic_title']),
				    'topic_id' => $discussions[$n]['id'],
				    'topic_created_rough' => $timeago->inWords(date('d M Y, H:i', $discussions[$n]['topic_date']), $this->_language->getTimeLanguage()),
				    'topic_created' => date('d M Y, H:i', $discussions[$n]['topic_date']),
				    'topic_created_username' => Output::getClean($this->_user->idToNickname($discussions[$n]['topic_creator'])),
				    'topic_created_mcname' => Output::getClean($this->_user->idToName($discussions[$n]['topic_creator'])),
				    'topic_created_style' => $this->_user->getGroupClass($discussions[$n]['topic_creator']),
				    'topic_created_user_id' => Output::getClean($discussions[$n]['topic_creator']),
				    'locked' => $discussions[$n]['locked'],
				    'forum_name' => $forum_name,
				    'forum_id' => $discussions[$n]['forum_id'],
				    'views' => $discussions[$n]['topic_views'],
				    'posts' => $posts,
				    'last_reply_avatar' => $last_reply_avatar,
				    'last_reply_rough' => $timeago->inWords(date('d M Y, H:i', $discussions[$n]['topic_reply_date']), $this->_language->getTimeLanguage()),
				    'last_reply' => date('d M Y, H:i', $discussions[$n]['topic_reply_date']),
				    'last_reply_username' => Output::getClean($this->_user->idToNickname($discussions[$n]['topic_last_user'])),
				    'last_reply_mcname' => Output::getClean($this->_user->idToName($discussions[$n]['topic_last_user'])),
				    'last_reply_style' => $this->_user->getGroupClass($discussions[$n]['topic_last_user']),
				    'last_reply_user_id' => Output::getClean($discussions[$n]['topic_last_user']),
				    'label' => $label,
				    'link' => URL::build('/forum/topic/' . $discussions[$n]['id'] . '-' . $forum->titleToURL($discussions[$n]['topic_title'])),
				    'forum_link' => URL::build('/forum/forum/' . $discussions[$n]['forum_id']),
				    'author_link' => URL::build('/profile/' . Output::getClean($this->_user->idToName($discussions[$n]['topic_creator']))),
				    'last_reply_profile_link' => URL::build('/profile/' . Output::getClean($this->_user->idToName($discussions[$n]['topic_last_user']))),
				    'last_reply_link' => URL::build('/forum/topic/' . $discussions[$n]['id'] . '-' . $forum->titleToURL($discussions[$n]['topic_title']), 'pid=' . $discussions[$n]['last_post_id'])
			    );

			    $n++;
		    }

		    $this->_cache->store('discussions', $template_array, 60);
	    }

	    // Generate HTML code for widget
	    $this->_smarty->assign('LATEST_POSTS_ARRAY', $template_array);

	    $this->_content = $this->_smarty->fetch('widgets/forum/latest_posts.tpl');
    }
}