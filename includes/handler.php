<?php
/**
 * Form submission handler — processes POST, validates, sends email, redirects.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared state between handler (template_redirect) and shortcode (the_content).
 * Called with $set to store state, without args to read.
 *
 * @param array|null $set
 * @return array { errors: string[], values: string[] }
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

	// Nonce.
	$nonce = sanitize_text_field( wp_unslash( $_POST['mavo_contact_nonce'] ?? '' ) );
	if ( ! wp_verify_nonce( $nonce, 'mavo_contact_submit' ) ) {
		wp_die(
			esc_html__( 'Session expirée. Veuillez rafraîchir la page et réessayer.', 'mavo-contact' ),
			esc_html__( 'Erreur de sécurité', 'mavo-contact' ),
			[ 'response' => 403 ]
		);
	}

	// Honeypot — silently appear as success.
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
			'errors' => [ 'rate_limit' => __( 'Trop de messages ont été envoyés récemment. Merci de réessayer un peu plus tard.', 'mavo-contact' ) ],
			'values' => _mavo_contact_raw_values(),
		] );
		return;
	}

	// Sanitize.
	$name    = sanitize_text_field( wp_unslash( $_POST['mavo_contact_name'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['mavo_contact_email'] ?? '' ) );
	$reason  = sanitize_key( wp_unslash( $_POST['mavo_contact_reason'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['mavo_contact_message'] ?? '' ) );

	// Validate.
	$allowed_reasons = _mavo_contact_reasons();
	$errors          = [];

	if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 80 ) {
		$errors['mavo_contact_name'] = __( "Merci d'indiquer votre nom (2 à 80 caractères).", 'mavo-contact' );
	}
	if ( ! is_email( $email ) ) {
		$errors['mavo_contact_email'] = __( "Merci d'indiquer une adresse e-mail valide.", 'mavo-contact' );
	}
	if ( ! array_key_exists( $reason, $allowed_reasons ) ) {
		$errors['mavo_contact_reason'] = __( 'Merci de sélectionner un sujet.', 'mavo-contact' );
	}
	$msg_len = mb_strlen( $message );
	if ( $msg_len < 20 ) {
		$errors['mavo_contact_message'] = __( "Merci d'écrire un message d'au moins 20 caractères.", 'mavo-contact' );
	} elseif ( $msg_len > 5000 ) {
		$errors['mavo_contact_message'] = __( 'Le message est trop long (5 000 caractères maximum).', 'mavo-contact' );
	} elseif ( preg_match_all( '/https?:\/\//i', $message ) > 3 ) {
		$errors['mavo_contact_message'] = __( 'Le message contient trop de liens.', 'mavo-contact' );
	}

	if ( ! empty( $errors ) ) {
		mavo_contact_state( [
			'errors' => $errors,
			'values' => compact( 'name', 'email', 'reason', 'message' ),
		] );
		return;
	}

	// Build email.
	$reason_label = $allowed_reasons[ $reason ];
	$recipient    = apply_filters( 'mavo_contact_recipient_email', get_option( 'admin_email' ) );
	$site_domain  = (string) ( wp_parse_url( home_url(), PHP_URL_HOST ) ?? '' );
	$subject      = sprintf( '[Maman Voyage] Contact — %s', $reason_label );
	$page_url     = sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) );
	$user_agent   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );

	$body = implode( "\n", [
		__( 'Nouveau message depuis le formulaire de contact Maman Voyage', 'mavo-contact' ),
		'',
		sprintf( '%s : %s', __( 'Nom', 'mavo-contact' ), $name ),
		sprintf( '%s : %s', __( 'E-mail', 'mavo-contact' ), $email ),
		sprintf( '%s : %s', __( 'Sujet', 'mavo-contact' ), $reason_label ),
		sprintf( '%s : %s', __( 'Page', 'mavo-contact' ), $page_url ),
		'',
		__( 'Message :', 'mavo-contact' ),
		$message,
		'',
		'---',
		sprintf( 'IP : %s', $ip ),
		sprintf( 'User-Agent : %s', $user_agent ),
		sprintf( '%s : %s', __( 'Envoyé depuis', 'mavo-contact' ), get_site_url() ),
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

	// wp_mail() returned false — let the user know without revealing detail.
	mavo_contact_state( [
		'errors' => [
			'send_failed' => __( "Une erreur est survenue lors de l'envoi. Merci de réessayer ou d'utiliser l'adresse e-mail directe.", 'mavo-contact' ),
		],
		'values' => compact( 'name', 'email', 'reason', 'message' ),
	] );
}

/**
 * Reason labels. Used by handler and shortcode (keep in sync).
 *
 * @return array<string,string>
 */
function _mavo_contact_reasons(): array {
	return [
		'question-voyage'      => __( 'Question voyage', 'mavo-contact' ),
		'collaboration-presse' => __( 'Collaboration / presse', 'mavo-contact' ),
		'probleme-technique'   => __( 'Problème technique', 'mavo-contact' ),
		'autre'                => __( 'Autre message', 'mavo-contact' ),
	];
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

/**
 * Best-effort client IP. Uses REMOTE_ADDR; never trusts CF header blindly.
 */
function _mavo_contact_ip(): string {
	return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
}

/**
 * Canonical URL of the current queried page (without any query args).
 */
function _mavo_contact_page_url(): string {
	$id = get_queried_object_id();
	if ( $id ) {
		return (string) get_permalink( $id );
	}
	return home_url( '/a-propos/contactez-moi/' );
}
