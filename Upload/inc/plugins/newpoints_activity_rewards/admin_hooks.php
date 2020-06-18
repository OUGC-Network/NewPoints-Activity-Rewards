<?php

/***************************************************************************
 *
 *	Newpoints Activity Rewards plugin (/inc/plugins/newpoints/newpoints_activity_rewards/admin_hooks.php)
 *	Author: Omar Gonzalez
 *	Copyright: © 2020 Omar Gonzalez
 *
 *	Website: https://ougc.network
 *
 *	Allow users to request points rewards in exchange of activity.
 *
 ***************************************************************************
 
****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

namespace NewpointsActivityRewards\AdminHooks;

function admin_load()
{
	global $modules_dir, $run_module, $action_file, $page;

	if($run_module == 'newpoints' && $page->active_action == 'activity_rewards')
	{
		$modules_dir = NEWPOINTS_ACTIVITY_REWARDS_ROOT;
		$run_module = 'admin';
		$action_file = 'module.php';
	}
}

function newpoints_admin_newpoints_menu(&$menu)
{
	global $lang;

	if(!\NewpointsActivityRewards\Admin\_is_installed())
	{
		return;
	}

	\NewpointsActivityRewards\Core\load_language();

	$menu[] = [
		'id' => 'activity_rewards',
		'title' => $lang->newpoints_activity_rewards_admin,
		'link' => 'index.php?module=newpoints-activity_rewards'
	];
}

function admin_newpoints_action_handler(&$handlers)
{
	$handlers['activity_rewards'] = [
		'active' => 'activity_rewards',
		'file' => MYBB_ROOT.'module.php'
	];
}

function admin_user_permissions(&$permissions)
{
	/*global $lang;

	\NewpointsActivityRewards\Core\load_language();

	$permissions['activity_rewards'] =  $lang->newpoints_activity_rewards_permission;*/
}

function admin_tools_cache_start()
{
	global $cache;

	/*\NewpointsActivityRewards\Core\control_object($cache, '
		function update_newpoints_activity_rewards()
		{
			//\NewpointsActivityRewards\Core\update_cache();
		}
	');*/
}

function admin_tools_cache_rebuild()
{
	\NewpointsActivityRewards\AdminHooks\admin_tools_cache_start();
}