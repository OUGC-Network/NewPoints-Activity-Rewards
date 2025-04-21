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

use function Newpoints\Core\alert_send;

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

function get_user_activity_amount(int $package_id, int $user_id): int
{
    global $db, $mybb;

    $packages_cache = cache_get();

    $package_data = &$packages_cache[$package_id];

    $interval = TIME_NOW - (60 * 60 * $package_data['hours']);

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
            $where_clauses[] = "t.uid='{$user_id}'";

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

            $where_clauses[] = "p.uid='{$user_id}'";

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
            $where_clauses[] = "r.uid='{$user_id}'";

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

function package_insert(array $package_data, bool $is_update = false, int $package_id = 0): int
{
    global $db;

    $insert_data = [];

    if (isset($package_data['title'])) {
        $insert_data['title'] = $db->escape_string($package_data['title']);
    }

    if (isset($package_data['description'])) {
        $insert_data['description'] = $db->escape_string($package_data['description']);
    }

    if (isset($package_data['type'])) {
        $insert_data['type'] = (int)$package_data['type'];
    }

    if (isset($package_data['active'])) {
        $insert_data['active'] = (int)$package_data['active'];
    }

    if (isset($package_data['amount'])) {
        $insert_data['amount'] = (int)$package_data['amount'];
    }

    if (isset($package_data['points'])) {
        $insert_data['points'] = (float)$package_data['points'];
    }

    if (isset($package_data['allowed_groups'])) {
        $insert_data['allowed_groups'] = $db->escape_string($package_data['allowed_groups']);
    }

    if (isset($package_data['forums'])) {
        $insert_data['forums'] = $db->escape_string($package_data['forums']);
    }

    if (isset($package_data['forums_type'])) {
        $insert_data['forums_type'] = (int)$package_data['forums_type'];
    }

    if (isset($package_data['forums_type_amount'])) {
        $insert_data['forums_type_amount'] = (int)$package_data['forums_type_amount'];
    }

    if (isset($package_data['hours'])) {
        $insert_data['hours'] = (int)$package_data['hours'];
    }

    if ($is_update) {
        $db->update_query('newpoints_activity_rewards_packages', $insert_data, "pid='{$package_id}'");

        return $package_id;
    }

    return (int)$db->insert_query('newpoints_activity_rewards_packages', $insert_data);
}

function package_update(array $package_data, int $package_id): int
{
    return package_insert($package_data, true, $package_id);
}

function package_get(array $where_clauses = [], array $query_fields = [], array $query_options = []): array
{
    global $db;

    $query_fields[] = 'pid';

    $query = $db->simple_select(
        'newpoints_activity_rewards_packages',
        implode(',', $query_fields),
        implode(' AND ', $where_clauses),
        $query_options
    );

    if (isset($query_options['limit']) && $query_options['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $package_objects = [];

    while ($package_data = $db->fetch_array($query)) {
        $package_objects[(int)$package_data['pid']] = $package_data;
    }

    return $package_objects;
}

function package_delete(array $where_clauses): bool
{
    global $db;

    foreach (package_get($where_clauses) as $package_id => $package_data) {
        log_delete(["pid='{$package_id}'"]);

        $db->delete_query('newpoints_activity_rewards_packages', "pid='{$package_id}'");
    }

    return true;
}

function log_insert(array $log_data, bool $is_update = false, int $log_id = 0): int
{
    global $db;

    $insert_data = [];

    if (isset($log_data['pid'])) {
        $insert_data['pid'] = (int)$log_data['pid'];
    }

    if (isset($log_data['uid'])) {
        $insert_data['uid'] = (int)$log_data['uid'];
    }

    if (isset($log_data['dateline'])) {
        $insert_data['dateline'] = (int)$log_data['dateline'];
    } elseif (!$is_update) {
        $insert_data['dateline'] = TIME_NOW;
    }

    if ($is_update) {
        $db->update_query('newpoints_activity_rewards_logs', $insert_data, "lid='{$log_id}'");

        return $log_id;
    }

    return (int)$db->insert_query('newpoints_activity_rewards_logs', $insert_data);
}

function log_get(array $where_clauses, array $query_fields = [], array $query_options = []): array
{
    global $db;

    $query_fields[] = 'lid';

    $query = $db->simple_select(
        'newpoints_activity_rewards_logs',
        implode(',', $query_fields),
        implode(' AND ', $where_clauses),
        $query_options
    );

    if (isset($query_options['limit']) && $query_options['limit'] == 1) {
        return (array)$db->fetch_array($query);
    }

    $log_objects = [];

    while ($log_data = $db->fetch_array($query)) {
        $log_objects[(int)$log_data['lid']] = $log_data;
    }

    return $log_objects;
}

function log_delete(array $where_clauses): bool
{
    global $db;

    $db->delete_query('newpoints_activity_rewards_logs', implode(' AND ', $where_clauses));

    return true;
}

function notify_user_rewards(int $user_id): bool
{
    foreach (cache_get() as $package_id => $package_data) {
        $user_amount = get_user_activity_amount($package_id, $user_id);

        $package_interval = TIME_NOW - (60 * 60 * $package_data['hours']);

        if (log_get(["pid='{$package_id}'", "uid='{$user_id}'", "dateline>='{$package_interval}'"])) {
            continue;
        }

        $package_amount = $package_data['amount'];

        if ($user_amount > $package_amount) {
            $user_amount -= $package_amount;
        }

        if ($user_amount < $package_amount) {
            continue;
        }

        alert_send($user_id, $package_id, 'activity_rewards', 'threads');
    }

    return true;
}