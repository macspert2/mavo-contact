<?php
/**
 * i18n helpers for mavo-contact.
 * All UI strings for FR / EN / DE live here; no .mo files needed.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Current Polylang language slug ('fr', 'en', 'de'). Falls back to 'fr'.
 */
function _mavo_contact_lang(): string {
	if ( function_exists( 'pll_current_language' ) ) {
		$lang = (string) pll_current_language( 'slug' );
		if ( in_array( $lang, [ 'fr', 'en', 'de' ], true ) ) {
			return $lang;
		}
	}
	return 'fr';
}

/**
 * Return a translated UI string for the current (or given) language.
 * Falls back to French if the key is missing in the target language.
 *
 * @param string $key  String key defined in _mavo_contact_all_strings().
 * @param string $lang Override language; defaults to _mavo_contact_lang().
 */
function _mavo_contact_t( string $key, string $lang = '' ): string {
	static $all = null;
	if ( null === $all ) {
		$all = _mavo_contact_all_strings();
	}
	if ( '' === $lang ) {
		$lang = _mavo_contact_lang();
	}
	return (string) ( $all[ $lang ][ $key ] ?? $all['fr'][ $key ] ?? $key );
}

/**
 * Reason options (key → label) for a given language.
 * Keys are language-independent and used for validation; labels are localised.
 *
 * @param string $lang  '' = current language.
 * @return array<string,string>
 */
function _mavo_contact_reasons( string $lang = '' ): array {
	if ( '' === $lang ) {
		$lang = _mavo_contact_lang();
	}
	static $all = [
		'fr' => [
			'question-voyage'      => 'Question voyage',
			'collaboration-presse' => 'Collaboration / presse',
			'probleme-technique'   => 'Problème technique',
			'autre'                => 'Autre message',
		],
		'en' => [
			'question-voyage'      => 'Travel question',
			'collaboration-presse' => 'Collaboration / press',
			'probleme-technique'   => 'Technical issue',
			'autre'                => 'Other message',
		],
		'de' => [
			'question-voyage'      => 'Reisefrage',
			'collaboration-presse' => 'Zusammenarbeit / Presse',
			'probleme-technique'   => 'Technisches Problem',
			'autre'                => 'Andere Nachricht',
		],
	];
	return $all[ $lang ] ?? $all['fr'];
}

/**
 * All localised strings, keyed by language then string key.
 *
 * @return array<string,array<string,string>>
 */
function _mavo_contact_all_strings(): array {
	return [

		// ------------------------------------------------------------------ FR
		'fr' => [
			'heading'              => 'Écrivez-moi',
			'required_note'        => "Les champs marqués d'un * sont obligatoires.",
			'label_name'           => 'Votre nom',
			'label_email'          => 'Votre adresse e-mail',
			'label_reason'         => 'Sujet de votre message',
			'label_message'        => 'Votre message',
			'reason_placeholder'   => '— Choisir un sujet —',
			'submit'               => 'Envoyer mon message',
			'success'              => 'Merci, votre message a bien été envoyé. Je vous répondrai dès que possible.',
			'error_summary'        => 'Merci de vérifier les champs indiqués ci-dessous.',
			'error_name'           => "Merci d'indiquer votre nom (2 à 80 caractères).",
			'error_email'          => "Merci d'indiquer une adresse e-mail valide.",
			'error_reason'         => 'Merci de sélectionner un sujet.',
			'error_msg_short'      => "Merci d'écrire un message d'au moins 20 caractères.",
			'error_msg_long'       => 'Le message est trop long (5 000 caractères maximum).',
			'error_msg_links'      => 'Le message contient trop de liens.',
			'error_rate_limit'     => 'Trop de messages ont été envoyés récemment. Merci de réessayer un peu plus tard.',
			'error_send_failed'    => "Une erreur est survenue lors de l'envoi. Merci de réessayer ou d'utiliser l'adresse e-mail directe.",
			'privacy'              => "Les informations envoyées via ce formulaire servent uniquement à vous répondre. Elles ne sont pas revendues, utilisées pour une newsletter, ni transmises à un service externe de gestion de formulaires.",
			// Collaboration helper — only shown on FR (no EN/DE translation of that page exists).
			'collab_note'          => 'Pour une collaboration, une demande presse ou un partenariat, vous pouvez aussi consulter la page %s.',
			'collab_link_text'     => 'Travailler avec Maman Voyage',
			'collab_url'           => 'https://www.mamanvoyage.com/travailler-avec-maman-voyage/',
			'email_alt'            => "Vous pouvez aussi m'écrire directement à cette adresse :",
			'email_aria'           => 'Écrire à %1$s [at] %2$s',
			'session_expired'      => 'Session expirée. Veuillez rafraîchir la page et réessayer.',
			'security_error'       => 'Erreur de sécurité',
		],

		// ------------------------------------------------------------------ EN
		'en' => [
			'heading'              => 'Write to me',
			'required_note'        => 'Fields marked * are required.',
			'label_name'           => 'Your name',
			'label_email'          => 'Your email address',
			'label_reason'         => 'Subject',
			'label_message'        => 'Your message',
			'reason_placeholder'   => '— Choose a subject —',
			'submit'               => 'Send my message',
			'success'              => "Thank you, your message has been sent. I'll get back to you as soon as possible.",
			'error_summary'        => 'Please check the fields indicated below.',
			'error_name'           => 'Please enter your name (2 to 80 characters).',
			'error_email'          => 'Please enter a valid email address.',
			'error_reason'         => 'Please select a subject.',
			'error_msg_short'      => 'Please write a message of at least 20 characters.',
			'error_msg_long'       => 'The message is too long (5,000 characters maximum).',
			'error_msg_links'      => 'The message contains too many links.',
			'error_rate_limit'     => 'Too many messages have been sent recently. Please try again later.',
			'error_send_failed'    => 'An error occurred while sending. Please try again or use the direct email address.',
			'privacy'              => 'The information submitted via this form is used only to reply to you. It is not sold, used for newsletters, or sent to any external form management service.',
			// No collab page translation — keys omitted intentionally.
			'email_alt'            => 'You can also write to me directly at this address:',
			'email_aria'           => 'Write to %1$s [at] %2$s',
			'session_expired'      => 'Session expired. Please refresh the page and try again.',
			'security_error'       => 'Security error',
		],

		// ------------------------------------------------------------------ DE
		'de' => [
			'heading'              => 'Schreiben Sie mir',
			'required_note'        => 'Mit * markierte Felder sind Pflichtfelder.',
			'label_name'           => 'Ihr Name',
			'label_email'          => 'Ihre E-Mail-Adresse',
			'label_reason'         => 'Betreff',
			'label_message'        => 'Ihre Nachricht',
			'reason_placeholder'   => '— Bitte wählen —',
			'submit'               => 'Nachricht senden',
			'success'              => 'Vielen Dank, Ihre Nachricht wurde gesendet. Ich melde mich so bald wie möglich bei Ihnen.',
			'error_summary'        => 'Bitte überprüfen Sie die unten angezeigten Felder.',
			'error_name'           => 'Bitte geben Sie Ihren Namen ein (2 bis 80 Zeichen).',
			'error_email'          => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
			'error_reason'         => 'Bitte wählen Sie einen Betreff.',
			'error_msg_short'      => 'Bitte schreiben Sie eine Nachricht mit mindestens 20 Zeichen.',
			'error_msg_long'       => 'Die Nachricht ist zu lang (maximal 5.000 Zeichen).',
			'error_msg_links'      => 'Die Nachricht enthält zu viele Links.',
			'error_rate_limit'     => 'In letzter Zeit wurden zu viele Nachrichten gesendet. Bitte versuchen Sie es später erneut.',
			'error_send_failed'    => 'Beim Senden ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut oder verwenden Sie die direkte E-Mail-Adresse.',
			'privacy'              => 'Die über dieses Formular übermittelten Informationen dienen ausschließlich zur Beantwortung Ihrer Anfrage. Sie werden nicht verkauft, für Newsletter verwendet oder an externe Formularverwaltungsdienste weitergegeben.',
			// No collab page translation — keys omitted intentionally.
			'email_alt'            => 'Sie können mir auch direkt an diese Adresse schreiben:',
			'email_aria'           => 'An %1$s [at] %2$s schreiben',
			'session_expired'      => 'Sitzung abgelaufen. Bitte aktualisieren Sie die Seite und versuchen Sie es erneut.',
			'security_error'       => 'Sicherheitsfehler',
		],
	];
}
