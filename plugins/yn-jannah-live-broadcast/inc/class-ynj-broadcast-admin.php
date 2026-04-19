<?php
/**
 * Broadcast WP Admin — stream history, broadcaster management, stats.
 *
 * @package YNJ_Broadcast
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Broadcast_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
    }

    public static function register_menu() {
        add_submenu_page(
            'yn-jannah',
            'Broadcasting',
            'Broadcasting',
            'manage_options',
            'ynj-broadcasting',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page() {
        $stats = YNJ_Broadcast::get_platform_stats();

        // Get mosque filter
        $filter_mosque = absint( $_GET['mosque_id'] ?? 0 );

        // Get all mosques for dropdown
        global $wpdb;
        $mt = YNJ_DB::table( 'mosques' );
        $mosques = $wpdb->get_results( "SELECT id, name FROM $mt WHERE status IN ('active','unclaimed') ORDER BY name ASC LIMIT 500" ) ?: [];
        ?>
        <div class="wrap">
            <h1>Live Broadcasting</h1>
            <p>Manage live streams across all mosques. Broadcasters can be assigned per-mosque.</p>

            <div style="display:flex;gap:16px;margin:20px 0;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:<?php echo $stats['live_now'] > 0 ? '#dc2626' : '#666'; ?>;"><?php echo (int) $stats['live_now']; ?></div>
                    <div style="font-size:12px;color:#666;"><?php echo $stats['live_now'] > 0 ? '🔴 Live Now' : 'Live Now'; ?></div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;"><?php echo (int) $stats['total_broadcasts']; ?></div>
                    <div style="font-size:12px;color:#666;">Total Broadcasts</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;"><?php echo (int) $stats['mosques_streaming']; ?></div>
                    <div style="font-size:12px;color:#666;">Mosques Streaming</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;"><?php
                        $hours = floor( $stats['total_duration'] / 3600 );
                        echo $hours > 0 ? $hours . 'h' : floor( $stats['total_duration'] / 60 ) . 'm';
                    ?></div>
                    <div style="font-size:12px;color:#666;">Total Stream Time</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;"><?php echo number_format( $stats['total_viewers'] ); ?></div>
                    <div style="font-size:12px;color:#666;">Peak Viewers</div>
                </div>
            </div>

            <!-- Mosque filter -->
            <form method="get" style="margin-bottom:16px;">
                <input type="hidden" name="page" value="ynj-broadcasting">
                <select name="mosque_id" onchange="this.form.submit()">
                    <option value="0">All Mosques</option>
                    <?php foreach ( $mosques as $m ) : ?>
                    <option value="<?php echo (int) $m->id; ?>" <?php selected( $filter_mosque, (int) $m->id ); ?>><?php echo esc_html( $m->name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ( $filter_mosque ) : ?>
            <h2>Broadcasters — <?php
                $fname = '';
                foreach ( $mosques as $m ) { if ( (int) $m->id === $filter_mosque ) { $fname = $m->name; break; } }
                echo esc_html( $fname );
            ?></h2>
            <?php
                $broadcasters = YNJ_Broadcast::get_broadcasters( $filter_mosque );
                if ( empty( $broadcasters ) ) :
            ?>
                <p style="color:#666;">No broadcasters assigned. Add a broadcaster to enable live streaming for this mosque.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped" style="margin-bottom:20px;">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Added</th></tr></thead>
                <tbody>
                    <?php foreach ( $broadcasters as $b ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $b->user_name ?: 'User #' . $b->user_id ); ?></strong></td>
                        <td><?php echo esc_html( $b->user_email ?? '' ); ?></td>
                        <td><?php echo esc_html( ucfirst( $b->role ) ); ?></td>
                        <td><?php echo $b->status === 'active' ? '<span style="color:#16a34a;">Active</span>' : esc_html( $b->status ); ?></td>
                        <td><?php echo esc_html( $b->created_at ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <?php endif; ?>

            <h2>Recent Broadcasts</h2>
            <?php
                $bt = YNJ_DB::table( 'broadcasts' );
                $where = $filter_mosque ? $wpdb->prepare( "WHERE b.mosque_id = %d", $filter_mosque ) : '';
                $recent = $wpdb->get_results(
                    "SELECT b.*, m.name AS mosque_name, u.name AS broadcaster_name
                     FROM $bt b LEFT JOIN $mt m ON m.id = b.mosque_id
                     LEFT JOIN " . YNJ_DB::table( 'users' ) . " u ON u.id = b.broadcaster_user_id
                     $where ORDER BY b.created_at DESC LIMIT 50"
                ) ?: [];
            ?>
            <?php if ( empty( $recent ) ) : ?>
                <p style="color:#666;">No broadcasts yet.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Mosque</th><th>Title</th><th>Type</th><th>Broadcaster</th><th>Status</th><th>Duration</th><th>Viewers</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ( $recent as $b ) :
                        $types = YNJ_Broadcast::get_stream_types();
                        $dur = (int) $b->duration_seconds;
                        $dur_str = $dur > 3600 ? floor($dur/3600).'h '.floor(($dur%3600)/60).'m' : floor($dur/60).'m';
                    ?>
                    <tr>
                        <td><?php echo esc_html( $b->mosque_name ?: 'Mosque #' . $b->mosque_id ); ?></td>
                        <td><strong><?php echo esc_html( $b->title ?: '—' ); ?></strong></td>
                        <td><?php echo ( $types[ $b->stream_type ]['icon'] ?? '' ) . ' ' . esc_html( $types[ $b->stream_type ]['label'] ?? $b->stream_type ); ?></td>
                        <td><?php echo esc_html( $b->broadcaster_name ?: '—' ); ?></td>
                        <td><?php
                            if ( $b->status === 'live' ) echo '<span style="color:#dc2626;font-weight:700;">🔴 LIVE</span>';
                            elseif ( $b->status === 'ended' ) echo '<span style="color:#666;">Ended</span>';
                            else echo esc_html( ucfirst( $b->status ) );
                        ?></td>
                        <td><?php echo $b->status === 'ended' ? esc_html( $dur_str ) : '—'; ?></td>
                        <td><?php echo (int) $b->peak_viewers; ?></td>
                        <td><?php echo esc_html( $b->started_at ?: $b->created_at ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
