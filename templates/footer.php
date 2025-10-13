<?php
/**
 * Footer details.
 *
 * @package UrlImageImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
		exit;
}
?>
<div id="iup-footer" class="container mt-5">
	<div class="row">
		<div class="col-sm text-center text-muted">
			<strong>
				<?php
					// translators: %1$s is for the heart symbol.
					printf( esc_html__( 'Made with %1$s by Infinite Uploads', 'url-image-importer' ), '<span class="dashicons dashicons-heart"></span>' );
				?>
			</strong>
		</div>
	</div>
	<div class="row mt-3">
		<div class="col-sm text-center text-muted">
			<a target="_new" href="<?php echo esc_url( 'https://infiniteuploads.com//support/?utm_source=bfu_plugin&utm_medium=plugin&utm_campaign=bfu_plugin&utm_content=footer&utm_term=support' ); ?>" class="text-muted"><?php esc_html_e( 'Support', 'url-image-importer' ); ?></a> |
			<a target="_new" href="<?php echo esc_url( 'https://infiniteuploads.com//terms-of-service/?utm_source=bfu_plugin&utm_medium=plugin&utm_campaign=bfu_plugin&utm_content=footer&utm_term=terms' ); ?>" class="text-muted"><?php esc_html_e( 'Terms of Service', 'url-image-importer' ); ?></a> |
			<a target="_new" href="<?php echo esc_url( 'https://infiniteuploads.com/privacy/?utm_source=bfu_plugin&utm_medium=plugin&utm_campaign=bfu_plugin&utm_content=footer&utm_term=privacy' ); ?>" class="text-muted"><?php esc_html_e( 'Privacy Policy', 'url-image-importer' ); ?></a>
		</div>
	</div>
	<div class="row mt-3">
		<div class="col-sm text-center text-muted">
			<a target="_new" href="https://twitter.com/infiniteuploads" class="text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Twitter', 'url-image-importer' ); ?>"><span class="dashicons dashicons-twitter"></span></a>
			<a target="_new" href="https://www.facebook.com/infiniteuploads/" class="text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Facebook', 'url-image-importer' ); ?>"><span class="dashicons dashicons-facebook-alt"></span></a>
		</div>
	</div>
</div>
