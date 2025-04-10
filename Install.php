<?php

// If we have found SSI.php and we are outside of SMF, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
{
	$ssi = true;
	require_once(dirname(__FILE__) . '/SSI.php');
}

// If we are outside SMF and can't find SSI.php, then throw an error.
elseif (!defined('SMF'))
	exit('<strong>Error:</strong> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

//Globals.
global $smcFunc, $db_prefix;

db_extend('packages');

// Create the table
$smcFunc['db_create_table'] ('{db_prefix}log_topic_bans', 
	array(
		array(
			  'name' => 'id_ban', 
			  'type' => 'mediumint', 
			  'size' => 8, 
			  'null' => false,
			  'unsigned' => true, 
			  'auto' => true,
		),
		array(
			  'name' => 'topic_id',
			  'type' => 'mediumint',
			  'size' => 8,
		),
		array(
			  'name' => 'id_banned_member', 
			  'type' => 'mediumint', 
			  'size' => 8, 
			  'null' => false,
			  'unsigned' => true, 
		),
		array(
			  'name' => 'banned_username', 
			  'type' => 'varchar', 
			  'size' => '255',
		      'default' => '',
		),
		array(
			  'name' => 'id_banning_member', 
			  'type' => 'mediumint', 
			  'size' => 8, 
			  'null' => false,
			  'unsigned' => true, 
		),
		array(
			  'name' => 'banning_username', 
			  'type' => 'varchar', 
			  'size' => '255',
		      'default' => '',
		),
		array(
			  'name' => 'time',
			  'type' => 'int',					
			  'size' => '10',
			  'default' => '0',	
	   ),
	   array(
			'name' => 'subject', 
			'type' => 'tinytext', 
			'default' => '',
		),
		array(
			'name' => 'body', 
			'type' => 'mediumtext', 
			'default' => '',
		),
	),
	array(
		array(
			'name' => 'id_ban',
			'type' => 'primary',
			'columns' => array('id_ban'),
		),
	),
	array(),
	'ignore');

//Insert to the schedule task table.	
$smcFunc['db_insert']('ignore',
	'{db_prefix}scheduled_tasks',
	array('next_time' => 'int', 'time_offset' => 'int', 'time_regularity' => 'int', 'time_unit' => 'string', 'disabled' => 'int', 'task' => 'string'),
	array(0, 0, 1, "d", 0, 'delete_topicbans'),
	array('id_task')
);	

if(SMF == 'SSI')
	echo 'The database updates for the Member Notepad mod are complete!';