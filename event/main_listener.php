<?php

/**
 *
 * Show Users Local Time. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2025, Anişor Neculai, https://crimin.us
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace anix\sult\event;

/**
 * @ignore
 */

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Show Users Local Time Event listener.
 */
class main_listener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'core.user_setup'                                => 'load_language_on_setup',
            'core.viewtopic_cache_user_data'                => 'viewtopic_cache_user_data',
            'core.viewtopic_modify_post_row'                => 'viewtopic_modify_post_row',
            'core.memberlist_prepare_profile_data'          => 'memberlist_profile',
        ];
    }

    /* @var \phpbb\language\language */
    protected $language;

    /** @var user */
    protected $user;

    /* @var \phpbb\template\template */
    protected $template;

    /** @var \phpbb\config\config */
    protected $config;

    /**
     * Constructor
     *
     * @param \phpbb\language\language	$language	Language object
     */
    public function __construct(
        \phpbb\language\language $language,
        \phpbb\user $user,
        \phpbb\template\template $template,
        \phpbb\config\config $config
    ) {
        $this->language = $language;
        $this->user = $user;
        $this->template = $template;
        $this->config    = $config;
    }

    /**
     * Load common language files during user setup
     *
     * @param \phpbb\event\data	$event	Event object
     */
    public function load_language_on_setup($event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        $lang_set_ext[] = [
            'ext_name' => 'anix/sult',
            'lang_set' => 'common',
        ];
        $event['lang_set_ext'] = $lang_set_ext;
    }

    public function viewtopic_cache_user_data($event)
    {
        $user_cache_data = $event['user_cache_data'];
        $row = $event['row'];

        $user_cache_data['user_timezone'] = $row['user_timezone'];
        $event['user_cache_data'] = $user_cache_data;
    }

    public function viewtopic_modify_post_row($event)
    {
        //Access the relevant data
        $user_cache = $event['user_cache'];
        $poster_id = $event['poster_id'];

        if (!isset($user_cache[$poster_id])) {
            return;
        }

        //Get timezone for current user & poster
        $user_timezone = $this->user->data['user_timezone'];
        $poster_timezone = $user_cache[$poster_id]['user_timezone'] ?? null;

        if ($poster_timezone === null) {
            return;
        }

        //If the user is not logged in, we use the board's current time
        if ($this->user->data['user_id'] == ANONYMOUS) {
            $user_timezone = $this->config['board_timezone'];
        }

        //Create DateTime for both timezones
        $user_tz = $this->getTimezone($user_timezone);
        $poster_tz = $this->getTimezone($poster_timezone);

        //Temporarily switch timezone
        $this->user->timezone = $poster_tz;

        // Get current time in the poster's timezone
        $current_date = new \DateTime('now', $poster_tz);
        $formatted_local_time = $this->user->format_date($current_date->getTimestamp());

        $event['post_row'] = array_merge($event['post_row'], [
            'POSTER_TIME'       => $formatted_local_time
        ]);

        //Switch back to user's timezone
        $this->user->timezone = $user_tz;
    }

    public function memberlist_profile($event)
    {
        //Access the relevant data
        $data = $event['data'];

        //Get timezone for current user & member
        $user_timezone = $this->user->data['user_timezone'];
        $member_timezone = $data['user_timezone'];

        //If the user is not logged in, we use the board's current time
        if ($this->user->data['user_id'] == ANONYMOUS) {
            $user_timezone = $this->config['board_timezone'];
        }

        //If user is deleted, we use the board's current time
        if (!$member_timezone) {
            $member_timezone = $this->config['board_timezone'];
        }

        //Create DateTime for both timezones
        $user_tz = $this->getTimezone($user_timezone);
        $member_tz = $this->getTimezone($member_timezone);

        //Temporarily switch timezone
        $this->user->timezone = $member_tz;

        // Get current time in the members's timezone
        $current_date = new \DateTime('now', $member_tz);
        $formatted_local_time = $this->user->format_date($current_date->getTimestamp());

        $this->template->assign_vars([
            'MEMBER_TIME_PROFILE'   => $formatted_local_time,
        ]);

        $event['template_data'] = array_merge($event['template_data'], [
            'MEMBER_TIME'       => $formatted_local_time,
        ]);

        //Switch back to user's timezone
        $this->user->timezone = $user_tz;
    }

    protected function getTimezone(string $timezone)
    {
        return new \DateTimeZone($timezone);
    }
}
