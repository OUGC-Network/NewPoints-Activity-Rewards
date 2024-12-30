<?php

/***************************************************************************
 *
 *    NewPoints Activity Rewards plugin (/inc/plugins/newpoints/plugins/ActivityRewards/core.php)
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

namespace Newpoints\ActivityRewards\Core;

use const Newpoints\ActivityRewards\ROOT;

const ACTIVITY_REWARDS_TYPE_THREADS = 1;

const ACTIVITY_REWARDS_TYPE_POSTS = 2;

const ACTIVITY_REWARDS_TYPE_REPUTATION = 3;

const ACTIVITY_REWARDS_FORUM_TYPE_ANY = 1;

const ACTIVITY_REWARDS_FORUM_TYPE_ALL = 2;

function templates_get(string $template_name = '', bool $enable_html_comments = true): string
{
    return \Newpoints\Core\templates_get($template_name, $enable_html_comments, ROOT, 'activity_rewards_');
}

function cache_update(): array
{
    global $mybb, $db;

    $query = $db->simple_select(
        'newpoints_activity_rewards_packages',
        'pid, title, description, type, amount, points, allowed_groups, forums, forums_type, forums_type_amount, hours',
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
            'allowed_groups' => $package_data['allowed_groups'],
            'forums' => $package_data['forums'],
            'forums_type' => (int)$package_data['forums_type'],
            'forums_type_amount' => (int)$package_data['forums_type_amount'],
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

function get_user_activity_amount(int $package_id): int
{
    global $db, $mybb;

    $packages_cache = cache_get();

    $package_data = &$packages_cache[$package_id];

    $interval = TIME_NOW - (60 * 60 * $package_data['hours']);

    $current_user_id = (int)$mybb->user['uid'];

    $user_amount = 0;

    $where_clauses = $forums_ids = [];

    $forum_type = ACTIVITY_REWARDS_FORUM_TYPE_ANY;

    if (!empty($package_data['forums'])) {
        $forums_ids = array_map('intval', explode(',', $package_data['forums']));

        if (!empty($forums_ids)) {
            $forums_ids_imploded = implode("','", $forums_ids);

            $where_clauses[] = "p.fid IN ('{$forums_ids_imploded}')";

            $forum_type = (int)$package_data['forums_type'];
        }
    }

    switch ($package_data['type']) {
        case ACTIVITY_REWARDS_TYPE_THREADS:
            $where_clauses[] = "t.uid='{$current_user_id}'";

            $where_clauses[] = "t.dateline>'{$interval}'";

            $where_clauses[] = "t.visible='1'";

            $query_options = [];

            $query_fields = ['COUNT(t.tid) as total_threads'];

            if ($forum_type === ACTIVITY_REWARDS_FORUM_TYPE_ALL) {
                $query_options['group_by'] = 't.fid';

                $query_fields[] = 't.fid';
            }

            $query = $db->simple_select(
                "threads t LEFT JOIN {$db->table_prefix}posts p ON(p.pid=t.firstpost)",
                implode(', ', $query_fields),
                implode(' AND ', $where_clauses),
                $query_options
            );

            if ($forum_type === ACTIVITY_REWARDS_FORUM_TYPE_ALL) {
                $forum_posts = [];

                while ($post_data = $db->fetch_array($query)) {
                    if ($post_data['total_threads'] < $package_data['forums_type_amount']) {
                        continue;
                    }

                    $forum_posts[(int)$post_data['fid']] = (int)$post_data['total_threads'];
                }

                if (count(array_intersect($forums_ids, array_keys($forum_posts))) === count($forums_ids)) {
                    $user_amount = array_sum($forum_posts);
                }
            } elseif ($forum_type === ACTIVITY_REWARDS_FORUM_TYPE_ANY) {
                $user_amount = (int)$db->fetch_field($query, 'total_threads');
            }

            break;
        case ACTIVITY_REWARDS_TYPE_POSTS:

            $where_clauses[] = "p.uid='{$current_user_id}'";

            $where_clauses[] = "p.dateline>'{$interval}'";

            $where_clauses[] = "p.visible='1'";

            $where_clauses[] = "t.visible='1'";

            $query_options = [];

            $query_fields = ['COUNT(p.pid) as total_posts'];

            if ($forum_type === ACTIVITY_REWARDS_FORUM_TYPE_ALL) {
                $query_options['group_by'] = 'p.fid';

                $query_fields[] = 'p.fid';
            }

            $query = $db->simple_select(
                "posts p LEFT JOIN {$db->table_prefix}threads t ON(t.tid=p.tid)",
                implode(', ', $query_fields),
                implode(' AND ', $where_clauses),
                $query_options
            );

            if ($forum_type === ACTIVITY_REWARDS_FORUM_TYPE_ALL) {
                $forum_posts = [];

                while ($post_data = $db->fetch_array($query)) {
                    if ($post_data['total_posts'] < $package_data['forums_type_amount']) {
                        continue;
                    }

                    $forum_posts[(int)$post_data['fid']] = (int)$post_data['total_posts'];
                }

                if (count(array_intersect($forums_ids, array_keys($forum_posts))) === count($forums_ids)) {
                    $user_amount = array_sum($forum_posts);
                }
            } elseif ($forum_type === ACTIVITY_REWARDS_FORUM_TYPE_ANY) {
                $user_amount = (int)$db->fetch_field($query, 'total_posts');
            }

            break;
        case ACTIVITY_REWARDS_TYPE_REPUTATION:
            $where_clauses[] = "r.uid='{$current_user_id}'";

            $where_clauses[] = "r.dateline>'{$interval}'";

            $query = $db->simple_select(
                "reputation r LEFT JOIN {$db->table_prefix}posts p ON(p.pid=r.pid)",
                'SUM(r.reputation) as total_reputation',
                implode(' AND ', $where_clauses)
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