<?php

global $lang;

use dvzStream\Stream;
use dvzStream\StreamEvent;

use function dvzStream\addStream;
use function Newpoints\Core\get_setting;
use function Newpoints\Core\language_load;
use function Newpoints\Core\points_format;
use function Newpoints\Core\url_handler_build;
use function Newpoints\ActivityRewards\Core\cache_get;
use function Newpoints\ActivityRewards\Core\log_get;
use function Newpoints\ActivityRewards\Core\templates_get;

use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_TYPE_POSTS;
use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_TYPE_REPUTATION;
use const Newpoints\ActivityRewards\Core\ACTIVITY_REWARDS_TYPE_THREADS;

$stream = new Stream();

$stream->setName(explode('.', basename(__FILE__))[0]);

language_load('newpoints_activity_rewards');

$stream->setTitle($lang->newpoints_activity_rewards_dvz_stream);

$stream->setEventTitle($lang->newpoints_activity_rewards_dvz_stream_event);

$stream->setFetchHandler(function (int $query_limit, int $last_log_id = 0) use ($stream) {
    global $db;

    $stream_events = [];

    $log_objects = log_get(
        ["lid>'{$last_log_id}'"],
        ['pid', 'uid', 'dateline'],
        ['order_by' => 'dateline', 'order_dir' => 'desc', 'limit' => $query_limit]
    );

    $log_ids = implode("','", array_map('intval', array_column($log_objects, 'lid')));

    $user_ids = implode("','", array_map('intval', array_column($log_objects, 'uid')));

    $query = $db->simple_select('users', 'uid, username, usergroup, displaygroup, avatar', "uid IN ('{$user_ids}')");

    $users_cache = $newpoints_logs_cache = [];

    while ($user_data = $db->fetch_array($query)) {
        $users_cache[(int)$user_data['uid']] = $user_data;
    }

    $package_cache = cache_get();

    $query = $db->simple_select(
        'newpoints_log',
        'points, log_tertiary_id',
        "action='activity_rewards' AND log_tertiary_id IN ('{$log_ids}')"
    );

    while ($newpoints_log_data = $db->fetch_array($query)) {
        $newpoints_logs_cache[(int)$newpoints_log_data['log_tertiary_id']] = (float)$newpoints_log_data['points'];
    }

    foreach ($log_objects as $log_id => $log_data) {
        $streamEvent = new StreamEvent();

        $streamEvent->setStream($stream);

        $streamEvent->setId($log_id);

        $streamEvent->setDate($log_data['dateline']);

        $streamEvent->setUser([
            'id' => $log_data['uid'],
            'username' => $users_cache[$log_data['uid']]['username'],
            'usergroup' => $users_cache[$log_data['uid']]['usergroup'],
            'displaygroup' => $users_cache[$log_data['uid']]['displaygroup'],
            'avatar' => $users_cache[$log_data['uid']]['avatar'],
        ]);

        $streamEvent->addData([
            'title' => $package_cache[$log_data['pid']]['title'],
            'type' => $package_cache[$log_data['pid']]['type'],
            'points' => (float)($newpoints_logs_cache[$log_id] ?? $package_cache[$log_data['pid']]['points']),

        ]);

        $stream_events[] = $streamEvent;
    }

    return $stream_events;
});

$stream->addProcessHandler(function (StreamEvent $streamEvent) {
    global $mybb, $lang;

    $stream_data = $streamEvent->getData();

    $page_url = url_handler_build([
        'action' => get_setting('activity_rewards_action_name')
    ]);

    $stream_points = strip_tags(points_format($stream_data['points']));

    switch ($stream_data['type']) {
        case ACTIVITY_REWARDS_TYPE_THREADS:
            $stream_text = $lang->newpoints_activity_rewards_dvz_stream_threads;
            break;
        case ACTIVITY_REWARDS_TYPE_POSTS:
            $stream_text = $lang->newpoints_activity_rewards_dvz_stream_posts;
            break;
        case ACTIVITY_REWARDS_TYPE_REPUTATION:
            $stream_text = $lang->newpoints_activity_rewards_dvz_stream_reputation;
            break;
    }

    $stream_text = $lang->sprintf(
        $stream_text,
        $stream_points,
        get_setting('main_curname')
    );

    $item = eval(templates_get('stream_item'));

    $streamEvent->setItem($item);
});

addStream($stream);
