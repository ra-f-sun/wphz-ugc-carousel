<?php
/**
 * Admin carousel list view template.
 *
 * @package WPHZ\UGC
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wphz-ugc-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'WPHZ UGC Carousels', 'wphz-ugc' ); ?></h1>
	
	<form method="POST" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:inline-block; margin-left: 10px;">
		<input type="hidden" name="action" value="wphz_ugc_create_carousel">
		<?php wp_nonce_field( 'wphz_ugc_admin', 'wphz_nonce' ); ?>
		<input type="text" name="name" placeholder="New Carousel Name..." required>
		<button type="submit" class="page-title-action">Add New</button>
		<?php $wphz_msg = filter_input( INPUT_GET, 'wphz_msg', FILTER_SANITIZE_FULL_SPECIAL_CHARS ); ?>
		<?php if ( 'deleted' === $wphz_msg ) : ?>
			<span style="color:red; margin-left: 10px;">Carousel deleted.</span>
		<?php endif; ?>
	</form>

	<hr class="wp-header-end">

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th class="manage-column column-primary">Name</th>
				<th class="manage-column">Shortcode</th>
				<th class="manage-column">Action</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $carousels ) ) : ?>
				<tr>
					<td colspan="3">No carousels found. Create one above!</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $carousels as $c ) : ?>
					<tr>
						<td class="column-primary">
							<strong><a href="?page=wphz-ugc-carousel&action=edit&id=<?php echo esc_attr( $c['id'] ); ?>" class="row-title"><?php echo esc_html( $c['name'] ); ?></a></strong>
							<div class="row-actions">
								<span class="edit"><a href="?page=wphz-ugc-carousel&action=edit&id=<?php echo esc_attr( $c['id'] ); ?>">Edit</a> | </span>
								<?php
								/**
								 * List view row actions.
								 *
								 * @package WPHZ\\UGC
								 */

									$duplicate_url = wp_nonce_url(
										admin_url( 'admin-ajax.php?action=wphz_ugc_duplicate_carousel&id=' . $c['id'] ),
										'wphz_ugc_admin',
										'wphz_nonce'
									);
								?>
								<span class="duplicate"><a href="<?php echo esc_url( $duplicate_url ); ?>" onclick="return confirm('Duplicate this carousel?');">Duplicate</a></span>
							</div>
						</td>
						<td><code>[wphz_ugc_carousel id="<?php echo esc_attr( $c['id'] ); ?>"]</code></td>
						<td>
							<?php
								$delete_url = wp_nonce_url(
									admin_url( 'admin-ajax.php?action=wphz_ugc_delete_carousel&id=' . $c['id'] ),
									'wphz_ugc_admin',
									'wphz_nonce'
								);
							?>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-link-delete" onclick="return confirm('Are you sure?');">Delete</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
