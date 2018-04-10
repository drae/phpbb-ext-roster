<?php

namespace numeric\roster\controller;

class roster_view
{
	/* @var \phpbb\controller\helper */
	protected $helper;

	/* @var \phpbb\auth\auth */
	protected $auth;

	/* @var \phpbb\config\config */
	protected $config;

	/* @var \phpbb\db\driver\driver_interface */
	protected $db;

	/* @var \phpbb\template\template */
	protected $template;

	/* @var \phpbb\user */
	protected $user;

	protected $request;

	protected $phpbb_container;

	/* @var phpEx */
	protected $phpEx;

	/* @var phpbb_root_path */
	protected $phpbb_root_path;

	protected $ranks = array();

	/**
	* Constructor
	*
	* @param \phpbb\config\config		$config
	* @param \phpbb\controller\helper	$helper
	* @param \phpbb\template\template	$template
	* @param \phpbb\user				$user
	*/
	public function __construct(\phpbb\controller\helper $helper, \phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\template\template $template, \phpbb\user $user, \phpbb\request\request $request, $phpbb_container)
	{
		$this->helper = $helper;
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->template = $template;
		$this->user = $user;
		$this->request = $request;
		$this->phpbb_container = $phpbb_container;

		$this->user->add_lang_ext('numeric/roster', 'roster');

		/**
		* Grab all raid instances
		*/
		$sql = 'SELECT r.roster_rank, f.rank_title
			FROM roster_ranks r, forum_ranks f
			WHERE f.rank_id = r.forum_rank';
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
			$this->ranks[$row['roster_rank']] = $row['rank_title'];
		$this->db->sql_freeresult($result);
	}

	public function main()
	{
		$this->template->assign_vars(array(
			'S_MENU_PAGE'		=> $this->user->lang['ROSTER'],
		));

		$this->template->assign_block_vars('navlinks', array(
			'FORUM_NAME'	=> $this->user->lang['ROSTER'],
		));

		return $this->helper->render('list.html', $this->user->lang['ROSTER']);
	}
}











function generate_wow_profile($roster_id)
{
	global $phpbb_root_path, $db, $config, $user, $template, $phpEx, $request;

	/**
	*	Pull player roster info and rank info
	**/
	$sql = 'SELECT rp.*, rps.*, fr.rank_title
		FROM roster_players rp, roster_player_builds rps, roster_ranks rk, forum_ranks fr
		WHERE  rp.roster_id = ' . (int) $roster_id  . '
			AND rps.build_id = rp.build_id
			AND rk.roster_rank = rp.rank
			AND fr.rank_id = rk.forum_rank';
	$result = $db->sql_query($sql);

	if (!($aryDbRosterData = $db->sql_fetchrow($result)))
	{
		trigger_error('No such member exists');
	}
	$db->sql_freeresult($result);

	$iActiveSpec	= $request->variable('spec', (int) $aryDbRosterData['active_build']);
	$iRosterId 	= (int) $aryDbRosterData['roster_id'];
	$outfit_1_id	= $request->variable('outfit_1', 0);
	$outfit_2_id	= $request->variable('outfit_2', 0);
	$iOutfitId 	= ($outfit_2_id) ? $outfit_2_id : $outfit_1_id;

	/**
	*
	* Model and equipment
	*
	**/
/*
	$aryEquipSlots = array('head', 'neck', 'shoulder', 'back', 'chest', 'shirt', 'tabard', 'wrist', 'hands', 'waist', 'legs', 'feet', 'finger1', 'finger2', 'trinket1', 'trinket2', 'mainHand', 'offHand');

	$aryModelParamFields = array(
		'ha' 			=> 'model_ha',
		'hc'			=> 'model_hc',
		'fa'			=> 'model_fa',
		'sk'			=> 'model_sk',
		'fc'			=> 'model_hc',
		'fh'			=> 'model_fh',
		'flag_helm'		=> 'flag_helm',
		'flag_cloak'	=> 'flag_cloak',
	);

	// Get all outfits for this user
	$sql = 'SELECT *
		FROM roster_player_outfits
		WHERE roster_id = ' . $iRosterId . '
		ORDER BY date DESC';
	$result = $db->sql_query($sql);

	$aryDbOutfits = $aryPaperdollModelParams = array();
	while ($row = $db->sql_fetchrow($result))
	{
		$aryDbOutfits[$row['outfit_id']]	= $row;
	}
	$db->sql_freeresult($result);

	if (sizeof($aryDbOutfits))
	{
		foreach ($aryDbOutfits as $id => $v)
		{
			// Set the character model features
			if (!sizeof($aryPaperdollModelParams) || $iOutfitId == $id)
			{
				$set_id = (!$iOutfitId) ? $id : $iOutfitId;

				foreach ($aryModelParamFields as $k => $v)
				{
					$aryPaperdollModelParams[$k] = $aryDbOutfits[$set_id][$v];
				}
				unset($set_id);
			}

/*			if (sizeof($aryDbOutfits) > 1)
			{
				$template->assign_block_vars('form_outfits_' . $v['build'], array(
					'OUTFIT_ID'		=> (int) $id,
	//				'OUTFIT_DATE'	=> (!empty($v['date'])) ? $user->format_date($v['date']) : '',

					'S_SELECTED'	=> ($id == $iOutfitId) ? true : false,
				));
			}
		}
	}

	unset($aryDbOutfits);

	// Build the displayed equipment information
	$aryPaperdollSlots = $sub_slot_ary = array();

	$sql_sub_select = ($iOutfitId) ? (int) $iOutfitId : '(SELECT MAX(outfit_id) FROM roster_player_outfits WHERE roster_id = ' . $iRosterId . ' AND build = ' . $iActiveSpec . ')';

	$sql = 'SELECT rpo.outfit_id, rpi.*, rwc.*
		FROM roster_player_outfits rpo, roster_player_items rpi, roster_player_items_cache rwc
		WHERE rpo.outfit_id = ' . $sql_sub_select . '
			AND rpi.outfit_id = rpo.outfit_id
			AND rwc.item_id = rpi.item_id
			AND rwc.bonusLists = rpi.bonusLists';
	$result = $db->sql_query($sql);

	while ($row = $db->sql_fetchrow($result))
	{
		$strSlot = $row['slot'];

		$aryPaperdollSlots[$strSlot]['item_id'] 	= $row['item_id'];
		$aryPaperdollSlots[$strSlot]['display_id'] 	= $row['display_id'];
		$aryPaperdollSlots[$strSlot]['inventory'] 	= $row['inventory'];

		if (!empty($row['quality']))
			$aryPaperdollSlots[$strSlot]['quality'] = $row['quality'];

		if (!empty($row['icon']))
			$aryPaperdollSlots[$strSlot]['icon'] 	= $row['icon'];

		if (!empty($row['set_item_ids']))
			$aryPaperdollSlots[$strSlot]['set_ids'] = explode(':', $row['set_item_ids']);

		// Produce the rel="" string for gems, enchants, random stat items, reforge
		$aryPaperdollSlots[$strSlot]['rel'] = array();

		if (!empty($row['suffix']))
			$aryPaperdollSlots[$strSlot]['rel'][] =  'rand=' . $row['suffix'];

		if (!empty($row['upgrade']))
			$aryPaperdollSlots[$strSlot]['rel'][] =  'upgd=' . $row['upgrade'];

		if (!empty($row['reforge']))
			$aryPaperdollSlots[$strSlot]['rel'][] =  'forg=' . $row['reforge'];

		if (!empty($row['enchant']))
			$aryPaperdollSlots[$strSlot]['rel'][] = 'ench=' . $row['enchant'];

		if (!empty($row['extrasocket']))
			$aryPaperdollSlots[$strSlot]['rel'][] = 'sock=' . $row['extrasocket'];

		$gems = array();

		for ($i = 0; $i < 3; $i++)
		{
			if (!empty($row['gem' . $i]))
			{
				$gems[] = $row['gem' . $i];
			}
		}

		if (sizeof($gems))
			$aryPaperdollSlots[$strSlot]['rel'][] = 'gems=' . implode(':', $gems);

		if ($row['bonusLists'] != '')
			$aryPaperdollSlots[$strSlot]['rel'][] =  'bonus=' . implode(':', explode(',', $row['bonusLists']));

	}
	$db->sql_freeresult($result);

	// Equipment display - paperdoll has 18 slots, 1-18, split into left (8), right (8) and center (2)
	foreach ($aryEquipSlots as $i => $strSlot)
	{
		// Where on doll are we?
		if ($i > 15)
		{
			$equip_loc = 'center';
		}
		else if ($i > 7)
		{
			$equip_loc = 'right';
		}
		else
		{
			$equip_loc = 'left';
		}

		if (isset($aryPaperdollSlots[$strSlot]))
		{
			$item_id = $aryPaperdollSlots[$strSlot]['item_id'];

			// Is this item a set piece? If so, are we wearing any other pieces
			$pcs = '';
			$pcs_ary = array();
			if (isset($aryPaperdollSlots[$strSlot]['set_ids']))
			{
				// Now loop through the ids of all set pieces for the item_id we
				// are looking at - if we match then the item in the $k slot
				// matches, add it to the list
				foreach ($aryPaperdollSlots[$strSlot]['set_ids'] as $item_set_id)
				{
					// Now we loop through all slots and compare the item_id in that
					// slot to each id listed as a potential set piece
					foreach ($aryPaperdollSlots as $k => $items_ary)
					{
						if ($items_ary['item_id'] == $item_set_id)
						{
							$pcs_ary[] = $item_set_id;
						}
					}
				}
				$aryPaperdollSlots[$strSlot]['rel'][] = 'pcs=' . implode(':', $pcs_ary);
				unset($pcs_ary);
			}

			$template->assign_block_vars('roster_equip_' . $equip_loc, array(
				'ITEM_ID'	=> $item_id,
				'REL'		=> implode('&', $aryPaperdollSlots[$strSlot]['rel']),
				'QUALITY'	=> ($aryPaperdollSlots[$strSlot]['quality']) ? 'q' . $aryPaperdollSlots[$strSlot]['quality'] : '',
				'IMG_ICON'	=> ($aryPaperdollSlots[$strSlot]['icon']) ? strtolower($aryPaperdollSlots[$strSlot]['icon']) . '.png' : 'inv_misc_questionmark.png',
			));

		}
		else
		{
			$template->assign_block_vars('roster_equip_' . $equip_loc, array(
				'ITEM_ID'	=> 0,
			));
		}
	}
*/
	/**
	*
	* Spec blocks
	*
	**/
	$t_talents_primary = $t_talents_secondary = '';
	$talent1_name = $talent2_name = $talent1_points = $talent2_points = '';

	if ($aryDbRosterData['spec_1'] && $aryDbRosterData['spec_1'] != 'None')
	{
		$talent1_name 		= $aryDbRosterData['spec_1'];

		$t_talents_primary .= ($aryDbRosterData['active_build'] == 1) ? 'active-' : 'inactive-';
		$t_talents_primary .= ($iActiveSpec == 1) ? 'selected' : 'unselected';
	}

	if ($aryDbRosterData['spec_2'] && $aryDbRosterData['spec_2'] != 'None')
	{
		$talent2_name 		= $aryDbRosterData['spec_2'];

		$t_talents_secondary .= ($aryDbRosterData['active_build'] == 2) ? 'active-' : 'inactive-';
		$t_talents_secondary .= ($iActiveSpec == 2) ? 'selected' : 'unselected';
	}

	$template->assign_vars(array(
		'TALENT1_NAME'		=> $talent1_name,
		'TALENT1_POINTS'	=> $talent1_points,
		'TALENT1_ICON'		=> (!empty($aryDbRosterData['spec_icon_1'])) ? $aryDbRosterData['spec_icon_1'] : '',
		'TALENT2_NAME'		=> $talent2_name,
		'TALENT2_POINTS'	=> $talent2_points,
		'TALENT2_ICON'		=> (!empty($aryDbRosterData['spec_icon_2'])) ? $aryDbRosterData['spec_icon_2'] : '',
		'CLASS_CLEAN'		=> $aryDbRosterData['class_clean'],

		'U_TALENTS1_URL'	=> append_sid('/roster/character/' . urlencode($aryDbRosterData['name']), 'spec=1'),
		'U_TALENTS2_URL'	=> append_sid('/roster/character/' . urlencode($aryDbRosterData['name']), 'spec=2'),

		'T_TALENT1_BTN_POS'	=> $t_talents_primary,
		'T_TALENT2_BTN_POS'	=> $t_talents_secondary,
	));

	/**
		Stats
	**/
	$strActiveStat 	= '';
	$aryDbStats 	= array();

	$sql = 'SELECT rmsp.value, rs.*
		FROM roster_map_stat_player rmsp, roster_stats rs
		WHERE rmsp.roster_id = ' . (int) $iRosterId . '
			AND rmsp.stat_id = rs.stat_id
		ORDER BY rs.type, rs.order ASC';
	$result = $db->sql_query($sql);

	while ($row = $db->sql_fetchrow($result))
	{
		$aryDbStats[$row['type']][$row['name']] = $row;
	}
	$db->sql_freeresult($result);

	// Dump out base stats - this is a single block of data, always shown
	// hasteRating is a special case so we store that for later use
	foreach ($aryDbStats as $type => $type_ary)
	{
		switch ($type)
		{
			// Attributes is kinda special
			case 'attributes':
				foreach ($type_ary as $name => $row)
				{
					if (!$row['display'])
					{
						continue;
					}

					$template->assign_block_vars('base_stats', array(
						'TITLE'	=> $row['title'],
						'VALUE'	=> round($row['value'], 2),
					));
				}
			break;

			// Secondary stats
			default:

				$strActiveStat = '';
				if (empty($aryDbRosterData['spec_clean_' . $iActiveSpec]) && wow_primary_stats($aryDbRosterData['class_clean'], 'default') == $type)
				{
					$strActiveStat = $type;
				}
				elseif (wow_primary_stats($aryDbRosterData['class_clean'], $aryDbRosterData['spec_clean_' . $iActiveSpec]) == $type)
				{
					$strActiveStat = $type;
				}

				$template->assign_block_vars('other_stats_header', array(
					'STAT_NAME_CAP'	=> ucfirst($type),
					'STAT_NAME'		=> $type,
					'S_VISIBLE'		=> ($strActiveStat == $type) ? 'block' : 'none',
				));

				$template->assign_block_vars('other_stats_outer', array(
					'STAT_NAME'		=> $type,
					'S_VISIBLE'		=> ($strActiveStat == $type) ? 'block' : 'none',
				));

				// Store some data
				$mhDps = $ofDps = $ohExp = $ohSpd = $mhSpd = $mhExp = $minDmg = $maxDmg = 0;
				foreach ($type_ary as $name => $row)
				{
					$is_percent = (!empty($row['percent'])) ? ' %' : '';
					$title = $value = '';

					// Handle special cases
					switch ($name)
					{
						case 'mainHandDps':
							$mhDps = ($row['value'] < 0) ? '--' : round($row['value'], 1);
						break;
						case 'offHandDps':
							$title = 'DPS';
							$value = $mhDps . ((isset($aryPaperdollSlots['offHand']) && $aryPaperdollSlots['offHand']['inventory'] != 14 && $aryPaperdollSlots['mainHand']['inventory'] != 17 && $row['value'] > 0) ? '/' . round($row['value'], 1) : '');
						break;
						case 'mainHandDmgMin':
							$minDmg = ($row['value'] < 0) ? '--' : round($row['value'], 0);
						break;
						case 'mainHandDmgMax':
							$title = 'Damage';
							$value = $minDmg . (($row['value'] > 0) ? ' - ' . round($row['value'], 0) : '');
						break;
						case 'mainHandSpeed':
							$mhSpd = ($row['value'] < 0) ? '--' : round($row['value'], 2);
						break;
						case 'offHandSpeed':
							$title = 'Speed';
							$value = $mhSpd . ((isset($aryPaperdollSlots['offHand']) && $aryPaperdollSlots['offHand']['inventory'] != 14 && $aryPaperdollSlots['mainHand']['inventory'] != 17) ? '/' . round($row['value'], 2) : ''); // Ignore shield off-hands
						break;
						default:
							if (!$row['display'])
							{
								continue;
							}

							$title = $row['title'];
							$value = ($row['value'] < 0) ? '--' : round($row['value'], ($is_percent) ? 2 : 1) . $is_percent;
						break;
					}

					if ($title)
					{
						$template->assign_block_vars('other_stats_outer.secondary_stats', array(
							'TITLE'	=> $title,
							'VALUE'	=> $value
						));
					}
				}
			break;
		}
	}
	unset($aryDbStats);


	/**
	*
	*
	*
	**/
	$talents_at_levels_ary = array(15, 30, 45, 60, 75, 90, 100);
	for ($i = 1; $i < MAX_TALENT_TIERS + 1; $i++)
	{
		$template->assign_block_vars('talent_list', array(
			'TALENT_ID'		=> $aryDbRosterData['talent_id_' . $i . '_' . $iActiveSpec],
			'TALENT_NAME'	=> ($aryDbRosterData['talent_name_' . $i . '_' . $iActiveSpec]) ? $aryDbRosterData['talent_name_' . $i . '_' . $iActiveSpec] : (($aryDbRosterData['level'] > $talents_at_levels_ary[$i - 1]) ? 'Empty' : ''),
			'TALENT_ICON'	=> $aryDbRosterData['talent_icon_' . $i . '_' . $iActiveSpec],
			'TALENT_LEVEL'	=> ($aryDbRosterData['level'] < $talents_at_levels_ary[$i - 1] && !$aryDbRosterData['talent_id_' . $i . '_' . $iActiveSpec]) ? $talents_at_levels_ary[$i - 1] : '',
		));
	}

	/**
	* Feed
	**/
	$start = 0;
	$sql = 'SELECT *
		FROM roster_player_feeds
		WHERE roster_id = ' . (int) $aryDbRosterData['roster_id'] . '
		ORDER BY timestamp DESC
		LIMIT ' . $start . ', 45';
	$result = $db->sql_query($sql);

	while ($row = $db->sql_fetchrow($result))
	{
		$type = $row['type'];
		$timestamp = $row['timestamp']; // msecs

		$feed_text = $feed_icon = $feed_icon_id = $feed_icon_type = '';

		// ACHIEVEMENT 	achieve_id 	featofstrength	achieve_name 	achieve_icon 	points
		// BOSS			boss_title 	boss_icon 		kills
		// LOOT			item_id		item_name		item_icon
		// CRITERIA		achieve_id	achieve_name	achieve_icon	crieria
		// RAID			raid_id		sign_status		select_status
		switch ($type)
		{
			case 'ACHIEVEMENT':
				// Feat of Strength?
				if (!empty($row['field2']))
				{
					$feed_text = sprintf('Earned the Feat of Strength <strong><a href="//www.wowhead.com/achievement=%s">%s</a></strong>', $row['field1'], $row['field3']);
				}
				else
				{
					$feed_text = sprintf('Earned the achievement <strong><a href="//www.wowhead.com/achievement=%s">%s</a></strong> for %d points', $row['field1'], $row['field3'], $row['field5']);
				}

				$feed_icon = '/styles/aquila/theme/images/icons/18/' . $row['field4']. '.png';
				$feed_icon_type = 'ACHIEVEMENT';
			break;

			case 'CRITERIA':
				$feed_text = sprintf('Completed step <em>%s</em> of achievement <a href="//www.wowhead.com/achievement=%s">%s</a>', $row['field4'], $row['field1'], $row['field2']);
				$feed_icon = '';
				$feed_icon_type = 'CRITERIA';
			break;

			case 'BOSSKILL':
				$feed_text = sprintf('%d <strong>%s</strong> ', $row['field4'], $row['field2']);
				$feed_icon = '';
				$feed_icon_type = 'BOSSKILL';
			break;

			case 'LOOT':
				$bonusList = ($row['field6']) ? '&bonus=' . implode(':', explode(',', $row['field6'])) : '';
				$feed_text = sprintf('Obtained <strong><a class="q%d" href="http://www.wowhead.com/item=%d%s">%s</a></strong>', $row['field4'], $row['field1'], $bonusList, $row['field2']);
				$feed_icon = '/styles/aquila/theme/images/icons/18/' . $row['field3'] . '.png';
				$feed_icon_id = $row['field1'];
				$feed_icon_type = 'LOOT';
			break;

			case 'RAID':
			break;
		}

		$template->assign_block_vars('feed_list', array(
			'FEED_TIME'		=> $user->format_date($timestamp),
			'FEED_ICON'		=> $feed_icon,
			'FEED_ICON_ID'	=> $feed_icon_id,
			'FEED_ICON_TYPE'=> $feed_icon_type,
			'FEED_TEXT'		=> $feed_text,
		));
	}


	$template->assign_block_vars('navlinks', array(
		'FORUM_NAME'	=> 'Roster',
		'U_VIEW_FORUM'	=> '/roster/',
	));
	$template->assign_block_vars('navlinks', array(
		'FORUM_NAME'	=> $aryDbRosterData['name'],
		'U_VIEW_FORUM'	=> '/roster/character/' . urlencode($aryDbRosterData['name']),
	));

	$template->assign_vars(array(
		'META'				=> '<link rel="canonical" href="www.aquilaguild.com/roster/character/' . urlencode($aryDbRosterData['name']) . '" />',
		'PORTRAIT_IMG'		=> '//eu.battle.net/static-render/eu/'. $aryDbRosterData['thumbnail'],

		'CHAR_NAME'			=> $aryDbRosterData['name'],
		'CHAR_SUFFIX'		=> $aryDbRosterData['suffix'],
		'CHAR_PREFIX'		=> $aryDbRosterData['prefix'],
		'CHAR_LEVEL'		=> $aryDbRosterData['level'],
		'CHAR_CLASS'		=> $aryDbRosterData['class'],
		'CHAR_RACE'			=> $aryDbRosterData['race'],
		'CHAR_RANK'			=> $aryDbRosterData['rank_title'],
		'LAST_MODIFIED'		=> $user->format_date($aryDbRosterData['modified']),
		'ROSTER_BG_CLASS'	=> 'roster-bg-' . str_replace(' ', '', $aryDbRosterData['class_clean']),

		'U_CHAR_ARMORY'		=> '//eu.battle.net/wow/en/character/' . $aryDbRosterData['realm'] . '/' . strtolower(urlencode($aryDbRosterData['name'])) . '/simple',

		'S_ROSTER_ACTION'	=> append_sid('/roster/character/' . urlencode($aryDbRosterData['name'])),
		'S_MENU_PAGE'		=> 'roster', 	// Sets which menu item to hilight on header
		'S_SHOW_WOW_PROFILE'=> true,
	));
}
