<?php

/***************************************************************************
 *
 *    NewPoints Activity Rewards plugin (/inc/plugins/ActivityRewards/hooks/admin.php)
 *    Author: Omar Gonzalez
 *    Copyright: © 2020 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Allow users to request points rewards in exchange for activity.
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

declare(strict_types=1);

namespace Newpoints\ActivityRewards\Hooks\Admin;

use function Newpoints\Core\language_load;

use const Newpoints\ActivityRewards\ROOT;

function newpoints_settings_rebuild_start(array &$hook_arguments): array
{
    language_load('activity_rewards');

    $hook_arguments['settings_directories'][] = ROOT . '/settings';

    return $hook_arguments;
}

function newpoints_templates_rebuild_start(array $hook_arguments): array
{
    $hook_arguments['templates_directories']['activity_rewards'] = ROOT . '/templates';

    return $hook_arguments;
}

function newpoints_admin_settings_intermediate(array &$hook_arguments): array
{
    language_load('activity_rewards');

    $hook_arguments['activity_rewards'] = [];

    return $hook_arguments;
}

function newpoints_admin_settings_commit_start(array &$setting_groups_objects): array
{
    return newpoints_admin_settings_intermediate($setting_groups_objects);
}

function newpoints_admin_load(): bool
{
    global $run_module;
    global $page;

    if ($run_module !== 'newpoints' || $page->active_action !== 'activity_rewards') {
        return false;
    }

    require_once ROOT . '/admin/packages.php';

    return true;
}

function newpoints_admin_menu(array &$sub_menu): array
{
    global $lang;

    language_load('activity_rewards');

    $sub_menu[] = [
        'id' => 'activity_rewards',
        'title' => $lang->newpoints_activity_rewards_admin_menu,
        'link' => 'index.php?module=newpoints-activity_rewards'
    ];

    return $sub_menu;
}

function newpoints_admin_newpoints_action_handler(array &$actions): array
{
    $actions['activity_rewards'] = ['active' => 'activity_rewards', 'file' => 'packages.php'];

    return $actions;
}

function newpoints_admin_permissions(&$permissions)
{
    global $lang;

    language_load('activity_rewards');

    $permissions['activity_rewards'] = $lang->newpoints_activity_rewards_admin_permission;
}