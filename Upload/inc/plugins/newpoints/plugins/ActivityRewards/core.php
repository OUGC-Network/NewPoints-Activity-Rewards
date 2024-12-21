<?php

/***************************************************************************
 *
 *    OUGC Points Activity Rewards plugin (/inc/plugins/newpoints/plugins/ActivityRewards/core.php)
 *    Author: Omar Gonzalez
 *    Copyright: © 2020 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Allow users to request points rewards in exchange of activity.
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

namespace Newpoints\ActivityRewards\Core;

use const Newpoints\ActivityRewards\ROOT;

const ACTIVITY_REWARDS_TYPE_THREADS = 1;

const ACTIVITY_REWARDS_TYPE_POSTS = 2;

const ACTIVITY_REWARDS_TYPE_REPUTATION = 3;

function templates_get(string $template_name = '', bool $enable_html_comments = true): string
{
    return \Newpoints\Core\templates_get($template_name, $enable_html_comments, ROOT, 'activity_rewards_');
}

function cache_update(): array
{
    global $mybb, $db;

    $query = $db->simple_select(
        'newpoints_activity_rewards_packages',
        'pid, title, description, type, amount, points, groups, hours',
        "active='1' AND points>'0'"
    );

    $package_objects = [];

    while ($package_data = $db->fetch_array($query)) {
        $package_objects[(int)$package_data['pid']] = [
            'title' => $package_data['title'],
            'description' => $package_data['description'],
            'type' => (int)$package_data['type'],
            'amount' => (int)$package_data['amount'],
            'points' => (float)$package_data['points'],
            'groups' => $package_data['groups'],
            'hours' => (int)$package_data['hours']
        ];
    }

    $mybb->cache->update('newpoints_activity_rewards_packages', $package_objects);

    return $package_objects;
}

function cache_get(): array
{
    global $mybb;

    $packages_cache = (array)$mybb->cache->read('newpoints_activity_rewards_packages');

    if (empty($packages_cache)) {
        $packages_cache = cache_update();
    }

    return $packages_cache;
}

function get_activity_count(int $package_id): int
{
    global $db, $mybb;

    $packages_cache = cache_get();

    $package_data = &$packages_cache[$package_id];

    $interval = TIME_NOW - (60 * 60 * $package_data['hours']);

    $current_user_id = (int)$mybb->user['uid'];

    $user_amount = 0;

    switch ($package_data['type']) {
        case ACTIVITY_REWARDS_TYPE_POSTS:
            $query = $db->simple_select(
                'posts p LEFT JOIN ' . $db->table_prefix . 'threads t ON(p.tid=t.tid)',
                'COUNT(p.pid) as total_posts',
                "p.uid='{$current_user_id}' AND p.dateline>'{$interval}' AND p.visible='1' AND t.visible='1'"
            );
            $user_amount = (int)$db->fetch_field($query, 'total_posts');

            break;
        case ACTIVITY_REWARDS_TYPE_THREADS:
            $query = $db->simple_select(
                'threads',
                'COUNT(tid) as total_threads',
                "uid='{$current_user_id}' AND dateline>'{$interval}' AND visible='1'"
            );

            $user_amount = (int)$db->fetch_field($query, 'total_threads');
            break;
        case ACTIVITY_REWARDS_TYPE_REPUTATION:
            $query = $db->simple_select(
                'reputation',
                'SUM(reputation) as total_reputation',
                "uid='{$current_user_id}' AND dateline>'{$interval}'"
            );

            $user_amount = (int)$db->fetch_field($query, 'total_reputation');
            break;
    }

    return $user_amount;
}

function package_delete(int $package_id): bool
{
    global $db;

    $db->delete_query('newpoints_activity_rewards_packages', "pid='{$package_id}'");

    $db->delete_query('newpoints_activity_rewards_logs', "pid='{$package_id}'");

    return true;
}