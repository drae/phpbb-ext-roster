<?php

namespace numeric\roster\controller;

class roster_list
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

	protected $sort_key_sql	= array('a' => 'clean', 'b' => 'rank', 'c' => 'class_clean', 'd' => 'race_clean', 'e' => 'spec_1', 'n' => 'level', 'o' => 'rp.itemlvl_equip');

	protected $levels_ary = array(1 => '%1$s >= 100', 2 => '%1$s >= 90 AND %1%s < 100', 3 => '%1$s >= 86 AND %1$s < 90', 4 => '%1$s >= 80 AND %1$s < 86', 5 => '%1$s >=70 AND %1$s < 80', 6 => '%1$s < 70');
	protected $levels_text_ary 	= array(1 => 'Level 110', 2 => 'Level 100-109', 3 => 'Level 90-99', 4 => 'Level 86-89', 5 => 'Level 80-85', 6 => 'Level 70-79', 7 => 'Level 60-69', 8 => 'Level 1-60');

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
		$start = $this->request->variable('start', 0);

		$default_sort_key = 'b';
		$default_sort_dir = 'a';
		$sort_key = $this->request->variable('sk', $default_sort_key);
		$sort_dir = $this->request->variable('sd', $default_sort_dir);

		$filter_options = $this->request->variable('filter_options', '');
		$filter_class = $this->request->variable('filter_class', 0);
		$filter_level = $this->request->variable('filter_level', 0);
		$filter_rank = $this->request->variable('filter_rank', 0);

		$roster_filters = array();
		$roster_classes = array();

		$pagination_base_url = array('sk=' . $sort_key . '&amp;sd=' . $sort_dir, );

		/**
			Generate filter SQL
		**/
		$where_sql = $from_sql = $order_by_sql = '';

		if ($filter_options != '' && $filter_options != 'none')
		{
			$where_ary = spec_filter($roster_filters, $filter_options);

			foreach (array('other', 'class', 'build', 'spec') as $where)
			{
				if (isset($where_ary[$where]))
				{
					$where_sql .= ($where_sql ? ' OR ' : '') . $where_ary[$where];
				}
			}

			if (isset($where_ary['ignore']))
			{
				$where_sql = '((' . $where_sql . ') AND NOT (' . $where_ary['ignore'] . '))';
			}

			$where_sql = '(' . $where_sql . ')';
		}

		if ($filter_class)
		{
			if (isset($roster_classes[$filter_class - 1]))
			{
				$where_sql .= ($where_sql ? ' AND ' : '') . ' rp.class_clean = \'' . $roster_classes[$filter_class - 1] . '\'';
			}
		}

		if ($filter_level)
		{
			if ($filter_level <= sizeof($this->levels_ary))
			{
				$where_sql .= ($where_sql ? ' AND ' : '') . ('(' . sprintf($this->levels_ary[$filter_level], 'rp.level') . ')');
			}
		}

		if ($filter_rank)
		{
			$tmp_in = '';
			$tmp_ary = array();
			switch ($filter_rank)
			{
				case -1:
					$sql = 'SELECT roster_rank
						FROM roster_ranks
						WHERE officer = 1';
					$result = $this->db->sql_query($sql);

					while ($row = $this->db->sql_fetchrow($result))
					{
						$tmp_ary[] = (int) $row['roster_rank'];
					}
					$this->db->sql_freeresult($result);

					$tmp_in = 'rp.rank IN (' . implode(', ', $tmp_ary) . ')';
					break;

				case -2:
					$sql = 'SELECT roster_rank
						FROM roster_ranks
						WHERE raider = 1';
					$result = $this->db->sql_query($sql);

					while ($row = $this->db->sql_fetchrow($result))
					{
						$tmp_ary[] = (int) $row['roster_rank'];
					}
					$this->db->sql_freeresult($result);

					$tmp_in = 'rp.rank IN (' . implode(', ', $tmp_ary) . ')';
					break;

				case -3:
					$sql = 'SELECT roster_rank
						FROM roster_ranks
						WHERE alt = 1';
					$result = $this->db->sql_query($sql);

					while ($row = $this->db->sql_fetchrow($result))
					{
						$tmp_ary[] = (int) $row['roster_rank'];
					}
					$this->db->sql_freeresult($result);

					$tmp_in = 'rp.rank IN (' . implode(', ', $tmp_ary) . ')';
					break;

				default:
					$tmp_in = 'rp.rank = ' . (int) ($filter_rank - 1);

			}
			$where_sql .= ($where_sql ? ' AND ' : '') . ('(' . $tmp_in . ')');
		}

		// Total number of peeps in roster (all levels)
		$sql = "SELECT COUNT(*) AS total_players
			FROM (roster_players rp$from_sql)" .
				($where_sql ? " WHERE $where_sql " : '');
		$result = $this->db->sql_query($sql);

		$total_players = $this->db->sql_fetchfield('total_players', $result);
		$this->db->sql_freeresult($result);

		/**
			Grab the appropriate data
		**/
		$order_by_sql .= $this->sort_key_sql[$sort_key] . ' ' . (($sort_dir == 'a') ? 'ASC' : 'DESC') . (($sort_key != 'a') ? ', rp.clean ASC' : '');

		$sql = "SELECT *
			FROM (roster_players rp
				$from_sql)
				" .
				($where_sql ? " WHERE $where_sql " : '') .
				'ORDER BY ' . $order_by_sql;
		$result = $this->db->sql_query_limit($sql, $this->config['players_per_page'], $start);

		while ($row = $this->db->sql_fetchrow($result))
		{
//			$specialisation = (!empty($row['spec_clean_' . $row['active_build']])) ? wow_talent_trees($row['class_clean'], $row['spec_clean_' . $row['active_build']]) : '';

			$this->template->assign_block_vars('roster', array(
				'RID'				=> $row['roster_id'],
				'NAME'				=> $row['name'],
				'NAME_FULL'			=> ($row['user_id']) ? get_username_string('full', $row['user_id'], $row['name'], $row['color']) : '',
				'RANK'				=> $this->ranks[$row['rank']],
				'CLASS'				=> $row['class'],
				'CLASS_CSS'			=> $row['class_clean'],
				'RACE'				=> $row['race'],
				'LEVEL'				=> $row['level'],
				'SPEC'				=> $row['active_build'],
				'ILVL'				=> $row['itemlvl_equip'],
				'PROF_1'			=> $row['prof_1'],
				'PROF_1_SKILL'		=> $row['prof_1_skill'],
				'PROF_2'			=> $row['prof_2'],
				'PROF_2_SKILL'		=> $row['prof_2_skill'],

				'ACHIEVE_POINTS' 	=> $row['achieve_points'],
				'ACHIEVE_EARNED' 	=> $row['achieve_earned'],

				'U_PROFILE'			=> '//eu.battle.net/wow/en/character/' . $row['realm'] . '/' . $row['name'] . '/',
				'U_ARMORY'			=> '//eu.battle.net/wow/en/character/' . $row['realm'] . '/' . $row['name'] . '/',
			));
		}
		$this->db->sql_freeresult($result);

		/**
			Filters
		**/
		foreach ($roster_filters as $category => $option_ary)
		{
			$this->template->assign_block_vars('filter_groups', array(
				'OPTION'	=> ucfirst(str_replace('_', ' ', $category)),
			));

			foreach ($option_ary as $filter => $class_ary)
			{
				$this->template->assign_block_vars('filter_groups.filter_options', array(
					'VALUE'		=> $filter,
					'OPTION'	=> ucfirst(str_replace('_', ' ', $filter)),

					'S_SELECTED'	=> ($filter_options == $filter) ? true : false,
				));

				if ($filter_options == $filter)
				{
					$pagination_base_url[] = 'filter_options=' . $filter;
				}
			}
		}

		foreach ($roster_classes as $k => $class)
		{
			$this->template->assign_block_vars('filter_class', array(
				'OPTION'	=> $user->lang['wowclasstoclass'][$class],
				'VALUE'		=> $k + 1,

				'S_SELECTED'	=> ($filter_class === $k + 1) ? true : false,
			));

			if ($filter_class === $k + 1)
			{
				$pagination_base_url[] = 'filter_class=' . $filter_class;
			}
		}

		foreach ($this->levels_text_ary as $k => $level)
		{
			$this->template->assign_block_vars('filter_level', array(
				'OPTION'	=> $level,
				'VALUE'		=> $k,

				'S_SELECTED'	=> ($filter_level === $k) ? true : false,
			));
		}

		foreach ((array(-2 => 'All Raiders', -1 => 'All Officers', -3 => 'All Alts') + $this->ranks) as $rank => $title)
		{
			$this->template->assign_block_vars('filter_rank', array(
				'OPTION'	=> $title,
				'VALUE'		=> ($rank >= 0) ? $rank + 1 : $rank,

				'S_SELECTED'	=> (($rank >= 0 && $filter_rank == $rank + 1) || ($rank < 0 && $filter_rank == $rank)) ? true : false,
			));

			if (($rank >= 0 && $filter_rank == $rank + 1) || ($rank < 0 && $filter_rank == $rank))
			{
				$pagination_base_url[] = 'filter_rank=' . $filter_rank;
			}
		}

		$pagination_url = append_sid('/roster/', implode('&amp;', $pagination_base_url));

		$pagination = $this->phpbb_container->get('pagination');
		$pagination->generate_template_pagination($pagination_url, 'pagination', 'start', $total_players, $this->config['players_per_page'], $start);

		$this->template->assign_vars(array(
			'TOTAL_PLAYERS'	=> ($total_players == 1) ? '1 Member' : sprintf('%d Members', $total_players),
			'ROSTER_UPDATE'	=> $this->user->format_date($this->config['last_roster_update']),

			'U_ROSTER'			=> append_sid('/roster/'),

			'U_SORT_NAME'	=> append_sid('/roster/', 'sk=a&amp;sd=' . (($sort_key == 'a' && $sort_dir == 'a') ? 'd' : 'a')),
			'U_SORT_CLASS'	=> append_sid('/roster/', 'sk=c&amp;sd=' . (($sort_key == 'c' && $sort_dir == 'a') ? 'd' : 'a')),
			'U_SORT_RACE'	=> append_sid('/roster/', 'sk=d&amp;sd=' . (($sort_key == 'd' && $sort_dir == 'a') ? 'd' : 'a')),
			'U_SORT_BUILD'	=> append_sid('/roster/', 'sk=e&amp;sd=' . (($sort_key == 'e' && $sort_dir == 'a') ? 'd' : 'a')),
			'U_SORT_RANK'	=> append_sid('/roster/', 'sk=b&amp;sd=' . (($sort_key == 'b' && $sort_dir == 'a') ? 'd' : 'a')),
			'U_SORT_LEVEL'	=> append_sid('/roster/', 'sk=n&amp;sd=' . (($sort_key == 'n' && $sort_dir == 'd') ? 'a' : 'd')),
			'U_SORT_ILVL'	=> append_sid('/roster/', 'sk=o&amp;sd=' . (($sort_key == 'o' && $sort_dir == 'd') ? 'a' : 'd')),

			'S_MENU_PAGE'			=> 'roster', 	// Sets which menu item to hilight on header
			'S_SHOW_GOOD_PROF'		=> 600, 		// Display fully opague profs above this level
			'S_MAX_PROF'			=> 700,
			'S_DISPLAY_FILTERS'		=> $display_filters,

			'S_FORM'				=> append_sid("/roster/", "mode=$mode"),
		));


		$this->template->assign_vars(array(
			'S_MENU_PAGE'		=> $this->user->lang['ROSTER'],
		));

		$this->template->assign_block_vars('navlinks', array(
			'FORUM_NAME'	=> $this->user->lang['ROSTER'],
		));

		return $this->helper->render('list.html', $this->user->lang['ROSTER']);
	}
}
