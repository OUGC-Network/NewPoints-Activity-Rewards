<?php

/***************************************************************************
 *
 *    NewPoints Activity Rewards plugin (/inc/plugins/ActivityRewards/hooks/shared.php)
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

namespace Newpoints\ActivityRewards\Hooks\Shared;

use postDatahandler;

use function Newpoints\ActivityRewards\Core\notify_user_rewards;

function datahandler_post_insert_post_end(postDatahandler &$data_handler): postDatahandler
{
    $post_user_id = (int)$data_handler->data['uid'];

    notify_user_rewards($post_user_id);

    return $data_handler;
}

function datahandler_post_insert_thread_end(postDatahandler &$data_handler): postDatahandler
{
    $thread_user_id = (int)$data_handler->data['uid'];

    notify_user_rewards($thread_user_id);

    return $data_handler;
}