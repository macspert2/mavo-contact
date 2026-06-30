<?php
/**
 * Form submission handler — processes POST, validates, sends email, redirects.
 * User-facing error messages are localised via _mavo_contact_t().
 * Email body is always in French (admin is French-speaking).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared state between handler (template_redirect) and shortcode (the_content).
 * Called with $set to store, without args to read.
 *
 * @param array|null $set
 * @return array { errors: string[], values: string[], lang: string }
 */
function mavo_contact_state( ?array $set = null ): array {
	static $s = [];
	if ( null !== $set ) {
		$s = $set;
	}
	return $s;
}

add_action( 'template_redirect', 'mavo_contact_maybe_handle' );

function mavo_contact_maybe_handle(): void {
	if ( empty( $_POST['mavo_contact_submit'] ) ) {
		return;
	}

	// Capture language at submission time so the shortcode re-renders
	// in the same language even if Polylang state changes during the request.
	$lang = _mavo_contact_lang();

	// Nonce.
	$nonce = sanitize_text_field( wp_unslash( $_POST['mavo_contact_nonce'] ?? '' ) );
	if ( ! wp_verify_nonce( $nonce, 'mavo_contact_submit' ) ) {
		wp_die(
			esc_html( _mavo_contact_t( 'session_expired', $lang ) ),
			esc_html( _mavo_contact_t( 'security_error', $lang ) ),
			[ 'response' => 403 ]
		);
	}

	// Honeypot — silently appear as success to bots.
	if ( ! empty( $_POST['mavo_contact_website'] ) ) {
		wp_safe_redirect( add_query_arg( 'mavo_contact', 'sent', _mavo_contact_page_url() ) );
		exit;
	}

	// Minimum fill time — bots often submit instantly.
	$started_at = absint( $_POST['mavo_contact_started_at'] ?? 0 );
	if ( $started_at && ( time() - $started_at ) < 3 ) {
		wp_safe_redirect( add_query_arg( 'mavo_contact', 'sent', _mavo_contact_page_url() ) );
		exit;
	}

	// Rate limit: max 3 submissions per IP per hour.
	$ip    = _mavo_contact_ip();
	$rkey  = 'mavo_contact_rate_' . md5( $ip );
	$count = (int) get_transient( $rkey );
	if ( $count >= 3 ) {
		mavo_contact_state( [
			'lang'   => $lang,
			'errors' => [ 'rate_limit' => _mavo_contact_t( 'error_rate_limit', $lang ) ],
			'values' => _mavo_contact_raw_values(),
		] );
		return;
	}

	// Sanitize.
	$name    = sanitize_text_field( wp_unslash( $_POST['mavo_contact_name'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['mavo_contact_email'] ?? '' ) );
	$reason  = sanitize_key( wp_unslash( $_POST['mavo_contact_reason'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['mavo_contact_message'] ?? '' ) );

	// Validate — error messages in visitor's language.
	$allowed_reasons = _mavo_contact_reasons( $lang );
	$errors          = [];

	if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 80 ) {
		$errors['mavo_contact_name'] = _mavo_contact_t( 'error_name', $lang );
	}
	if ( ! is_email( $email ) ) {
		$errors['mavo_contact_email'] = _mavo_contact_t( 'error_email', $lang );
	}
	if ( ! array_key_exists( $reason, $allowed_reasons ) ) {
		$errors['mavo_contact_reason'] = _mavo_contact_t( 'error_reason', $lang );
	}
	$msg_len = mb_strlen( $message );
	if ( $msg_len < 20 ) {
		$errors['mavo_contact_message'] = _mavo_contact_t( 'error_msg_short', $lang );
	} elseif ( $msg_len > 5000 ) {
		$errors['mavo_contact_message'] = _mavo_contact_t( 'error_msg_long', $lang );
	} elseif ( preg_match_all( '/https?:\/\//i', $message ) > 3 ) {
		$errors['mavo_contact_message'] = _mavo_contact_t( 'error_msg_links', $lang );
	}

	if ( ! empty( $errors ) ) {
		mavo_contact_state( [
			'lang'   => $lang,
			'errors' => $errors,
			'values' => compact( 'name', 'email', 'reason', 'message' ),
		] );
		return;
	}

	// Email body always in French — admin is French-speaking.
	// Use the French reason label regardless of submission language.
	$reason_label_fr = _mavo_contact_reasons( 'fr' )[ $reason ] ?? $reason;
	$recipient       = apply_filters( 'mavo_contact_recipient_email', get_option( 'admin_email' ) );
	$site_domain     = (string) ( wp_parse_url( home_url(), PHP_URL_HOST ) ?? '' );
	$subject         = sprintf( '[Maman Voyage] Contact — %s', $reason_label_fr );
	$page_url        = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) );
	$user_agent      = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );

	$body = implode( "\n", [
		'Nouveau message depuis le formulaire de contact Maman Voyage',
		'',
		"Nom : {$name}",
		"E-mail : {$email}",
		"Sujet : {$reason_label_fr}",
		"Langue : {$lang}",
		"Page : {$page_url}",
		'',
		'Message :',
		$message,
		'',
		'---',
		"IP : {$ip}",
		"User-Agent : {$user_agent}",
		'Envoyé depuis : ' . get_site_url(),
	] );

	$headers = [
		'Content-Type: text/plain; charset=UTF-8',
		sprintf( 'From: Maman Voyage <no-reply@%s>', $site_domain ),
		sprintf( 'Reply-To: %s <%s>', $name, $email ),
	];

	$sent = wp_mail( $recipient, $subject, $body, $headers );

	if ( $sent ) {
		set_transient( $rkey, $count + 1, HOUR_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'mavo_contact', 'sent', _mavo_contact_page_url() ) );
		exit;
	}

	// wp_mail() returned false.
	mavo_contact_state( [
		'lang'   => $lang,
		'errors' => [ 'send_failed' => _mavo_contact_t( 'error_send_failed', $lang ) ],
		'values' => compact( 'name', 'email', 'reason', 'message' ),
	] );
}

/**
 * Raw sanitized values for re-populating form on validation error.
 *
 * @return array<string,string>
 */
function _mavo_contact_raw_values(): array {
	return [
		'name'    => sanitize_text_field( wp_unslash( $_POST['mavo_contact_name'] ?? '' ) ),
		'email'   => sanitize_email( wp_unslash( $_POST['mavo_contact_email'] ?? '' ) ),
		'reason'  => sanitize_key( wp_unslash( $_POST['mavo_contact_reason'] ?? '' ) ),
		'message' => sanitize_textarea_field( wp_unslash( $_POST['mavo_contact_message'] ?? '' ) ),
	];
}

/** Best-effort client IP — trusts only REMOTE_ADDR. */
function _mavo_contact_ip(): string {
	return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
}

/** Canonical URL of the current queried page (no query args). */
function _mavo_contact_page_url(): string {
	$id = get_queried_object_id();
	if ( $id ) {
		return (string) get_permalink( $id );
	}
	return home_url( '/a-propos/contactez-moi/' );
}
