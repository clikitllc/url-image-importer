<?php
/**
 * Popup for Upgrade.
 *
 * @package UrlImageImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="modal fade" id="upgrade-modal" tabindex="-1" role="dialog" aria-labelledby="upgrade-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body cloud">
				<div class="container-fluid">
					<div class="row justify-content-center mb-3 mt-3">
						<div class="col text-center">
						<img class="mb-4" src="<?php echo esc_url( plugins_url( '/assets/img/iu-logo-blue.svg', dirname( __FILE__ ) ) ); ?>" alt="Infinite Uploads Logo" height="76" width="76"/>
							<h4><?php esc_html_e( 'Effortlessly Offload Your Media Library to the Cloud', 'url-image-importer' ); ?></h4>
							<p class="lead">
								<?php esc_html_e( 'Infinite Uploads is a cloud storage and CDN delivery provider for your WordPress media library and other uploads. The Infinite Uploads plugin allows you to easily connect an unlimited number of sites to our cloud for offloading your files, lowering hosting costs, improving site performance, and serving files faster to your visitors.', 'url-image-importer' ); ?>
								
						</div>
					</div>
					<div class="row justify-content-center mb-3">
						<div class="col text-center">
							<?php
							if ( current_user_can( 'install_plugins' ) ) {
								$installed_plugins = get_plugins();
								if ( array_key_exists( 'infinite-uploads/infinite-uploads.php', $installed_plugins ) || in_array( 'infinite-uploads/infinite-uploads.php', $installed_plugins, true ) ) {
									if ( class_exists( 'Infinite_Uploads_Admin' ) ) {
										$url = Infinite_Uploads_Admin::get_instance()->settings_url();
										?>
										<a class="btn text-nowrap btn-primary btn-lg mb-2" href="<?php echo esc_url( $url ); ?>" role="button"><?php esc_html_e( 'Configure Infinite Uploads', 'url-image-importer' ); ?></a>
										<?php
									} else {
										$url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . urlencode( 'infinite-uploads/infinite-uploads.php' ), 'activate-plugin_infinite-uploads/infinite-uploads.php' );
										?>
										<a class="btn text-nowrap btn-primary btn-lg mb-2" href="<?php echo esc_url( $url ); ?>" role="button"><?php esc_html_e( 'Activate Infinite Uploads', 'url-image-importer' ); ?></a>
										<?php
									}
								} else {
									$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=infinite-uploads' ), 'install-plugin_infinite-uploads' );
									?>
									<a class="btn text-nowrap btn-primary btn-lg mb-2" href="<?php echo esc_url( $url ); ?>" role="button"><?php esc_html_e( 'Install Infinite Uploads', 'url-image-importer' ); ?></a>
									<?php
								}
								?>
							<?php } ?>
							<p>
								<small class="text-muted">
									<?php
										// translators: %1$s is for the cloud icon.
										printf( esc_html__( 'Get 7 days of %1$s storage FREE. Plans starting at just $16/mo', 'url-image-importer' ), '<span class="dashicons dashicons-cloud"></span>' );
									?>
								</small>
							</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
