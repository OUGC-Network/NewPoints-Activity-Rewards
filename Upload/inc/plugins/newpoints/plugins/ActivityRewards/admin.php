<?php

/***************************************************************************
 *
 *    NewPoints Activity Rewards plugin (/inc/plugins/ActivityRewards/admin.php)
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

namespace Newpoints\ActivityRewards\Admin;

use function Newpoints\ActivityRewards\Core\cache_update;
use function Newpoints\Admin\db_drop_tables;
use function Newpoints\Admin\db_verify_columns_exists;
use function Newpoints\Admin\db_verify_tables;
use function Newpoints\Admin\db_verify_tables_exists;
use function Newpoints\Core\language_load;
use function Newpoints\Core\log_remove;
use function Newpoints\Core\plugins_version_delete;
use function Newpoints\Core\plugins_version_get;
use function Newpoints\Core\plugins_version_update;
use function Newpoints\Core\settings_remove;
use function Newpoints\Core\templates_remove;

use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_TYPE_POSTS;
use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_TYPE_REPUTATION;
use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_TYPE_THREADS;
use const Newpoints\Admin\PERMISSION_REMOVE;
use const PLUGINLIBRARY;

const TABLES_DATA = [
    'newpoints_activity_rewards_packages' => [
        'pid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'title' => [
            'type' => 'VARCHAR',
            'size' => 150,
            'default' => ''
        ],
        'description' => [
            'type' => 'VARCHAR',
            'size' => 250,
            'default' => ''
        ],
        'type' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'active' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'amount' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 10
        ],
        'points' => [
            'type' => 'DECIMAL',
            'size' => '16,2',
            'default' => 0
        ],
        'groups' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'forums' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'forums_type' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'forums_type_amount' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'hours' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 24
        ],
    ],
    'newpoints_activity_rewards_logs' => [
        'lid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'pid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 24
        ],
        'uid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 24
        ],
    ],
];

function plugin_information(): array
{
    global $lang;

    language_load('activity_rewards');

    return [
        'name' => 'Activity Rewards',
        'description' => $lang->newpoints_activity_rewards,
        'website' => 'https://ougc.network',
        'author' => 'Omar G.',
        'authorsite' => 'https://ougc.network',
        'version' => '3.0.0',
        'versioncode' => 3100,
        'compatibility' => '31*',
        'codename' => 'newpoints_activity_rewards'
        //'codename' => 'ougc_points_activity_rewards'
    ];
}

function plugin_activation(): bool
{
    global $db, $cache;
    global $PL;

    $PL || require_once PLUGINLIBRARY;

    language_load('activity_rewards');

    $current_version = plugins_version_get('activity_rewards');

    $new_version = (int)plugin_information()['versioncode'];

    /*~*~* RUN UPDATES START *~*~*/
    $query = $db->simple_select('newpoints_log', '*', "action='ougc_points_activity_rewards'");

    while ($log_data = $db->fetch_array($query)) {
        $data = my_unserialize($log_data['data']);

        $db->update_query(
            'newpoints_log',
            [
                'action' => 'activity_rewards',
                'data' => isset($data['amount']) ? "Amount: {$data['amount']}" : '',
                'points' => $data['points'] ?? 0,
                'log_primary_id' => $data['pid'] ?? 0,
                'log_secondary_id' => $data['amount'] ?? 0
            ],
            "lid='{$log_data['lid']}'"
        );
    }

    $PL->templates_delete('ougcpointsactivityrewards');

    $plugins = (array)$cache->read('ougc_plugins');

    if (isset($plugins['pointsactivityrewards'])) {
        unset($plugins['pointsactivityrewards']);
    }

    if (!empty($plugins)) {
        $cache->update('ougc_plugins', $plugins);
    } else {
        $cache->delete('ougc_plugins');
    }

    $cache->delete('ougc_points_activity_rewards_packages');

    change_admin_permission('newpoints', 'activity_rewards', PERMISSION_REMOVE);

    $PL->settings_delete('ougc_points_activity_rewards');

    if ($db->table_exists('ougc_points_activity_rewards_packages')) {
        $db->rename_table('ougc_points_activity_rewards_packages', 'newpoints_activity_rewards_packages');

        $db->update_query(
            'newpoints_activity_rewards_packages',
            ['type' => ACTIVITY_REWARDS_TYPE_THREADS],
            "type='thread'"
        );

        $db->update_query(
            'newpoints_activity_rewards_packages',
            ['type' => ACTIVITY_REWARDS_TYPE_POSTS],
            "type='post'"
        );

        $db->update_query(
            'newpoints_activity_rewards_packages',
            ['type' => ACTIVITY_REWARDS_TYPE_REPUTATION],
            "type='rep'"
        );
    }

    if ($db->table_exists('ougc_points_activity_rewards_logs')) {
        $db->rename_table('ougc_points_activity_rewards_logs', 'newpoints_activity_rewards_logs');
    }

    /*~*~* RUN UPDATES END *~*~*/

    db_verify_tables(TABLES_DATA);

    cache_update();

    plugins_version_update('activity_rewards', $new_version);

    return true;
}

function plugin_is_installed(): bool
{
    return db_verify_tables_exists(TABLES_DATA) && db_verify_columns_exists(TABLES_DATA);
}

function plugin_uninstallation(): bool
{
    log_remove(
        [
            'activity_rewards_charge',
            'activity_rewards_author_share',
            'activity_rewards_delete_charge',
            'activity_rewards_delete_author_share'
        ]
    );

    db_drop_tables(TABLES_DATA);

    settings_remove(
        [
            'action_name'
        ],
        'newpoints_activity_rewards_'
    );

    templates_remove([''], 'newpoints_activity_rewards_');

    plugins_version_delete('activity_rewards');

    return true;
}