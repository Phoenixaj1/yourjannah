<?php
/**
 * Dashboard section: Jumuah Times (loaded via ynj_dashboard_sections filter)
 *
 * Expects: $mosque_id, $mosque_name, $mosque_slug from page-dashboard.php
 */
if ( ! defined( 'ABSPATH' ) ) return;

$slots = class_exists( 'YNJ_Jumuah_Data' ) ? YNJ_Jumuah_Data::get_times( $mosque_id ) : [];
?>

<div class="d-header">
    <h1>🕌 Jumuah Times</h1>
    <p>Manage your Friday prayer khutbah and salah times.</p>
</div>

<?php if ( empty( $slots ) ) : ?>
<div class="d-card">
    <div class="d-empty">
        <div class="d-empty__icon">🕌</div>
        <h3>No Jumuah times set</h3>
        <p>Add your Jumuah khutbah and salah times so people can find them when searching.</p>
    </div>
</div>
<?php else : ?>
<div class="d-card">
    <h3>Current Jumuah Schedule</h3>
    <table class="d-table">
        <thead>
            <tr>
                <th>Slot</th>
                <th>Khutbah</th>
                <th>Salah</th>
                <th>Language</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $slots as $i => $slot ) : ?>
            <tr>
                <td><strong>Jumu'ah <?php echo $i + 1; ?></strong></td>
                <td style="font-weight:700;"><?php echo esc_html( $slot->khutbah_time ? substr( $slot->khutbah_time, 0, 5 ) : '—' ); ?></td>
                <td style="font-weight:700;color:#287e61;"><?php echo esc_html( $slot->salah_time ? substr( $slot->salah_time, 0, 5 ) : '—' ); ?></td>
                <td><?php echo esc_html( $slot->language ?: '' ); ?></td>
                <td style="font-size:12px;color:#666;"><?php echo esc_html( $slot->notes ?: '' ); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
