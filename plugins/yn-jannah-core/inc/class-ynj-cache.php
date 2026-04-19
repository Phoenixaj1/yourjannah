<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Cache {
    const GROUP = 'ynj';

    public static function get( $key ) {
        return wp_cache_get( $key, self::GROUP );
    }

    public static function set( $key, $data, $ttl = 300 ) {
        wp_cache_set( $key, $data, self::GROUP, $ttl );
    }

    public static function delete( $key ) {
        wp_cache_delete( $key, self::GROUP );
    }

    public static function flush_mosque( $mosque_id ) {
        self::delete( "mosque_{$mosque_id}" );
        self::delete( "events_{$mosque_id}" );
        self::delete( "announcements_{$mosque_id}" );
        self::delete( "classes_{$mosque_id}" );
        self::delete( "services_{$mosque_id}" );
        self::delete( "campaigns_{$mosque_id}" );
    }
}
