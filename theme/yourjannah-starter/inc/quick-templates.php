<?php
/**
 * Quick Post Templates — Prefilled announcement templates for mosque admins.
 *
 * Used by: front-end quick post modal, dashboard announcements form.
 * Placeholders in [BRACKETS] should be replaced by the admin before posting.
 *
 * @package YourJannah
 * @since   3.9.8
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get all quick post templates.
 *
 * @param  string $mosque_name  Optional mosque name to replace [MOSQUE] placeholder.
 * @return array
 */
function ynj_get_quick_templates( $mosque_name = '' ) {
    $templates = [
        [
            'key'   => 'parking',
            'icon'  => '🚗',
            'label' => 'Parking Reminder',
            'title' => "Don't Block the Car Park",
            'body'  => "Brothers and sisters, please park responsibly and do not double-park or block other vehicles. JazakAllah khayr.",
            'type'  => 'general',
        ],
        [
            'key'   => 'jumuah',
            'icon'  => '🕌',
            'label' => "Jumu'ah Reminder",
            'title' => "Jumu'ah Reminder — Arrive Early",
            'body'  => "Reminder: Jumu'ah prayers today. Please arrive early to get a good spot. First khutbah begins at [TIME].",
            'type'  => 'religious',
        ],
        [
            'key'   => 'funeral',
            'icon'  => '🤲',
            'label' => 'Funeral Prayer',
            'title' => 'Inna lillahi wa inna ilayhi raji\'un',
            'body'  => "Funeral prayer (Salat al-Janazah) will be held today at [TIME] at the masjid. Please attend if you can. May Allah grant the deceased Jannah.",
            'type'  => 'urgent',
        ],
        [
            'key'   => 'cleaning',
            'icon'  => '🧹',
            'label' => 'Mosque Cleaning',
            'title' => 'Mosque Cleaning Volunteer Day',
            'body'  => "We need volunteers this [DAY] for a deep clean of the masjid. Please come after [PRAYER] prayer. Cleaning supplies provided.",
            'type'  => 'general',
        ],
        [
            'key'   => 'lost_property',
            'icon'  => '📦',
            'label' => 'Lost Property',
            'title' => 'Lost Property — Please Collect',
            'body'  => "We have uncollected lost property at the masjid. Please check if any items belong to you. Items not collected by [DATE] will be donated.",
            'type'  => 'general',
        ],
        [
            'key'   => 'iftaar',
            'icon'  => '🍽️',
            'label' => 'Iftaar Tonight',
            'title' => 'Iftaar at the Masjid Tonight',
            'body'  => "Iftaar will be served at the masjid tonight at [TIME]. All are welcome. Please bring a dish to share if you can.",
            'type'  => 'event',
        ],
        [
            'key'   => 'sisters',
            'icon'  => '👩',
            'label' => "Sisters' Circle",
            'title' => "Sisters' Circle This [DAY]",
            'body'  => "Sisters' weekly halaqah will be held this [DAY] after [PRAYER] prayer. Topic: [TOPIC]. All sisters welcome.",
            'type'  => 'religious',
        ],
        [
            'key'   => 'youth',
            'icon'  => '⚽',
            'label' => 'Youth Event',
            'title' => 'Youth Event This [DAY]',
            'body'  => "Youth programme this [DAY] at [TIME]. Activities include [ACTIVITY]. Ages 12-18 welcome.",
            'type'  => 'event',
        ],
        [
            'key'   => 'charity',
            'icon'  => '💝',
            'label' => 'Charity Collection',
            'title' => 'Friday Charity Collection',
            'body'  => "This Friday we are collecting for [CAUSE]. Please give generously. Collection boxes will be at all exits after Jumu'ah.",
            'type'  => 'religious',
        ],
        [
            'key'   => 'eid',
            'icon'  => '🌙',
            'label' => 'Eid Announcement',
            'title' => 'Eid Mubarak!',
            'body'  => "Eid Mubarak from all of us at [MOSQUE]. Eid prayer times have been announced — check our page for details. Taqabbal Allahu minna wa minkum.",
            'type'  => 'urgent',
        ],
        [
            'key'   => 'new_class',
            'icon'  => '📚',
            'label' => 'New Class',
            'title' => 'New Class Starting — [SUBJECT]',
            'body'  => "We are excited to announce a new [SUBJECT] class starting [DATE]. Classes will be held every [DAY] at [TIME]. Open to all levels. Register on our page.",
            'type'  => 'event',
        ],
        [
            'key'   => 'general',
            'icon'  => '📢',
            'label' => 'General Update',
            'title' => 'Important Reminder',
            'body'  => '[Your message here]',
            'type'  => 'general',
        ],
    ];

    // Replace [MOSQUE] with actual name
    if ( $mosque_name ) {
        foreach ( $templates as &$t ) {
            $t['body'] = str_replace( '[MOSQUE]', $mosque_name, $t['body'] );
        }
        unset( $t );
    }

    return $templates;
}
