<?php

/***************************************************************************
 *
 *    OUGC Points Activity Rewards plugin (/inc/plugins/newpoints/plugins/newpoints_activity_rewards.php)
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

use function Newpoints\ActivityRewards\Admin\plugin_information;
use function Newpoints\ActivityRewards\Admin\plugin_activation;
use function Newpoints\ActivityRewards\Admin\plugin_is_installed;
use function Newpoints\ActivityRewards\Admin\plugin_uninstallation;
use function Newpoints\ActivityRewards\Core\cache_update;
use function Newpoints\Core\add_hooks;

use const Newpoints\ActivityRewards\ROOT;
use const Newpoints\ROOT_PLUGINS;

defined('IN_MYBB') || die('Direct initialization of this file is not allowed.');

define('Newpoints\ActivityRewards\ROOT', ROOT_PLUGINS . '/ActivityRewards');

require_once ROOT . '/core.php';

if (defined('IN_ADMINCP')) {
    require_once ROOT . '/admin.php';
    require_once ROOT . '/hooks/admin.php';

    add_hooks('Newpoints\ActivityRewards\Hooks\Admin');
} else {
    require_once ROOT . '/hooks/forum.php';

    add_hooks('Newpoints\ActivityRewards\Hooks\Forum');
}

function newpoints_activity_rewards_info(): array
{
    return plugin_information();
}

function newpoints_activity_rewards_activate(): bool
{
    return plugin_activation();
}

function newpoints_activity_rewards_uninstall(): bool
{
    return plugin_uninstallation();
}

function newpoints_activity_rewards_is_installed(): bool
{
    return plugin_is_installed();
}

function reload_newpoints_activity_rewards_packages(): bool
{
    cache_update();

    return true;
}