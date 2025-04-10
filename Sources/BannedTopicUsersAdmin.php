<?php

// If we are outside SMF throw an error.
if (!defined('SMF')) {
    die('Hacking attempt...');
}

//Admin panel menu for the mod.
function TopicBansMenu()
{
	global $context, $txt;
	
	isAllowedTo('admin_forum');

	$subActions = array(
		'members' => 'TopicBannedMmembers',
		'settings' => 'TopicBannedMmembersSettings',
	);

	if (!isset($_GET['sa']) || !isset($subActions[$_GET['sa']]))
		$_GET['sa'] = 'members';

	$context['page_title'] = $txt['topic_bans'];

	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => &$txt['topic_bans'],
		'description' => $txt['topic_bans_desc'],
	);
	
	$context['sub_template'] = 'show_settings';

	// Call the right function for this sub-acton.
	$subActions[$_GET['sa']]();
}

//Display all topics that have banned users in them in the admin panel.
function TopicBannedMmembers()
{
	global $txt, $sourcedir, $scripturl, $context, $smcFunc;
	
	isAllowedTo('admin_forum');
	
	// User pressed the 'remove selection button'.
	if (!empty($_POST['delete']) && !empty($_POST['remove']) && is_array($_POST['remove']))
	{
		checkSession();

		foreach ($_POST['remove'] as $index => $log_time)
			$_POST['remove'][(int) $index] = (int) $log_time;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_topic_bans
			WHERE id_ban IN ({array_int:ban_list})',
			array(
				'ban_list' => $_POST['remove'],
			)
		);

		redirectexit('action=admin;area=topicbans;sa=members');
	}
	
	$listOptions = array(
		'id' => 'ban_list',
		'items_per_page' => 20,
		'base_href' => $scripturl . '?action=admin;area=topicbans;sa=members',
		'default_sort_col' => 'subject',
		'no_items_label' => $txt['no_banned_members_yet'],
		'get_items' => array(
			'function' => 'list_getTopicBannedMembers',
		),
		'get_count' => array(
			'function' => 'list_countTopicBannedMembers',
		),
		'columns' => array(
			'subject' => array(
				'header' => array(
					'value' => $txt['topic_bans_list'],
				),
				'data' => array(
					'db' => 'subject',
					'class' => 'mediumtext',
					'style' => 'width: 40%; text-align: left;',	
				),
				'sort' =>  array(
					'default' => 'subject DESC',
					'reverse' => 'subject',
				),
			),
			'id_banned_member' => array(
				'header' => array(
					'value' => $txt['banned_users_from_topic'],
					'style' => 'text-align: center;',
				),
				'data' => array(
					'db' => 'id_banned_member',
					'class' => 'mediumtext',
					'style' => 'width: 10%; text-align: center;',
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
					'style' => 'width: 10%; text-align: center;',
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
					'style' => 'width: 40%; text-align: center;',
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
			'href' => $scripturl . '?action=admin;area=topicbans;sa=members',
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
	$context['default_list'] = 'ban_list';
}

//Get all moderated member.
function list_getTopicBannedMembers($start, $items_per_page, $sort)
{
	global $smcFunc, $scripturl;
	
	$result = $smcFunc['db_query']('', '
	       SELECT m.subject, t.id_topic, bl.id_ban, bl.id_banned_member, bl.banned_username, bl.id_banning_member, bl.banning_username, bl.time
		   FROM ({db_prefix}messages AS m, {db_prefix}topics AS t, {db_prefix}log_topic_bans AS bl, {db_prefix}members AS mem, {db_prefix}members AS mem2)
		   WHERE t.id_first_msg = m.id_msg 
		   AND t.id_topic = bl.topic_id
		   AND mem.id_member = bl.id_banned_member
		   AND mem2.id_member = bl.id_banning_member
		   ORDER BY {raw:sort}
		   LIMIT {int:start}, {int:per_page}',
		   array(
				 'sort' => $sort,
		         'start' => $start,
			     'per_page' => $items_per_page,
			    )
		   );
	
    $topics = array();
	
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		//Trim characters for topic subjects if they are too long.
		if (strlen($row['subject']) > 40)
	    {
	        $row['subject'] = substr($row['subject'], 0, 40) . "..."; 
	    }
				
		$topics[] = array(
		    'id' => $row['id_ban'],
		    'subject' => '<a href="' . $scripturl . '?topic=' . $row["id_topic"] . '.0" target="_blank" rel="noopener">' . $row['subject'] . '</a>',
			'id_banned_member' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_banned_member'] . '">' . $row['banned_username'] . '</a>',
			'id_banning_member' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_banning_member'] . '">' . $row['banning_username'] . '</a>',
			'time' => timeformat($row['time']),
		);
	}
	
	return $topics;

	$smcFunc['db_free_result']($result);
}

//Count all topic bans for the pagination.
function list_countTopicBannedMembers()
{
	global $smcFunc;
	
	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_topic_bans',
		array(
		
		)
	);
				
	list ($topics) = $smcFunc['db_fetch_row']($result);
	
	$smcFunc['db_free_result']($result);

	return $topics;
}

//Mod settings.
function TopicBannedMmembersSettings($return_config = false) 
{
	global $txt, $scripturl, $context, $sourcedir, $modSettings;
	
	require_once($sourcedir.'/ManageServer.php');
	
	$context['page_title'] = $txt['topic_ban_settings'];
	$context['sub_template'] = 'show_settings';
	
	$config_vars = array(
		array('check', 'enable_topic_ban_message'),
        array('large_text', 'topic_ban_message_text'),
        '',
		array('check', 'enable_complete_topic_ban'),	
	);
		
	if (isset($_GET['save']))
	{
		saveDBSettings($config_vars);
		redirectexit('action=admin;area=topicbans;sa=settings');
	}
	
	$context['settings_title'] = $txt['topic_ban_settings'];
	$context['post_url'] = $scripturl . '?action=admin;area=topicbans;save;sa=settings';
	
	prepareDBSettingContext($config_vars);
}