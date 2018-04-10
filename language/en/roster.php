<?php
/**
 * DO NOT CHANGE
**/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || is_array($lang) === false)
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ROSTER'	=> 'Roster',

	'NAME'		=> 'Name',
	'RANK'		=> 'Rank',
	'CLASS'		=> 'Class',
	'LEVEL'		=> 'Level',
	'RACE'		=> 'Race',
	'SPEC'		=> 'Spec',
	'ILEVEL'	=> 'Ilvl',

	'LAST_UPDATED'	=> 'Last Updated',
]);
