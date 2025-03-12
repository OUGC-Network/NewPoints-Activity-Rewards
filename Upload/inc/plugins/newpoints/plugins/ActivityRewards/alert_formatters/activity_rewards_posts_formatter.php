<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/newpoints/plugins/ActivityRewards/alert_formatters/activity_rewards_posts_formatter.php)
 *    Author: Pirata Nervo
 *    Copyright: © 2009 Pirata Nervo
 *    Copyright: © 2024 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    NewPoints plugin for MyBB - A complex but efficient points system for MyBB.
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

namespace Newpoints\ActivityRewards\MyAlerts\Formatters;

use MybbStuff_MyAlerts_Entity_Alert;
use MybbStuff_MyAlerts_Formatter_AbstractFormatter;

use function Newpoints\Core\language_load;
use function Newpoints\Core\log_get;
use function Newpoints\Core\main_file_name;
use function Newpoints\Core\points_format;
use function Newpoints\Core\get_setting;
use function Newpoints\Core\url_handler_build;

class newpoints_activity_rewards_posts_formatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
{
    public function init(): bool
    {
        return language_load('activity_rewards');
    }

    /**
     * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
     *
     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
     *
     * @return string The formatted alert string.
     */
    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert): string
    {
        $details = $alert->toArray();

        $log_id = (int)$details['object_id'];

        $log_data = log_get($log_id);

        return $this->lang->newpoints_alert_text_activity_rewards_posts;
    }

    /**
     * Build a link to an alert's content so that the system can redirect to it.
     *
     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
     *
     * @return string The built alert, preferably an absolute link.
     */
    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert): string
    {
        global $settings;

        return $settings['bburl'] . '/' . url_handler_build(['action' => get_setting('activity_rewards_action_name')]);
    }
}