<?php
/**
 * Dashboard section: Dua Wall (loaded via ynj_dashboard_sections filter)
 */
if ( ! defined( 'ABSPATH' ) ) return;

$dua_data = class_exists( 'YNJ_Dua_Wall' ) ? YNJ_Dua_Wall::get_duas( $mosque_id, [ 'limit' => 10 ] ) : [ 'duas' => [], 'total' => 0 ];
$stats = class_exists( 'YNJ_Dua_Wall' ) ? YNJ_Dua_Wall::get_stats( $mosque_id ) : [ 'total' => 0, 'total_prayers' => 0 ];
?>

<div class="d-header">
    <h1>🤲 Dua Wall</h1>
    <p>Your community's collective supplications. <?php echo $stats['total']; ?> duas shared, <?php echo number_format( $stats['total_prayers'] ); ?> prayers made.</p>
</div>

<div class="d-stats">
    <div class="d-stat">
        <div style="font-size:24px;font-weight:900;color:#287e61;"><?php echo $stats['total']; ?></div>
        <div style="font-size:11px;color:#6b7280;">Total Duas</div>
    </div>
    <div class="d-stat">
        <div style="font-size:24px;font-weight:900;color:#7c3aed;"><?php echo number_format( $stats['total_prayers'] ); ?></div>
        <div style="font-size:11px;color:#6b7280;">Prayers Made</div>
    </div>
</div>

<?php if ( empty( $dua_data['duas'] ) ) : ?>
<div class="d-card">
    <div class="d-empty">
        <div class="d-empty__icon">🤲</div>
        <h3>No duas yet</h3>
        <p>Your community hasn't shared any duas. Encourage them to share their prayers on the mosque page.</p>
    </div>
</div>
<?php else : ?>
<?php foreach ( $dua_data['duas'] as $dua ) : ?>
<div class="d-card" style="<?php echo $dua->pinned ? 'border-left:3px solid #287e61;' : ''; ?>">
    <?php if ( $dua->pinned ) : ?><div style="font-size:10px;font-weight:700;color:#287e61;margin-bottom:6px;">📌 PINNED</div><?php endif; ?>
    <p style="font-size:14px;line-height:1.6;color:#1a1a1a;margin-bottom:8px;"><?php echo esc_html( $dua->dua_text ); ?></p>
    <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#6b7280;">
        <span><?php echo $dua->is_anonymous ? 'Anonymous' : esc_html( $dua->author_name ?: 'YourJannah' ); ?></span>
        <span style="color:#7c3aed;font-weight:700;">🤲 <?php echo (int) $dua->prayer_count; ?> prayers</span>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
