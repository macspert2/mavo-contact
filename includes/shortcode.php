<?php
/**
 * [mavo_contact_form] shortcode — renders the contact form or success notice.
 * All UI strings come from _mavo_contact_t() in i18n.php.
 */

defined( 'ABSPATH' ) || exit;

// Register CSS globally; actual enqueue happens inside the shortcode.
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

	// Determine language: use state lang if set (validation error path),
	// otherwise current Polylang language.
	$state = mavo_contact_state();
	$lang  = $state['lang'] ?? _mavo_contact_lang();

	// Success state after PRG redirect.
	if ( isset( $_GET['mavo_contact'] ) && 'sent' === $_GET['mavo_contact'] ) {
		return _mavo_contact_render_success( $lang );
	}

	$errors = $state['errors'] ?? [];
	$values = $state['values'] ?? [];

	return _mavo_contact_render_form( $lang, $errors, $values );
}

function _mavo_contact_render_success( string $lang ): string {
	ob_start();
	?>
	<section class="mv-contact" aria-label="<?php echo esc_attr( _mavo_contact_t( 'heading', $lang ) ); ?>">
		<div class="mv-contact-notice mv-contact-notice--success" role="status">
			<span class="mv-contact-notice__icon" aria-hidden="true">✓</span>
			<?php echo esc_html( _mavo_contact_t( 'success', $lang ) ); ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

function _mavo_contact_render_form( string $lang, array $errors, array $values ): string {
	$reasons       = _mavo_contact_reasons( $lang );
	$has_errors    = ! empty( $errors );
	$general_error = $errors['rate_limit'] ?? $errors['send_failed'] ?? null;

	ob_start();
	?>
	<section class="mv-contact" aria-labelledby="mv-contact-title">

		<?php if ( $has_errors ) : ?>
		<div class="mv-contact-notice mv-contact-notice--error" role="alert">
			<?php echo esc_html( $general_error ?? _mavo_contact_t( 'error_summary', $lang ) ); ?>
		</div>
		<?php endif; ?>

		<form class="mv-contact__form" method="post" action="">

			<?php wp_nonce_field( 'mavo_contact_submit', 'mavo_contact_nonce' ); ?>
			<input type="hidden" name="mavo_contact_submit" value="1">
			<input type="hidden" name="mavo_contact_started_at" value="<?php echo esc_attr( (string) time() ); ?>">

			<!-- Honeypot — hidden from real users and screen readers -->
			<div class="mv-contact-hp" aria-hidden="true">
				<label for="mv_contact_website">Website</label>
				<input type="text" id="mv_contact_website" name="website" tabindex="-1" autocomplete="off">
			</div>

			<h2 id="mv-contact-title" class="mv-contact__heading">
				<?php echo esc_html( _mavo_contact_t( 'heading', $lang ) ); ?>
			</h2>

			<p class="mv-contact__required-note">
				<span aria-hidden="true">*</span> <?php echo esc_html( _mavo_contact_t( 'required_note', $lang ) ); ?>
			</p>

			<!-- Name + Email — two columns on desktop -->
			<div class="mv-contact__grid mv-contact__grid--two">

				<?php _mavo_contact_field_text( 'name', $lang, $errors, $values ); ?>
				<?php _mavo_contact_field_email( $lang, $errors, $values ); ?>

			</div>

			<?php _mavo_contact_field_reason( $lang, $reasons, $errors, $values ); ?>

			<?php
			// Collaboration helper — only shown on French (no EN/DE translation of that page).
			if ( 'fr' === $lang ) :
				$collab_url  = apply_filters( 'mavo_contact_collab_url', _mavo_contact_t( 'collab_url', 'fr' ) );
				$collab_text = _mavo_contact_t( 'collab_link_text', 'fr' );
				$collab_note = _mavo_contact_t( 'collab_note', 'fr' );
			?>
			<p class="mv-contact__collab-note">
				<?php
				printf(
					esc_html( $collab_note ),
					'<a href="' . esc_url( $collab_url ) . '">' . esc_html( $collab_text ) . '</a>'
				);
				?>
			</p>
			<?php endif; ?>

			<?php _mavo_contact_field_message( $lang, $errors, $values ); ?>

			<div class="mv-contact__actions">
				<button type="submit" class="mv-contact__submit">
					<?php echo esc_html( _mavo_contact_t( 'submit', $lang ) ); ?>
				</button>
			</div>

			<p class="mv-contact__privacy">
				<?php echo esc_html( _mavo_contact_t( 'privacy', $lang ) ); ?>
			</p>

		</form>

		<?php
		$obf = _mavo_contact_obfuscated_email_html( $lang );
		if ( $obf ) :
		?>
		<p class="mv-contact__email-alt">
			<?php echo esc_html( _mavo_contact_t( 'email_alt', $lang ) ); ?>
			<?php echo $obf; // escaped inside the function ?>
		</p>
		<?php endif; ?>

	</section>
	<?php
	return ob_get_clean();
}

// ---------------------------------------------------------------------------
// Field renderers — extracted to keep _mavo_contact_render_form() readable.
// ---------------------------------------------------------------------------

function _mavo_contact_field_text( string $key, string $lang, array $errors, array $values ): void {
	$id        = 'mavo_contact_' . $key;
	$has_error = isset( $errors[ $id ] );
	$label_key = 'label_' . $key; // e.g. 'label_name'
	?>
	<div class="mv-form-field <?php echo $has_error ? 'mv-form-field--error' : ''; ?>">
		<label for="<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( _mavo_contact_t( $label_key, $lang ) ); ?>
			<span aria-hidden="true"> *</span>
		</label>
		<input
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $id ); ?>"
			type="text"
			autocomplete="<?php echo esc_attr( $key ); ?>"
			required
			maxlength="80"
			value="<?php echo esc_attr( $values[ $key ] ?? '' ); ?>"
			<?php if ( $has_error ) : ?>
			aria-describedby="<?php echo esc_attr( $id . '_error' ); ?>"
			aria-invalid="true"
			<?php endif; ?>
		>
		<?php if ( $has_error ) : ?>
		<span id="<?php echo esc_attr( $id . '_error' ); ?>" class="mv-form-field__error" role="alert">
			<?php echo esc_html( $errors[ $id ] ); ?>
		</span>
		<?php endif; ?>
	</div>
	<?php
}

function _mavo_contact_field_email( string $lang, array $errors, array $values ): void {
	$id        = 'mavo_contact_email';
	$has_error = isset( $errors[ $id ] );
	?>
	<div class="mv-form-field <?php echo $has_error ? 'mv-form-field--error' : ''; ?>">
		<label for="<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( _mavo_contact_t( 'label_email', $lang ) ); ?>
			<span aria-hidden="true"> *</span>
		</label>
		<input
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $id ); ?>"
			type="email"
			autocomplete="email"
			required
			maxlength="254"
			value="<?php echo esc_attr( $values['email'] ?? '' ); ?>"
			<?php if ( $has_error ) : ?>
			aria-describedby="<?php echo esc_attr( $id . '_error' ); ?>"
			aria-invalid="true"
			<?php endif; ?>
		>
		<?php if ( $has_error ) : ?>
		<span id="<?php echo esc_attr( $id . '_error' ); ?>" class="mv-form-field__error" role="alert">
			<?php echo esc_html( $errors[ $id ] ); ?>
		</span>
		<?php endif; ?>
	</div>
	<?php
}

function _mavo_contact_field_reason( string $lang, array $reasons, array $errors, array $values ): void {
	$id        = 'mavo_contact_reason';
	$has_error = isset( $errors[ $id ] );
	?>
	<div class="mv-form-field <?php echo $has_error ? 'mv-form-field--error' : ''; ?>">
		<label for="<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( _mavo_contact_t( 'label_reason', $lang ) ); ?>
			<span aria-hidden="true"> *</span>
		</label>
		<select
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $id ); ?>"
			required
			<?php if ( $has_error ) : ?>
			aria-describedby="<?php echo esc_attr( $id . '_error' ); ?>"
			aria-invalid="true"
			<?php endif; ?>
		>
			<option value="" disabled <?php selected( '', $values['reason'] ?? '' ); ?>>
				<?php echo esc_html( _mavo_contact_t( 'reason_placeholder', $lang ) ); ?>
			</option>
			<?php foreach ( $reasons as $rval => $rlabel ) : ?>
			<option value="<?php echo esc_attr( $rval ); ?>" <?php selected( $rval, $values['reason'] ?? '' ); ?>>
				<?php echo esc_html( $rlabel ); ?>
			</option>
			<?php endforeach; ?>
		</select>
		<?php if ( $has_error ) : ?>
		<span id="<?php echo esc_attr( $id . '_error' ); ?>" class="mv-form-field__error" role="alert">
			<?php echo esc_html( $errors[ $id ] ); ?>
		</span>
		<?php endif; ?>
	</div>
	<?php
}

function _mavo_contact_field_message( string $lang, array $errors, array $values ): void {
	$id        = 'mavo_contact_message';
	$has_error = isset( $errors[ $id ] );
	?>
	<div class="mv-form-field <?php echo $has_error ? 'mv-form-field--error' : ''; ?>">
		<label for="<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( _mavo_contact_t( 'label_message', $lang ) ); ?>
			<span aria-hidden="true"> *</span>
		</label>
		<textarea
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $id ); ?>"
			rows="7"
			required
			maxlength="5000"
			<?php if ( $has_error ) : ?>
			aria-describedby="<?php echo esc_attr( $id . '_error' ); ?>"
			aria-invalid="true"
			<?php endif; ?>
		><?php echo esc_textarea( $values['message'] ?? '' ); ?></textarea>
		<?php if ( $has_error ) : ?>
		<span id="<?php echo esc_attr( $id . '_error' ); ?>" class="mv-form-field__error" role="alert">
			<?php echo esc_html( $errors[ $id ] ); ?>
		</span>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Obfuscated email link using CSS RTL text-reversal + entity-encoded href.
 * Screen readers get the aria-label; HTML source shows a reversed string.
 */
function _mavo_contact_obfuscated_email_html( string $lang ): string {
	$email = (string) apply_filters( 'mavo_contact_public_email', get_option( 'admin_email' ) );
	if ( ! is_email( $email ) ) {
		return '';
	}

	$parts = explode( '@', $email, 2 );
	if ( 2 !== count( $parts ) ) {
		return '';
	}

	// Entity-encode the full mailto: URI character by character.
	$enc_href = '';
	$raw_href = 'mailto:' . $email;
	for ( $i = 0, $len = strlen( $raw_href ); $i < $len; $i++ ) {
		$enc_href .= '&#' . ord( $raw_href[ $i ] ) . ';';
	}

	// Reversed string displayed via CSS direction:rtl.
	$reversed = strrev( $email ); // safe: email addresses are always ASCII

	$aria = sprintf(
		_mavo_contact_t( 'email_aria', $lang ),
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
