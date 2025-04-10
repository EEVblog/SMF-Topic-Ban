<?php

// Can' t be defined outside of smf.
if (!defined('SMF'))
	die ('Hacking attempt...');

//Add the Admin Panel link to list all topics with banned users.
function load_topic_ban_admin(&$admin_areas)
{
	global $txt;

	$admin_areas['members']['areas'] += array(
		'topicbans' => array(
		    'permission' => array('admin_forum'),
			'label' => $txt['topic_bans'],
			'file' => 'BannedTopicUsersAdmin.php',
			'function' => 'TopicBansMenu',
			'icon' => 'members.gif',
			'subsections' => array(
			    'members' => array($txt['topic_bans']),
                'settings' => array($txt['topic_ban_settings']),						
			),
		),
	);
}

//Load topic ban permissions.
function load_topic_ban_permissions(&$permissionGroups, &$permissionList)
{
	global $context;

    $permissionList['board']['topic_ban'] = array(true, 'topic', 'moderate');
	
	$context['non_guest_permissions'][] = 'topic_ban';
}	

//Add the ban members from topics button.
function load_topic_ban_buttons(&$normal_buttons)
{
	global $context, $scripturl;
	
	$context['can_ban_users_topics'] = allowedTo('topic_ban_any') || (allowedTo('topic_ban_own') && $context['user']['started']);

    if (!empty($context['can_ban_users_topics']))
	{
	   $normal_buttons['banTopic'] = array(
	          'text' => 'ban_topic', 
			  'lang' => true,	
			  'url' => $scripturl . '?action=banTopic;topic=' . $context['current_topic']. ';' . $context['session_var'] . '=' . $context['session_id']);
	}		  
}

//Manage banned members from topics button.
function load_topic_managebans_buttons(&$mod_buttons)
{
	global $context, $scripturl;
	
	$context['can_manage_topic_bans'] = allowedTo('topic_ban_any') || (allowedTo('topic_ban_own') && $context['user']['started']);

    if (!empty($context['can_manage_topic_bans']))
	{
	   $mod_buttons['manageBanned'] = array(
	          'text' => 'manage_banned_topic_members', 
			  'lang' => true,	
			  'url' => $scripturl . '?action=manageBanned;topic=' . $context['current_topic'] . '.0');	
	}		  
}

//Action Jackson right here lol.
function load_topic_ban_actions(&$actionsArray)
{
	$actionsArray['banTopic'] = array('Subs-TopicBan.php', 'TopicBan');
	$actionsArray['manageBanned'] = array('Subs-TopicBan.php', 'ManageTopicBannedMembers');
}

//Load our custom language.
function load_topic_ban_language()
{
	loadLanguage('TopicBan');
}

//Ban users from posting in individual topics.
function TopicBan()
{
	global $smcFunc, $context, $user_info, $txt;

    //Load the template.
	loadTemplate('TopicBan');
	
	//Check the topic id and make sure that it exists.
	if (!isset($_REQUEST['topic']) || is_array($_REQUEST['topic']))
		fatal_lang_error('topic_doesnt_exist', false);

	//Get topic id.
	$topic_id = (int)$_REQUEST['topic'];

	//Always do the permission check!
	$permissionCheck = $smcFunc['db_query']('', '
		SELECT id_member_started
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic} 
		LIMIT 1',
		array(
			'current_topic' => $topic_id,
		)
	);
	
	list ($starter) = $smcFunc['db_fetch_row']($permissionCheck);
	$smcFunc['db_free_result']($permissionCheck);
	
	if (!allowedTo('topic_ban_any') && $user_info['id'] == $starter)
		isAllowedTo('topic_ban_own');
	else
		isAllowedTo('topic_ban_any');

	// Set up the inputs for the form.
	$context['bannedUsers'] = isset($_POST['banned_username']) ? $_POST['banned_username'] : '';
	$context['pmSubject'] = isset($_POST['subject']) ? $_POST['subject'] : '';
	$context['pmBody'] = isset($_POST['body']) ? $_POST['body'] : '';
	
	//Ban users from posting in this topic.
	if (isset($_POST['banTopic2']))
	{
		 //Verify that the form has a "sc" element in it.
		checkSession();
		
		//Escape strings for security.
		$_POST['banned_username'] = htmlspecialchars($_POST['banned_username']);
		$_POST['subject'] = htmlspecialchars($_POST['subject']);
		$_POST['body'] = $smcFunc['htmlspecialchars']($_POST['body'], ENT_QUOTES);
		
		//Censor the subject and body of the pm.
		censorText($_POST['subject']);
		censorText($_POST['body']);
			
		//Make sure that member exists.
        $_POST['banned_username'] = empty($_POST['banned_username']) ? '' : trim($_POST['banned_username']);
		
		if(empty($_POST['banned_username']))
		   	fatal_lang_error('you must_select_member', false);
	   
	    if (!empty($_POST['banned_username']))
        {
            $memberCheck = $smcFunc['db_query']('', '
                    SELECT id_member
                    FROM {db_prefix}members
                    WHERE member_name = {string:username} OR real_name = {string:username}',
                    array(
                        'username' => $_POST['banned_username'],
                    )
            );

            if ($smcFunc['db_num_rows']($memberCheck) == 0)
                  fatal_lang_error('invalid_username', false);
            else
                  $smcFunc['db_free_result']($memberCheck);
        }
		
		//You can not ban the same user twice from the same topic.
	    $banCheck = $smcFunc['db_query']('', '
		         SELECT COUNT(*)
		         FROM {db_prefix}log_topic_bans
		         WHERE banned_username = {string:username}
				 AND topic_id  = {int:current_topic}
				 LIMIT 1',
		         array(
			           'username' => $_POST['banned_username'],
					   'current_topic' => $topic_id,
		              )
	    );
	
	    list ($banned) = $smcFunc['db_fetch_row']($banCheck);
	    $smcFunc['db_free_result']($banCheck);

	    if (!empty($banned))
		     fatal_lang_error('you_already_banned_member', false);
		 
		//Get banned members from topics.
	    $result = $smcFunc['db_query']('', '
			   SELECT id_member, member_name, real_name
			   FROM {db_prefix}members 
			   WHERE member_name = {string:username} OR real_name = {string:username}',
			   array(
				     'username' => $_POST['banned_username'],
				    )
	     );

         //Group and loop through them to get their ids.
	     $bans = array();
	
	     while ($row = $smcFunc['db_fetch_assoc']($result))
	     {
		     $bans[] = array('id' => $row['id_member']);
	     }
	
	    $smcFunc['db_free_result']($result);
	
	    foreach($bans as $ban)
		{
		   $memID = (int) $ban['id'];
		}
		
		//Do the actual job.
		$smcFunc['db_insert']('', '{db_prefix}log_topic_bans',
		
		array(
		      'topic_id' => 'int', 'id_banned_member' => 'int', 'banned_username' => 'string', 'id_banning_member' => 'int', 'banning_username' => 'string', 'time' => 'int', 'subject' => 'string', 'body' => 'string',
		),
		array(
			  $topic_id, $memID, $_POST['banned_username'], $user_info['id'], $user_info['username'], time(), $_POST['subject'], $_POST['body'],
		),
		      array()
		);
			
		$id = $smcFunc['db_insert_id']('{db_prefix}log_topic_bans', 'id_ban');
		
		//Send the pm to banned members.
		banned_from_topic_pm();
		
		//Log action in the admin log.
	    logAction('topic_ban', array('bannedUsers' => $_POST['banned_username'], 'topic' => $topic_id), $log_type = 'admin');

		//User(s) banned successfully. Redirect back to the topic.
		redirectexit('topic=' . $topic_id);
	}
	
	//Select the topic subject for display at the page title.
	$subject = $smcFunc['db_query']('', '
		SELECT m.subject
		FROM ({db_prefix}messages AS m, {db_prefix}topics AS t)
		WHERE m.id_topic = {int:current_topic}
			AND t.id_first_msg = m.id_msg
		LIMIT 1',
		array(
			'current_topic' => $topic_id,
		)
	);

	//List the topic subject.
	list ($topicSubject) = $smcFunc['db_fetch_row']($subject);
	$smcFunc['db_free_result']($subject);
	
	//Register the var for use in template.
	$context['topicSubject'] = $topicSubject;

	//Set the page title.
	$context['page_title'] = $txt['ban_topic'] . ': ' . $topicSubject . ' ';
}

//Banned users pm function.
function banned_from_topic_pm()
{
	global $sourcedir, $smcFunc, $user_profile, $user_info;
	
	//Without this no pm.
	require_once($sourcedir . '/Subs-Post.php');
	
	//Get banned members from topics.
	$result = $smcFunc['db_query']('', '
			SELECT id_member, member_name, real_name
			FROM {db_prefix}members 
			WHERE member_name = {string:username} OR real_name = {string:username}',
			array(
				   'username' => $smcFunc['htmlspecialchars']($_POST['banned_username'], ENT_QUOTES),
				 )
	);

    //Group and loop through them to send the pm.
	$banned = array();
	
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$banned[] = array(
			'id' => $row['id_member'],
		);
	}
	
	$smcFunc['db_free_result']($result);
	
	//Do not send the pm if no one is banned.
	if (empty($banned))
		return true;
	
	//Send the pm only if subject and body are not empty.
	if (!empty($_POST['subject']) && !empty($_POST['body']))
	{
		foreach($banned as $ban)
		{
	        // Load member data of the pm sender.
		    $pm_sender = (isset($user_info['id']) ? $user_info['id'] : 0);
		    loadMemberData($pm_sender, false, 'normal');
		
		    //Construct the array for sendpm().
	        $pm_to = array(
			    'to' => array($ban['id']),
			    'bcc' => array(),
	        );
	   
            $pm_from = array(
			    'id' => (isset($user_info['id']) ? $user_info['id'] : 0),
			    'name' => (isset($user_profile[$pm_sender]['real_name'])),
			    'username' => (isset($user_profile[$pm_sender]['member_name'])),
	        );
		
		    $pm_subject = $_POST['subject'];
		    $pm_body = $_POST['body'];

		    //Send the pm.
		    sendpm($pm_to, $pm_subject, $pm_body, false, $pm_from);
	   }
   }
}

//Manage banned members for this topic.
function ManageTopicBannedMembers()
{
	//Define the global vars.
	global $smcFunc, $context, $user_info, $scripturl, $sourcedir, $txt, $topic;
	
	//Check the topic id and make sure that it exists.
	if (empty($topic))
		fatal_lang_error('no_board', false);
	
	$context['page_title'] = $txt['topic_bans'];

	//Always do the permission check!
	$permissionCheck = $smcFunc['db_query']('', '
		SELECT id_member_started
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic} 
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	
	list ($starter) = $smcFunc['db_fetch_row']($permissionCheck);
	$smcFunc['db_free_result']($permissionCheck);
	
	if (!allowedTo('topic_ban_any') && $user_info['id'] == $starter)
		isAllowedTo('topic_ban_own');
	else
		isAllowedTo('topic_ban_any');
	
	// User pressed the 'remove selection button'.
	if (!empty($_POST['delete']) && !empty($_POST['remove']) && is_array($_POST['remove']))
	{
		checkSession();

		foreach ($_POST['remove'] as $index => $log_time)
			$_POST['remove'][(int) $index] = (int) $log_time;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_topic_bans
			WHERE id_ban IN ({array_int:topic_list})',
			array(
				'topic_list' => $_POST['remove'],
			)
		);

		redirectexit('action=manageBanned;topic=' . $topic);
	}
	
	$listOptions = array(
		'id' => 'topic_list',
		'items_per_page' => 20,
		'base_href' => $scripturl . '?action=manageBanned;topic=' . $topic,
		'default_sort_col' => 'time',
		'no_items_label' => $txt['no_banned_members_topic'],
		'get_items' => array(
			'function' => 'list_getThisTopicBannedMembers',
		),
		'get_count' => array(
			'function' => 'list_countThisTopicBannedMembers',
		),
		'columns' => array(
			'id_banned_member' => array(
				'header' => array(
					'value' => $txt['banned_users_from_topic'],
					'style' => 'text-align: center;',
				),
				'data' => array(
					'db' => 'id_banned_member',
					'class' => 'mediumtext',
					'style' => 'width: 20%; text-align: center;',
				),
				'sort' =>  array(
					'default' => 'id_banned_member DESC',
					'reverse' => 'id_banned_member',
				),
			),
			'id_banning_member' => array(
				'header' => array(
					'value' => $txt['banned_by'],
				),
				'data' => array(
					'db' => 'id_banning_member',
					'class' => 'mediumtext',
					'style' => 'width: 20%; text-align: center;',
				),
				'sort' =>  array(
					'default' => 'id_banning_member DESC',
					'reverse' => 'id_banning_member',
				),
			),
			'time' => array(
				'header' => array(
					'value' => $txt['ban_time'],
				),
				'data' => array(
					'db' => 'time',
					'class' => 'mediumtext',
					'style' => 'width: 60%; text-align: center;',
				),
				'sort' =>  array(
					'default' => 'time DESC',
					'reverse' => 'time',
				),
			),
		    'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
			),
			'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[]" value="%1$d" class="input_check" />',
						'params' => array(
							'id' => false,
						),
					),
					'style' => 'text-align: center',
				),
			),
		),	
		'form' => array(
			'href' => $scripturl . '?action=manageBanned;topic=' . $topic,
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="delete" value="' . $txt['delete_selected_bans'] . '" onclick="return confirm(\'' . $txt['delete_selected_bans_confirm'] . '\');" class="button_submit" />',
				'style' => 'text-align: right;',
			),
		),
	);

    require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'topic_list';
}

//Get all banned members for this topic.
function list_getThisTopicBannedMembers($start, $items_per_page, $sort)
{
	global $smcFunc, $topic, $scripturl;

	$result = $smcFunc['db_query']('', '
		SELECT bl.id_ban, bl.id_banned_member, bl.banned_username, bl.id_banning_member, bl.banning_username, bl.time
		FROM ({db_prefix}log_topic_bans AS bl, {db_prefix}members AS mem, {db_prefix}members AS mem2)
		WHERE bl.topic_id = {int:current_topic}
		AND mem.id_member = bl.id_banned_member
		AND mem2.id_member = bl.id_banning_member
		ORDER BY {raw:sort}
		   LIMIT {int:start}, {int:per_page}',
		array(
			'sort' => $sort,
			'start' => $start,
			'per_page' => $items_per_page,
			'current_topic' => $topic,
		)
	);

	$topics = array();
	
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{		
		$topics[] = array(
		    'id' => $row['id_ban'],
			'id_banned_member' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_banned_member'] . '">' . $row['banned_username'] . '</a>',
			'id_banning_member' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_banning_member'] . '">' . $row['banning_username'] . '</a>',
			'time' => timeformat($row['time']),
		);
	}
	
	return $topics;

	$smcFunc['db_free_result']($result);
}

//Count all bans for this topic.
function list_countThisTopicBannedMembers()
{
	global $smcFunc, $topic;

	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_topic_bans
		WHERE topic_id = {int:current_topic}',
		array(
			'current_topic' => $topic,
		)
	);
	
	list ($topics) = $smcFunc['db_fetch_row']($result);
	
	$smcFunc['db_free_result']($result);

	return $topics;
}