<?php
/**
 * Popup for Subscribe.
 *
 * @package UrlImageImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="modal fade" id="subscribe-modal" tabindex="-1" role="dialog" aria-labelledby="subscribe-modal-label" aria-hidden="true">
	
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-body">
				<div class="container-fluid">
					<form action="https://infiniteuploads.us10.list-manage.com/subscribe/post?u=c50f189b795383e791f477637&amp;id=4f5e536a46&amp;SOURCE=BFU_Plugin" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank" novalidate>

						<div class="row justify-content-center mb-4 mt-3">
							<div class="col text-center">
								<h4><?php esc_html_e( 'Get Media Management Tips & Tricks', 'url-image-importer' ); ?></h4>
								<p class="lead"><?php esc_html_e( 'Subscribe to receive tips for managing large files in WordPress and making your media library infinitely scalable with cloud storage from Infinite Uploads.', 'url-image-importer' ); ?></p>
							</div>
						</div>
						<div class="row">
							<div class="col text-center">
								<div id="mce-responses">
									<div id="mce-error-response" class="response alert alert-warning alert-dismissible fade show" role="alert" style="display:none"></div>
									<div id="mce-success-response" class="response alert alert-success alert-dismissible fade show" role="alert" style="display:none"></div>
								</div>
							</div>
						</div>
						<div class="row justify-content-center">
							<div class="col-xl-8 col-lg-9 col-md-10 text-center">
									<div style="position: absolute; left: -5000px;" aria-hidden="true">
										<input type="text" name="b_c50f189b795383e791f477637_4f5e536a46" tabindex="-1" value="">
									</div>
									<label for="mce-EMAIL" class="sr-only"><?php esc_html_e( 'Email Address', 'url-image-importer' ); ?></label>
									<input type="email" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" name="EMAIL" class="required email bfu-input-subscribe" id="mce-EMAIL" placeholder="<?php esc_attr_e( 'Email Address', 'url-image-importer' ); ?>">
							</div>
						</div>
						<div class="row text-center mb-4">
							<div class="col text-muted">
								
							</div>
						</div>
						<div class="row justify-content-center mt-4">
							<div class="col-md-8 col-md-7 col-xl-6 text-center">
								<button id="bfu-subscribe-button" class="btn text-nowrap btn-primary btn-lg" type="submit"><?php esc_html_e( 'Subscribe & View Results', 'url-image-importer' ); ?></button>
							</div>
						</div>
						<div class="row text-center mb-4">
							<div class="col text-muted">
								<small>
									<a id="bfu-view-results" role="button"><?php esc_html_e( 'No thanks, view results without subscribing.', 'url-image-importer' ); ?></a>
								</small>
							</div>
						</div>

					</form>
				</div>
			</div>
		</div>
	</div>
</div>
