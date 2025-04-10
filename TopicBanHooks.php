<?php

//Require ssi.php file.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
{
	$ssi = true;
	require_once(dirname(__FILE__) . '/SSI.php');
}

// Can' t be defined outside of smf.
elseif (!defined('SMF'))
	exit('<strong>Error:</strong> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

$hook_functions = array(
	'integrate_pre_include' => '$sourcedir/Subs-TopicBan.php',
	'integrate_load_permissions' => 'load_topic_ban_permissions',
	'integrate_display_buttons' => 'load_topic_ban_buttons',
	'integrate_mod_buttons' => 'load_topic_managebans_buttons',
	'integrate_actions' => 'load_topic_ban_actions',
	'integrate_pre_load' => 'load_topic_ban_language',
	'integrate_admin_areas' => 'load_topic_ban_admin',
);

if (!empty($context['uninstalling']))
	$call = 'remove_integration_function';
else
	$call = 'add_integration_function';

foreach ($hook_functions as $hook => $function)
	$call($hook, $function);			

if (!empty($ssi))
	echo 'Congratulations! You have successfully installed this mod!';