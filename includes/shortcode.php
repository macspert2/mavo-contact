<?php
/**
 * [mavo_contact_form] shortcode — renders the contact form or success notice.
 */

defined( 'ABSPATH' ) || exit;

// Register CSS globally so it is ready to enqueue; actual enqueue happens
// inside the shortcode so it only loads on pages where the shortcode runs.
add_action( 'wp_enqueue_scripts', static function () {
	wp_register_style(
		'mavo-contact',
		MAVO_CONTACT_URL . 'assets/css/mavo-contact.css',
		[],
		MAVO_CONTACT_VERSION
	);
} );

add_shortcode( 'mavo_contact_form', 'mavo_contact_form_shortcode' );

function mavo_contact_form_shortcode( array $atts = [] ): string {
	wp_enqueue_style( 'mavo-contact' );

	$atts = shortcode_atts( [], $atts, 'mavo_contact_form' );

	// Success state after PRG redirect.
	if ( isset( $_GET['mavo_contact'] ) && 'sent' === $_GET['mavo_contact'] ) {
		return _mavo_contact_render_success();
	}

	$state  = mavo_contact_state();
	$errors = $state['errors'] ?? [];
	$values = $state['values'] ?? [];

	return _mavo_contact_render_form( $errors, $values );
}

function _mavo_contact_render_success(): string {
	ob_start();
	?>
	<section class="mv-contact" aria-label="<?php esc_attr_e( 'Formulaire de contact', 'mavo-contact' ); ?>">
		<div class="mv-contact-notice mv-contact-notice--success" role="status">
			<span class="mv-contact-notice__icon" aria-hidden="true">✓</span>
			<?php esc_html_e( 'Merci, votre message a bien été envoyé. Je vous répondrai dès que possible.', 'mavo-contact' ); ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

function _mavo_contact_render_form( array $errors, array $values ): string {
	$reasons       = _mavo_contact_reasons();
	$has_errors    = ! empty( $errors );
	$general_error = $errors['rate_limit'] ?? $errors['send_failed'] ?? null;

	ob_start();
	?>
	<section class="mv-contact" aria-labelledby="mv-contact-title">

		<?php if ( $has_errors ) : ?>
		<div class="mv-contact-notice mv-contact-notice--error" role="alert">
			<?php if ( $general_error ) : ?>
				<?php echo esc_html( $general_error ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Merci de vérifier les champs indiqués ci-dessous.', 'mavo-contact' ); ?>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<form class="mv-contact__form" method="post" action="">

			<?php wp_nonce_field( 'mavo_contact_submit', 'mavo_contact_nonce' ); ?>
			<input type="hidden" name="mavo_contact_submit" value="1">
			<input type="hidden" name="mavo_contact_started_at" value="<?php echo esc_attr( (string) time() ); ?>">

			<!-- Honeypot — hidden from real users and screen readers -->
			<div class="mv-contact__trap" aria-hidden="true">
				<label for="mavo_contact_website">Site web</label>
				<input type="text" id="mavo_contact_website" name="mavo_contact_website" tabindex="-1" autocomplete="off">
			</div>

			<h2 id="mv-contact-title" class="mv-contact__heading">
				<?php esc_html_e( 'Écrivez-moi', 'mavo-contact' ); ?>
			</h2>

			<p class="mv-contact__required-note">
				<span aria-hidden="true">*</span> <?php esc_html_e( 'Les champs marqués d\'un * sont obligatoires.', 'mavo-contact' ); ?>
			</p>

			<!-- Name + Email — two columns on desktop -->
			<div class="mv-contact__grid mv-contact__grid--two">

				<div class="mv-form-field <?php echo $errors['mavo_contact_name'] ?? false ? 'mv-form-field--error' : ''; ?>">
					<label for="mavo_contact_name">
						<?php esc_html_e( 'Votre nom', 'mavo-contact' ); ?>
						<span aria-hidden="true"> *</span>
					</label>
					<input
						id="mavo_contact_name"
						name="mavo_contact_name"
						type="text"
						autocomplete="name"
						required
						maxlength="80"
						value="<?php echo esc_attr( $values['name'] ?? '' ); ?>"
						<?php if ( isset( $errors['mavo_contact_name'] ) ) : ?>
						aria-describedby="mavo_contact_name_error"
						aria-invalid="true"
						<?php endif; ?>
					>
					<?php if ( isset( $errors['mavo_contact_name'] ) ) : ?>
					<span id="mavo_contact_name_error" class="mv-form-field__error" role="alert">
						<?php echo esc_html( $errors['mavo_contact_name'] ); ?>
					</span>
					<?php endif; ?>
				</div>

				<div class="mv-form-field <?php echo $errors['mavo_contact_email'] ?? false ? 'mv-form-field--error' : ''; ?>">
					<label for="mavo_contact_email">
						<?php esc_html_e( 'Votre adresse e-mail', 'mavo-contact' ); ?>
						<span aria-hidden="true"> *</span>
					</label>
					<input
						id="mavo_contact_email"
						name="mavo_contact_email"
						type="email"
						autocomplete="email"
						required
						maxlength="254"
						value="<?php echo esc_attr( $values['email'] ?? '' ); ?>"
						<?php if ( isset( $errors['mavo_contact_email'] ) ) : ?>
						aria-describedby="mavo_contact_email_error"
						aria-invalid="true"
						<?php endif; ?>
					>
					<?php if ( isset( $errors['mavo_contact_email'] ) ) : ?>
					<span id="mavo_contact_email_error" class="mv-form-field__error" role="alert">
						<?php echo esc_html( $errors['mavo_contact_email'] ); ?>
					</span>
					<?php endif; ?>
				</div>

			</div><!-- /.mv-contact__grid--two -->

			<!-- Reason -->
			<div class="mv-form-field <?php echo $errors['mavo_contact_reason'] ?? false ? 'mv-form-field--error' : ''; ?>">
				<label for="mavo_contact_reason">
					<?php esc_html_e( 'Sujet de votre message', 'mavo-contact' ); ?>
					<span aria-hidden="true"> *</span>
				</label>
				<select
					id="mavo_contact_reason"
					name="mavo_contact_reason"
					required
					<?php if ( isset( $errors['mavo_contact_reason'] ) ) : ?>
					aria-describedby="mavo_contact_reason_error"
					aria-invalid="true"
					<?php endif; ?>
				>
					<option value="" disabled <?php selected( '', $values['reason'] ?? '' ); ?>>
						— <?php esc_html_e( 'Choisir un sujet', 'mavo-contact' ); ?> —
					</option>
					<?php foreach ( $reasons as $rval => $rlabel ) : ?>
					<option value="<?php echo esc_attr( $rval ); ?>" <?php selected( $rval, $values['reason'] ?? '' ); ?>>
						<?php echo esc_html( $rlabel ); ?>
					</option>
					<?php endforeach; ?>
				</select>
				<?php if ( isset( $errors['mavo_contact_reason'] ) ) : ?>
				<span id="mavo_contact_reason_error" class="mv-form-field__error" role="alert">
					<?php echo esc_html( $errors['mavo_contact_reason'] ); ?>
				</span>
				<?php endif; ?>
			</div>

			<p class="mv-contact__collab-note">
				<?php
				printf(
					/* translators: %s = URL of "Travailler avec Maman Voyage" page */
					esc_html__( 'Pour une collaboration, une demande presse ou un partenariat, vous pouvez aussi consulter la page %s.', 'mavo-contact' ),
					'<a href="' . esc_url( apply_filters( 'mavo_contact_collab_url', 'https://www.mamanvoyage.com/travailler-avec-maman-voyage/' ) ) . '">'
					. esc_html__( 'Travailler avec Maman Voyage', 'mavo-contact' )
					. '</a>'
				);
				?>
			</p>

			<!-- Message -->
			<div class="mv-form-field <?php echo $errors['mavo_contact_message'] ?? false ? 'mv-form-field--error' : ''; ?>">
				<label for="mavo_contact_message">
					<?php esc_html_e( 'Votre message', 'mavo-contact' ); ?>
					<span aria-hidden="true"> *</span>
				</label>
				<textarea
					id="mavo_contact_message"
					name="mavo_contact_message"
					rows="7"
					required
					maxlength="5000"
					<?php if ( isset( $errors['mavo_contact_message'] ) ) : ?>
					aria-describedby="mavo_contact_message_error"
					aria-invalid="true"
					<?php endif; ?>
				><?php echo esc_textarea( $values['message'] ?? '' ); ?></textarea>
				<?php if ( isset( $errors['mavo_contact_message'] ) ) : ?>
				<span id="mavo_contact_message_error" class="mv-form-field__error" role="alert">
					<?php echo esc_html( $errors['mavo_contact_message'] ); ?>
				</span>
				<?php endif; ?>
			</div>

			<!-- Submit -->
			<div class="mv-contact__actions">
				<button type="submit" name="mavo_contact_submit" class="mv-contact__submit">
					<?php esc_html_e( 'Envoyer mon message', 'mavo-contact' ); ?>
				</button>
			</div>

			<!-- Privacy note -->
			<p class="mv-contact__privacy">
				<?php esc_html_e( 'Les informations envoyées via ce formulaire servent uniquement à vous répondre. Elles ne sont pas revendues, utilisées pour une newsletter, ni transmises à un service externe de gestion de formulaires.', 'mavo-contact' ); ?>
			</p>

		</form>

		<?php
		$obf = _mavo_contact_obfuscated_email_html();
		if ( $obf ) :
		?>
		<p class="mv-contact__email-alt">
			<?php esc_html_e( "Vous pouvez aussi m'écrire directement à cette adresse :", 'mavo-contact' ); ?>
			<?php echo $obf; // already escaped inside the function ?>
		</p>
		<?php endif; ?>

	</section>
	<?php
	return ob_get_clean();
}

/**
 * Renders an obfuscated email link using the CSS RTL text-reversal technique.
 * Screen readers get the aria-label; scrapers see a reversed string in the HTML.
 * The mailto: href is fully HTML-entity-encoded.
 *
 * Source email: apply_filters( 'mavo_contact_public_email', admin_email )
 */
function _mavo_contact_obfuscated_email_html(): string {
	$email = (string) apply_filters( 'mavo_contact_public_email', get_option( 'admin_email' ) );
	if ( ! is_email( $email ) ) {
		return '';
	}

	$parts = explode( '@', $email, 2 );
	if ( 2 !== count( $parts ) ) {
		return '';
	}

	// Entity-encode the full mailto: URI so it is not plain text in the source.
	$raw_href  = 'mailto:' . $email;
	$enc_href  = '';
	$href_len  = strlen( $raw_href );
	for ( $i = 0; $i < $href_len; $i++ ) {
		$enc_href .= '&#' . ord( $raw_href[ $i ] ) . ';';
	}

	// Reverse the visible email string — CSS `direction:rtl` displays it
	// correctly but HTML source shows the reversed form, confusing scrapers.
	$reversed = strrev( $email ); // safe: email addresses are always ASCII

	$aria = sprintf(
		/* translators: %1$s = email user part, %2$s = domain part */
		__( 'Écrire à %1$s [at] %2$s', 'mavo-contact' ),
		$parts[0],
		$parts[1]
	);

	return sprintf(
		'<a href="%s" class="mv-obf-email" aria-label="%s"><span aria-hidden="true" class="mv-obf-text">%s</span></a>',
		$enc_href,
		esc_attr( $aria ),
		esc_html( $reversed )
	);
}
