<?php

/***************************************************************************
 *
 *    Newpoints Buy Format plugin (/inc/plugins/newpoints/plugins/ActivityRewards/admin/packages.php)
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

use function Newpoints\ActivityRewards\Core\cache_update;
use function Newpoints\ActivityRewards\Core\package_delete;
use function Newpoints\Core\language_load;
use function Newpoints\Core\url_handler_build;
use function Newpoints\Core\url_handler_get;
use function Newpoints\Core\url_handler_set;

use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_FORUM_TYPE_ANY;
use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_FORUM_TYPE_ALL;
use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_TYPE_POSTS;
use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_TYPE_REPUTATION;
use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_TYPE_THREADS;

url_handler_set('index.php?module=newpoints-activity_rewards');

global $db, $lang, $mybb, $page, $plugins;

language_load('activity_rewards');

$package_id = $mybb->get_input('package_id', MyBB::INPUT_INT);

$sub_tabs = [];

if (!$mybb->get_input('action') || in_array($mybb->get_input('action'), ['add', 'edit'])) {
    $sub_tabs['newpoints_activity_rewards_view'] = [
        'title' => $lang->newpoints_activity_rewards_admin_view,
        'link' => url_handler_get(),
        'description' => $lang->newpoints_activity_rewards_admin_view_desc
    ];

    $sub_tabs['newpoints_activity_rewards_add'] = [
        'title' => $lang->newpoints_activity_rewards_admin_add,
        'link' => url_handler_build(['action' => 'add']),
        'description' => $lang->newpoints_activity_rewards_admin_add_desc
    ];

    if ($mybb->get_input('action') == 'edit') {
        $sub_tabs['newpoints_activity_rewards_edit'] = [
            'title' => $lang->newpoints_activity_rewards_admin_edit,
            'link' => url_handler_build(
                ['action' => 'edit', 'package_id' => $package_id]
            ),
            'description' => $lang->newpoints_activity_rewards_admin_edit_desc
        ];
    }
}

if ($mybb->get_input('action') === 'delete') {
    if ($mybb->get_input('no')) {
        admin_redirect(url_handler_get());
    }

    $query = $db->simple_select('newpoints_activity_rewards_packages', '*', "pid='{$package_id}'");

    if (!$db->num_rows($query)) {
        flash_message($lang->newpoints_activity_rewards_admin_error_invalid_package, 'error');

        admin_redirect(url_handler_get());
    }

    if ($mybb->request_method == 'post') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            admin_redirect(url_handler_get());
        }

        package_delete($package_id);

        cache_update();

        flash_message($lang->newpoints_activity_rewards_admin_success_deleted_package, 'success');

        admin_redirect(url_handler_get());
    }

    $process_url = url_handler_build([
        'action' => 'delete',
        'package_id' => $package_id,
        'confirm' => 1
    ]);

    $page->output_confirm_action(
        $process_url,
        $lang->newpoints_activity_rewards_admin_confirm_delete
    );
} elseif (in_array($mybb->get_input('action'), ['add', 'edit'])) {
    $add_page = $mybb->get_input('action') === 'add';

    $page->add_breadcrumb_item($lang->newpoints_activity_rewards_admin_title, url_handler_get());

    $page->output_header($lang->newpoints_activity_rewards_admin_title);

    if (!$add_page) {
        $query = $db->simple_select('newpoints_activity_rewards_packages', '*', "pid='{$package_id}'");

        if (!($package_data = $db->fetch_array($query))) {
            admin_redirect(url_handler_get());
        }
    }

    if ($mybb->request_method == 'post') {
        $errors = [];

        foreach (['title', 'type', 'amount', 'hours'] as $key) {
            if (empty($mybb->input[$key])) {
                $errors[] = $lang->{"newpoints_activity_rewards_admin_add_error_{$key}"};
            }
        }


        if ($errors) {
            $page->output_inline_error($errors);
        } else {
            $update_data = [
                'title' => $mybb->get_input('title'),
                'description' => $mybb->get_input('description'),
                'type' => $mybb->get_input('type', MyBB::INPUT_INT),
                'active' => (int)$mybb->get_input('active', MyBB::INPUT_INT),
                'amount' => (int)$mybb->get_input('amount', MyBB::INPUT_INT),
                'points' => (float)$mybb->get_input('points', MyBB::INPUT_FLOAT),
                'groups' => implode(',', $mybb->get_input('groups', MyBB::INPUT_ARRAY)),
                'forums' => implode(',', $mybb->get_input('forums', MyBB::INPUT_ARRAY)),
                'forums_type' => (int)$mybb->get_input('forums_type', MyBB::INPUT_INT),
                'forums_type_amount' => (int)$mybb->get_input('forums_type_amount', MyBB::INPUT_INT),
                'hours' => (int)$mybb->get_input('hours', MyBB::INPUT_INT)
            ];

            if ($update_data['forums_type_amount'] > $update_data['amount']) {
                $update_data['forums_type_amount'] = $update_data['amount'];
            }

            if ($add_page) {
                $package_id = $db->insert_query('newpoints_activity_rewards_packages', $update_data);
            } else {
                $db->update_query('newpoints_activity_rewards_packages', $update_data, "pid='{$package_id}'");
            }

            cache_update();

            if ($add_page) {
                flash_message($lang->newpoints_activity_rewards_admin_success_add_package, 'success');
            } else {
                flash_message($lang->newpoints_activity_rewards_admin_success_updated_package, 'success');
            }

            admin_redirect(url_handler_build(['action' => 'edit', 'package_id' => $package_id]));
        }
    }

    if ($add_page) {
        $page->output_nav_tabs($sub_tabs, 'newpoints_activity_rewards_add');

        $form_url = url_handler_build(['action' => 'add']);
    } else {
        $page->output_nav_tabs($sub_tabs, 'newpoints_activity_rewards_edit');

        $form_url = url_handler_build(['action' => 'edit', 'package_id' => $package_id]);
    }

    foreach (
        [
            'title',
            'description',
            'type',
            'active',
            'amount',
            'points',
            'groups',
            'forums',
            'forums_type',
            'forums_type_amount',
            'hours'
        ] as $key
    ) {
        if (!isset($mybb->input[$key])) {
            if (isset($package_data[$key])) {
                $mybb->input[$key] = $package_data[$key];
            } else {
                $mybb->input[$key] = '';
            }
        }
    }

    $form = new Form($form_url, 'post', 'newpoints_activity_rewards');

    if ($add_page) {
        $form_container = new FormContainer($lang->newpoints_activity_rewards_admin_add_info);
    } else {
        $form_container = new FormContainer($lang->newpoints_activity_rewards_admin_edit_info);
    }

    $form_container->output_row(
        $lang->newpoints_activity_rewards_admin_add_name,
        $lang->newpoints_activity_rewards_admin_add_name_desc,
        $form->generate_text_box('title', $mybb->get_input('title'), ['id' => 'title']),
        'title'
    );

    $form_container->output_row(
        $lang->newpoints_activity_rewards_admin_add_description,
        $lang->newpoints_activity_rewards_admin_add_description_desc,
        $form->generate_text_box('description', $mybb->get_input('description'), ''),
        'description'
    );

    $form_container->output_row(
        $lang->newpoints_activity_rewards_admin_add_type,
        $lang->newpoints_activity_rewards_admin_add_type_desc,
        $form->generate_select_box(
            'type',
            [
                ACTIVITY_REWARDS_TYPE_THREADS => $lang->newpoints_activity_rewards_admin_add_type_threads,
                ACTIVITY_REWARDS_TYPE_POSTS => $lang->newpoints_activity_rewards_admin_add_type_posts,
                ACTIVITY_REWARDS_TYPE_REPUTATION => $lang->newpoints_activity_rewards_admin_add_type_reputation
            ],
            [$mybb->get_input('type', MyBB::INPUT_INT)]
        )
    );

    $form_container->output_row(
        $lang->newpoints_activity_rewards_admin_add_active,
        $lang->newpoints_activity_rewards_admin_add_active_desc,
        $form->generate_yes_no_radio(
            'active',
            $mybb->get_input('active', MyBB::INPUT_INT)
        )
    );

    $form_container->output_row(
        $lang->newpoints_activity_rewards_admin_add_amount,
        $lang->newpoints_activity_rewards_admin_add_amount_desc,
        $form->generate_numeric_field(
            'amount',
            $mybb->get_input('amount', MyBB::INPUT_INT)
        )
    );

    $form_container->output_row(
        $lang->newpoints_activity_rewards_admin_add_points,
        $lang->newpoints_activity_rewards_admin_add_points_desc,
        $form->generate_numeric_field(
            'points',
            $mybb->get_input('points', MyBB::INPUT_FLOAT),
            ['step' => '0.01', 'min' => '0']
        )
    );

    if (!is_array($mybb->input['groups'])) {
        $mybb->input['groups'] = explode(',', $mybb->input['groups']);
    }

    $form_container->output_row(
        $lang->newpoints_activity_rewards_admin_add_groups,
        $lang->newpoints_activity_rewards_admin_add_groups_desc,
        $form->generate_group_select(
            'groups[]',
            $mybb->get_input('groups', MyBB::INPUT_ARRAY),
            ['multiple' => true]
        )
    );

    if (!is_array($mybb->input['forums'])) {
        $mybb->input['forums'] = explode(',', $mybb->input['forums']);
    }

    $form_container->output_row(
        $lang->newpoints_activity_rewards_admin_add_forums,
        $lang->newpoints_activity_rewards_admin_add_forums_desc,
        $form->generate_forum_select(
            'forums[]',
            $mybb->get_input('forums', MyBB::INPUT_ARRAY),
            ['multiple' => true]
        )
    );

    $form_container->output_row(
        $lang->newpoints_activity_rewards_admin_add_forums_type,
        $lang->newpoints_activity_rewards_admin_add_forums_type_desc,
        $form->generate_select_box(
            'forums_type',
            [
                ACTIVITY_REWARDS_FORUM_TYPE_ANY => $lang->newpoints_activity_rewards_admin_add_forums_type_any,
                ACTIVITY_REWARDS_FORUM_TYPE_ALL => $lang->newpoints_activity_rewards_admin_add_forums_type_all,
            ],
            [$mybb->get_input('forums_type', MyBB::INPUT_INT)],
            ['id' => 'select_forums_type']
        )
    );

    $form_container->output_row(
        $lang->newpoints_activity_rewards_admin_add_forums_type_amount,
        $lang->newpoints_activity_rewards_admin_add_forums_type_amount_desc,
        $form->generate_numeric_field(
            'forums_type_amount',
            $mybb->get_input('forums_type_amount', MyBB::INPUT_INT)
        ),
        '',
        ['id' => 'row_forums_type_amount']
    );

    $form_container->output_row(
        $lang->newpoints_activity_rewards_admin_add_hours,
        $lang->newpoints_activity_rewards_admin_add_hours_desc,
        $form->generate_numeric_field(
            'hours',
            $mybb->get_input('hours', MyBB::INPUT_INT)
        )
    );

    $form_container->end();

    $buttons = [];

    if ($add_page) {
        $buttons[] = $form->generate_submit_button($lang->newpoints_activity_rewards_admin_add_submit);
    } else {
        $buttons[] = $form->generate_submit_button($lang->newpoints_activity_rewards_admin_edit_submit);
    }

    $buttons[] = $form->generate_reset_button($lang->reset);

    $form->output_submit_wrapper($buttons);

    $form->end();

    echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
	<script type="text/javascript">
		$(function() {
				new Peeker($("#select_forums_type"), $("#row_forums_type_amount"), ' . ACTIVITY_REWARDS_FORUM_TYPE_ALL . ');
		});
	</script>';

    $page->output_footer();
} else {
    $page->add_breadcrumb_item($lang->newpoints_activity_rewards_admin_title, url_handler_get());

    $page->output_header($lang->newpoints_activity_rewards_admin_title);

    $page->output_nav_tabs($sub_tabs, 'newpoints_activity_rewards_view');

    $table = new Table();

    $table->construct_header($lang->newpoints_activity_rewards_admin_view_type, ['width' => '10%']);

    $table->construct_header($lang->newpoints_activity_rewards_admin_view_title, ['width' => '25%']);

    $table->construct_header($lang->newpoints_activity_rewards_admin_view_description, ['width' => '35%']);

    $table->construct_header(
        $lang->newpoints_activity_rewards_admin_view_status,
        ['width' => '10%', 'class' => 'align_center']
    );

    $table->construct_header($lang->options, ['width' => '20%', 'class' => 'align_center']);

    $query = $db->simple_select(
        'newpoints_activity_rewards_packages',
        '*',
        '',
        ['order_by' => 'type']
    );

    if (!$db->num_rows($query)) {
        $table->construct_cell(
            '<div align="center">' . $lang->newpoints_activity_rewards_admin_view_table_empty . '</div>',
            ['colspan' => 4]
        );

        $table->construct_row();
    } else {
        while ($package_data = $db->fetch_array($query)) {
            switch ($package_data['type']) {
                case ACTIVITY_REWARDS_TYPE_THREADS:
                    $table->construct_cell($lang->newpoints_activity_rewards_admin_view_package_type_threads);
                    break;
                case ACTIVITY_REWARDS_TYPE_POSTS:
                    $table->construct_cell($lang->newpoints_activity_rewards_admin_view_package_type_posts);
                    break;
                case ACTIVITY_REWARDS_TYPE_REPUTATION:
                    $table->construct_cell($lang->newpoints_activity_rewards_admin_view_package_type_reputation);
                    break;
            }

            $url = url_handler_build(['action' => 'edit', 'package_id' => $package_data['pid']]);

            $table->construct_cell("<a href='{$url}'>" . htmlspecialchars_uni($package_data['title']) . '</a>');

            $table->construct_cell(htmlspecialchars_uni($package_data['description']));

            $table->construct_cell(
                '<img src="styles/' . $page->style . '/images/icons/bullet_' . ($package_data['active'] ? 'on' : 'off') . '.png" /> ',
                ['class' => 'align_center']
            );

            $popup = new PopupMenu('service_' . $package_data['pid'], $lang->options);

            $popup->add_item($lang->edit, $url);

            $popup->add_item(
                $lang->delete,
                url_handler_build(['action' => 'delete', 'package_id' => $package_data['pid']])
            );

            $table->construct_cell($popup->fetch(), ['class' => 'align_center']);

            $table->construct_row();
        }
    }

    $table->output($lang->newpoints_activity_rewards_admin_view_table_title);

    $page->output_footer();
}