<?php

/***************************************************************************
 *
 *    NewPoints Activity Rewards plugin (/inc/plugins/ActivityRewards/hooks/forum.php)
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

namespace Newpoints\ActivityRewards\Hooks\Forum;

use MyBB;

use function Newpoints\ActivityRewards\Core\cache_get;
use function Newpoints\ActivityRewards\Core\get_user_activity_amount;
use function Newpoints\Core\get_setting;
use function Newpoints\Core\language_load;
use function Newpoints\Core\log_add;
use function Newpoints\Core\page_build_purchase_confirmation;
use function Newpoints\Core\points_add_simple;
use function Newpoints\Core\points_format;
use function Newpoints\ActivityRewards\Core\templates_get;
use function Newpoints\Core\url_handler_build;

use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_FORUM_TYPE_ANY;
use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_FORUM_TYPE_ALL;
use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_TYPE_POSTS;
use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_TYPE_REPUTATION;
use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_TYPE_THREADS;

function newpoints_global_start(array &$hook_arguments): array
{
    $hook_arguments['newpoints.php'] = array_merge($hook_arguments['newpoints.php'], [
        'newpoints_activity_rewards_page_confirm_request',
        'newpoints_activity_rewards_page_empty',
        'newpoints_activity_rewards_page_package',
        'newpoints_activity_rewards_page_packages',
    ]);

    return $hook_arguments;
}

function newpoints_terminate()
{
    global $mybb;

    if ($mybb->get_input('action') !== get_setting('activity_rewards_action_name')) {
        return;
    }

    global $db, $lang, $cache, $theme;
    global $header, $headerinclude, $footer;
    global $newpoints_menu, $newpoints_errors, $newpoints_content, $newpoints_buttons, $newpoints_pagination, $newpoints_file, $newpoints_additional;
    global $action_name;

    language_load('activity_rewards');

    $action_name = get_setting('activity_rewards_action_name');

    $url_params = ['action' => $action_name];

    add_breadcrumb(
        $lang->newpoints_activity_rewards_page_breadcrumb,
        $mybb->settings['bburl'] . '/' . url_handler_build($url_params)
    );

    $page_url = url_handler_build([
        'action' => $action_name
    ]);

    $page_title = $lang->newpoints_activity_rewards_page_title;

    $current_user_id = (int)$mybb->user['uid'];

    $package_id = $mybb->get_input('pid', MyBB::INPUT_INT);

    $packages_cache = cache_get();

    if ($mybb->request_method == 'post') {
        verify_post_check($mybb->get_input('my_post_key'));

        $package_data = $packages_cache[$package_id] ?? [];

        if (empty($package_data) || !is_member($package_data['groups'])) {
            error_no_permission();
        }

        $user_amount = get_user_activity_amount($package_id);

        $package_amount = $package_data['amount'];

        if ($user_amount < $package_amount) {
            error_no_permission();
        }

        $package_interval = TIME_NOW - (60 * 60 * $package_data['hours']);

        $query = $db->simple_select(
            'newpoints_activity_rewards_logs',
            '*',
            "pid='{$package_id}' AND uid='{$current_user_id}' AND dateline>='{$package_interval}'"
        );

        if ($db->num_rows($query)) {
            error_no_permission();
        }

        $package_name = htmlspecialchars_uni($package_data['title']);

        $package_points = points_format($package_data['points']);

        $lang->newpoints_page_confirm_table_purchase_title = $lang->newpoints_page_confirm_table_purchase_button = $lang->newpoints_activity_rewards_page_button_request_confirm;

        if (!$mybb->get_input('confirm', MyBB::INPUT_INT)) {
            page_build_purchase_confirmation(
                $lang->newpoints_activity_rewards_page_confirm_table_title_description,
                'pid',
                $package_id,
                '',
                eval(templates_get('page_confirm_request'))
            );
        }

        $package_points = $package_data['points'];

        points_add_simple($current_user_id, $package_points);

        log_add(
            'activity_rewards',
            '',
            $mybb->user['username'],
            $current_user_id,
            $package_points,
            $package_id,
            $package_amount
        );

        $db->insert_query('newpoints_activity_rewards_logs', [
            'pid' => $package_id,
            'uid' => $current_user_id,
            'dateline' => TIME_NOW
        ]);

        redirect(
            "{$mybb->settings['bburl']}/{$page_url}",
            $lang->newpoints_activity_rewards_page_success
        );
    }

    $packages_list_threads = $packages_list_posts = $packages_list_reputations = '';

    $forums_cache = cache_forums();

    foreach ($packages_cache as $package_id => $package_data) {
        if (!is_member($package_data['groups'])) {
            continue;
        }

        $package_title = htmlspecialchars_uni($package_data['title']);

        $package_description = htmlspecialchars_uni($package_data['description']);

        $package_amount = $package_data['amount'];

        $package_points = $package_data['points'];

        switch ($package_data['type']) {
            case ACTIVITY_REWARDS_TYPE_THREADS:
                $package_type_text = $lang->newpoints_activity_rewards_page_table_amount_threads;

                $package_type = $lang->newpoints_activity_rewards_page_table_type_threads;
                break;
            case ACTIVITY_REWARDS_TYPE_POSTS:
                $package_type_text = $lang->newpoints_activity_rewards_page_table_amount_posts;

                $package_type = $lang->newpoints_activity_rewards_page_table_type_posts;
                break;
            case ACTIVITY_REWARDS_TYPE_REPUTATION:
                $package_type_text = $lang->newpoints_activity_rewards_page_table_amount_reputation;

                $package_type = $lang->newpoints_activity_rewards_page_table_type_reputation;
                break;
        }

        $package_amount = my_number_format($package_amount);

        $package_points = points_format($package_points);

        $package_hours = my_number_format($package_data['hours']);

        $package_interval = TIME_NOW - (60 * 60 * $package_data['hours']);

        $query = $db->simple_select(
            'newpoints_activity_rewards_logs',
            '*',
            "pid='{$package_id}' AND uid='{$current_user_id}' AND dateline>='{$package_interval}'"
        );

        $button_disabled_element = '';

        $user_amount = get_user_activity_amount($package_id);

        if ($db->num_rows($query)) {
            $button_disabled_element = 'disabled="disabled"';

            if ($user_amount >= $package_amount) {
                $user_amount -= $package_amount;
            }
        }

        if ($user_amount < $package_amount) {
            $button_disabled_element = 'disabled="disabled"';
        }

        if ($user_amount > $package_amount) {
            $user_amount = $package_amount;
        }

        $user_amount = my_number_format($user_amount);

        $package_forums = '';

        if (!empty($package_data['forums'])) {
            $package_forums_ids = explode(',', $package_data['forums']);

            $package_forums_items = [];

            foreach ($package_forums_ids as $forum_id) {
                $forum_data = $forums_cache[$forum_id] ?? [];

                if (empty($forum_data['active'])) {
                    continue;
                }

                $forum_name = htmlspecialchars_uni(strip_tags($forum_data['name']));

                $forum_link = get_forum_link($forum_id);

                $package_forums_items[] = eval(templates_get('page_package_forum_item'));
            }

            if ($package_forums_items) {
                if ($package_data['type'] === ACTIVITY_REWARDS_TYPE_THREADS) {
                    switch ($package_data['forums_type']) {
                        case ACTIVITY_REWARDS_FORUM_TYPE_ANY:
                            $package_forums_note = $lang->newpoints_activity_rewards_page_table_forums_note_thread_any;
                            break;
                        case ACTIVITY_REWARDS_FORUM_TYPE_ALL:
                            $package_forums_note = $lang->sprintf(
                                $lang->newpoints_activity_rewards_page_table_forums_note_thread_all,
                                $package_data['forums_type_amount']
                            );
                            break;
                    }
                } elseif ($package_data['type'] === ACTIVITY_REWARDS_TYPE_POSTS) {
                    switch ($package_data['forums_type']) {
                        case ACTIVITY_REWARDS_FORUM_TYPE_ANY:
                            $package_forums_note = $lang->newpoints_activity_rewards_page_table_forums_note_post_any;
                            break;
                        case ACTIVITY_REWARDS_FORUM_TYPE_ALL:
                            $package_forums_note = $lang->sprintf(
                                $lang->newpoints_activity_rewards_page_table_forums_note_post_all,
                                $package_data['forums_type_amount']
                            );
                            break;
                    }
                } elseif ($package_data['type'] === ACTIVITY_REWARDS_TYPE_REPUTATION) {
                    switch ($package_data['forums_type']) {
                        case ACTIVITY_REWARDS_FORUM_TYPE_ANY:
                            $package_forums_note = $lang->newpoints_activity_rewards_page_table_forums_note_reputation_any;
                            break;
                        case ACTIVITY_REWARDS_FORUM_TYPE_ALL:
                            $package_forums_note = $lang->sprintf(
                                $lang->newpoints_activity_rewards_page_table_forums_note_reputation_all,
                                $package_data['forums_type_amount']
                            );
                            break;
                    }
                }

                $package_forum_list = implode($lang->comma, $package_forums_items);

                $package_forums .= eval(templates_get('page_package_forum'));
            }
        }

        switch ($package_data['type']) {
            case ACTIVITY_REWARDS_TYPE_THREADS:
                $packages_list_threads .= eval(templates_get('page_package'));
                break;
            case ACTIVITY_REWARDS_TYPE_POSTS:
                $packages_list_posts .= eval(templates_get('page_package'));
                break;
            case ACTIVITY_REWARDS_TYPE_REPUTATION:
                $packages_list_reputations .= eval(templates_get('page_package'));
                break;
        }
    }

    if (empty($packages_list_posts) && empty($packages_list_threads) && empty($packages_list_reputations)) {
        $newpoints_content = eval(templates_get('page_empty'));
    } else {
        $newpoints_content = eval(templates_get('page_packages'));
    }

    output_page(eval(\Newpoints\Core\templates_get('page')));

    exit;
}

function newpoints_default_menu(array &$menu): array
{
    global $mybb;

    language_load('activity_rewards');

    $menu[get_setting('activity_rewards_menu_order')] = [
        'action' => get_setting('activity_rewards_action_name'),
        'lang_string' => 'newpoints_activity_rewards_menu'
    ];

    return $menu;
}

function newpoints_logs_log_row(): bool
{
    global $log_data;

    if (!in_array($log_data['action'], [
        'activity_rewards'
    ])) {
        return false;
    }

    global $lang;
    global $log_action, $log_primary, $log_secondary;

    language_load('activity_rewards');

    $packages_cache = cache_get();

    $package_id = (int)$log_data['log_primary_id'];

    $package_data = $packages_cache[$package_id] ?? [];

    if (!empty($package_data)) {
        $log_primary = $lang->sprintf(
            $lang->newpoints_activity_rewards_page_logs_primary,
            htmlspecialchars_uni($package_data['title'])
        );
    }

    $package_amount = (int)$log_data['log_secondary_id'];

    if (!empty($package_amount)) {
        switch ($package_data['type']) {
            case ACTIVITY_REWARDS_TYPE_THREADS:
                $log_secondary = $lang->sprintf(
                    $lang->newpoints_activity_rewards_page_logs_secondary_threads,
                    my_number_format($package_amount)
                );
                break;
            case ACTIVITY_REWARDS_TYPE_POSTS:
                $log_secondary = $lang->sprintf(
                    $lang->newpoints_activity_rewards_page_logs_secondary_posts,
                    my_number_format($package_amount)
                );
                break;
            case ACTIVITY_REWARDS_TYPE_REPUTATION:
                $log_secondary = $lang->sprintf(
                    $lang->newpoints_activity_rewards_page_logs_secondary_reputation,
                    my_number_format($package_amount)
                );
                break;
        }
    }

    if ($log_data['action'] === 'activity_rewards') {
        $log_action = $lang->newpoints_activity_rewards_page_logs_activity_rewards;
    }

    return true;
}

function newpoints_logs_end(): bool
{
    global $lang;
    global $action_types;

    language_load('activity_rewards');

    foreach ($action_types as $key => &$action_type) {
        if ($key === 'activity_rewards') {
            $action_type = $lang->newpoints_activity_rewards_page_logs_activity_rewards;
        }
    }

    return true;
}