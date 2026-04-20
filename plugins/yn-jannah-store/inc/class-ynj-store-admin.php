<?php
/**
 * Masjid Store WP Admin — full CRUD for store items with image upload.
 * @package YNJ_Store
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Store_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
    }

    public static function register_menu() {
        add_submenu_page( 'yn-jannah', 'Store', 'Store', 'manage_options', 'ynj-store', [ __CLASS__, 'render_page' ] );
    }

    public static function render_page() {
        wp_enqueue_media();

        // Handle actions
        if ( isset( $_POST['ynj_store_save'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'ynj_store_item' ) ) {
            $id = absint( $_POST['item_id'] ?? 0 );
            YNJ_Store::save_item( $_POST, $id );
            echo '<div class="notice notice-success"><p>Item saved.</p></div>';
        }

        if ( isset( $_GET['delete'] ) && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'ynj_store_delete' ) ) {
            YNJ_Store::delete_item( absint( $_GET['delete'] ) );
            echo '<div class="notice notice-success"><p>Item deleted.</p></div>';
        }

        // Edit mode?
        $editing = null;
        if ( isset( $_GET['edit'] ) ) {
            $editing = YNJ_Store::get_item_by_id( absint( $_GET['edit'] ) );
        }

        $items = YNJ_Store::get_items();

        // Stats
        global $wpdb;
        $tt = YNJ_DB::table( 'transactions' );
        $total = (int) $wpdb->get_var( "SELECT COALESCE(SUM(amount_pence), 0) FROM $tt WHERE item_type = 'store' AND status = 'succeeded'" );
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tt WHERE item_type = 'store' AND status = 'succeeded'" );
        ?>
        <div class="wrap">
            <h1>Masjid Store — Community Shout-Outs</h1>
            <p>Manage purchasable items. All proceeds go to the Masjid and Islamic Projects. Each purchase auto-posts an announcement.</p>

            <div style="display:flex;gap:16px;margin:20px 0;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#287e61;">&pound;<?php echo number_format( $total / 100, 2 ); ?></div>
                    <div style="font-size:12px;color:#666;">Total Sales</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;"><?php echo $count; ?></div>
                    <div style="font-size:12px;color:#666;">Purchases</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;"><?php echo count( $items ); ?></div>
                    <div style="font-size:12px;color:#666;">Active Items</div>
                </div>
            </div>

            <!-- Add/Edit Form -->
            <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;">
                <h2 style="margin-top:0;"><?php echo $editing ? 'Edit Item' : 'Add New Item'; ?></h2>
                <form method="post">
                    <?php wp_nonce_field( 'ynj_store_item' ); ?>
                    <input type="hidden" name="item_id" value="<?php echo $editing ? (int) $editing->id : 0; ?>">
                    <table class="form-table">
                        <tr>
                            <th>Key (slug)</th>
                            <td><input type="text" name="item_key" value="<?php echo esc_attr( $editing->item_key ?? '' ); ?>" class="regular-text" required placeholder="e.g. jumuah_mubarak"></td>
                        </tr>
                        <tr>
                            <th>Title</th>
                            <td><input type="text" name="title" value="<?php echo esc_attr( $editing->title ?? '' ); ?>" class="regular-text" required placeholder="e.g. Jumu'ah Mubarak"></td>
                        </tr>
                        <tr>
                            <th>Description</th>
                            <td><input type="text" name="description" value="<?php echo esc_attr( $editing->description ?? '' ); ?>" class="large-text" placeholder="Short description shown to buyer"></td>
                        </tr>
                        <tr>
                            <th>Icon (emoji)</th>
                            <td><input type="text" name="icon" value="<?php echo esc_attr( $editing->icon ?? '🕌' ); ?>" style="width:60px;font-size:24px;text-align:center;"></td>
                        </tr>
                        <tr>
                            <th>Image</th>
                            <td>
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <input type="text" name="image_url" id="ynj-store-img" value="<?php echo esc_attr( $editing->image_url ?? '' ); ?>" class="regular-text" placeholder="Image URL">
                                    <button type="button" class="button" onclick="ynjStorePickImage()">Choose Image</button>
                                    <?php if ( ! empty( $editing->image_url ) ) : ?>
                                    <img src="<?php echo esc_url( $editing->image_url ); ?>" style="height:50px;border-radius:6px;">
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>Prices (pence)</th>
                            <td>
                                <div style="display:flex;gap:8px;">
                                    <input type="number" name="price_1" value="<?php echo (int) ( $editing->price_1 ?? 300 ); ?>" style="width:100px;" placeholder="Low">
                                    <input type="number" name="price_2" value="<?php echo (int) ( $editing->price_2 ?? 500 ); ?>" style="width:100px;" placeholder="Mid">
                                    <input type="number" name="price_3" value="<?php echo (int) ( $editing->price_3 ?? 1000 ); ?>" style="width:100px;" placeholder="High">
                                </div>
                                <p class="description">In pence. 300 = £3.00, 1000 = £10.00</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Default Price (pence)</th>
                            <td><input type="number" name="default_price" value="<?php echo (int) ( $editing->default_price ?? 500 ); ?>" style="width:100px;"></td>
                        </tr>
                        <tr>
                            <th>Badge Color</th>
                            <td><input type="color" name="badge_color" value="<?php echo esc_attr( $editing->badge_color ?? '#287e61' ); ?>"></td>
                        </tr>
                        <tr>
                            <th>Badge Text</th>
                            <td><input type="text" name="badge_text" value="<?php echo esc_attr( $editing->badge_text ?? '' ); ?>" class="regular-text" placeholder="e.g. Jumu'ah Mubarak"></td>
                        </tr>
                        <tr>
                            <th>Announcement Template</th>
                            <td>
                                <textarea name="announcement_template" rows="3" class="large-text" placeholder="Use {name}, {mosque}, {message} as placeholders"><?php echo esc_textarea( $editing->announcement_template ?? '' ); ?></textarea>
                                <p class="description">Variables: <code>{name}</code> = buyer's name, <code>{mosque}</code> = mosque name, <code>{message}</code> = custom message</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Sort Order</th>
                            <td><input type="number" name="sort_order" value="<?php echo (int) ( $editing->sort_order ?? 0 ); ?>" style="width:60px;"></td>
                        </tr>
                        <tr>
                            <th>Active</th>
                            <td><label><input type="checkbox" name="is_active" value="1" <?php checked( $editing ? $editing->is_active : 1 ); ?>> Show in store</label></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" name="ynj_store_save" class="button button-primary"><?php echo $editing ? 'Update Item' : 'Add Item'; ?></button>
                        <?php if ( $editing ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ynj-store' ) ); ?>" class="button">Cancel</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>

            <!-- Items List -->
            <h2>Store Items</h2>
            <?php if ( empty( $items ) ) : ?>
                <p>No items yet. Add your first item above.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th style="width:40px;"></th><th>Image</th><th>Title</th><th>Description</th><th>Prices</th><th>Active</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ( $items as $item ) : ?>
                    <tr>
                        <td style="font-size:24px;"><?php echo esc_html( $item->icon ); ?></td>
                        <td><?php if ( $item->image_url ) : ?><img src="<?php echo esc_url( $item->image_url ); ?>" style="height:40px;border-radius:6px;"><?php else : ?>—<?php endif; ?></td>
                        <td><strong><?php echo esc_html( $item->title ); ?></strong></td>
                        <td style="font-size:12px;color:#666;"><?php echo esc_html( $item->description ); ?></td>
                        <td>£<?php echo number_format( $item->price_1 / 100 ); ?> / £<?php echo number_format( $item->price_2 / 100 ); ?> / £<?php echo number_format( $item->price_3 / 100 ); ?></td>
                        <td><?php echo $item->is_active ? '<span style="color:#16a34a;">Yes</span>' : '<span style="color:#999;">No</span>'; ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ynj-store&edit=' . $item->id ) ); ?>">Edit</a> |
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ynj-store&delete=' . $item->id ), 'ynj_store_delete' ) ); ?>" onclick="return confirm('Delete this item?');" style="color:#dc2626;">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <script>
        function ynjStorePickImage() {
            var frame = wp.media({ title: 'Select Store Item Image', library: { type: 'image' }, multiple: false });
            frame.on('select', function() {
                var url = frame.state().get('selection').first().toJSON().url;
                document.getElementById('ynj-store-img').value = url;
            });
            frame.open();
        }
        </script>
        <?php
    }
}
