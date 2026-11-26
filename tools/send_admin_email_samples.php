<?php
$admin = 'freddarlington98@gmail.com';
$results = array();

$send = static function ( $key, $subject, $args ) use ( $admin, &$results ) {
	$args['admin']   = true;
	$args['content'] = ( $args['content'] ?? '' );
	$message         = eg_email_layout( $args );
	$results[ $key ] = wp_mail( $admin, '[MUSTER] ' . $subject, $message, eg_email_html_headers() );
};

$send(
	'admin_account',
	'Jemand hat sich angemeldet',
	array(
		'eyebrow'   => 'Neue Anmeldung',
		'heading'   => 'Jemand hat sich angemeldet',
		'intro'     => 'Fred Darlington ist neu dabei. Eine kurze Übersicht für Sie:',
		'content'   => eg_email_summary_rows(
			array(
				'Name'      => 'Fred Darlington',
				'Kontakt'   => 'freddarlington545@gmail.com',
				'Interesse' => 'Analysen, Veranstaltungen und Dialogformate',
			),
			'admin'
		) . '<p>Wenn Sie möchten, können Sie ihn persönlich begrüßen.</p>',
		'cta_url'   => eg_email_public_url(),
		'cta_text'  => 'Zur Website',
	)
);

$send(
	'admin_membership',
	'Eine neue Mitgliedschaft ist da',
	array(
		'eyebrow'   => 'Mitgliedschaft',
		'heading'   => 'Eine neue Mitgliedschaft ist da',
		'intro'     => 'Fred Darlington ist dem Mitgliederkreis beigetreten.',
		'content'   => eg_email_summary_rows(
			array(
				'Name'  => 'Fred Darlington',
				'Stufe' => 'Reader',
				'Seit'  => '1. September 2026',
			),
			'admin'
		) . '<p>Die Mitgliedschaft ist aktiv. Es ist nichts weiter zu tun, außer Sie möchten sich persönlich melden.</p>',
		'cta_url'   => eg_email_public_url( 'mitgliedschaft-vorteile.html' ),
		'cta_text'  => 'Mitgliedschaft ansehen',
	)
);

$send(
	'admin_shop',
	'Es gibt einen neuen Erwerb',
	array(
		'eyebrow'   => 'Shop',
		'heading'   => 'Es gibt einen neuen Erwerb',
		'intro'     => 'Fred Darlington hat soeben etwas im Shop erworben. Der Betrag beträgt 39,80 €.',
		'content'   => eg_email_summary_rows(
			array(
				'Name'    => 'Fred Darlington',
				'Inhalt'  => 'Eurasien Gesellschaft – Leseprobe',
				'Beitrag' => '39,80 €',
			),
			'admin'
		) . '<p>Der digitale Inhalt steht der Person bereits zur Verfügung.</p>',
		'cta_url'   => eg_email_public_url(),
		'cta_text'  => 'Zur Website',
	)
);

$send(
	'admin_event',
	'Eine neue Anmeldung ist da',
	array(
		'eyebrow'   => 'Veranstaltung',
		'heading'   => 'Eine neue Anmeldung ist da',
		'intro'     => 'Fred Darlington hat sich für „Dialog im eurasischen Raum“ angemeldet. Die Teilnahme ist bestätigt.',
		'content'   => eg_email_summary_rows(
			array(
				'Name'          => 'Fred Darlington',
				'Kontakt'       => 'freddarlington545@gmail.com',
				'Veranstaltung' => 'Dialog im eurasischen Raum',
				'Wann'          => '15. Oktober 2026, 19:00 Uhr',
				'Wo'            => 'Berlin',
				'Plätze'        => '1',
			),
			'admin'
		) . '<p>Wenn Sie möchten, können Sie sich kurz bei der Person melden. Alles Weitere ist bereits erledigt.</p>',
		'cta_url'   => eg_email_public_url( 'veranstaltungen.html' ),
		'cta_text'  => 'Veranstaltungen ansehen',
	)
);

$send(
	'admin_newsletter',
	'Jemand möchte auf dem Laufenden bleiben',
	array(
		'eyebrow'   => 'Nachrichten',
		'heading'   => 'Jemand möchte auf dem Laufenden bleiben',
		'intro'     => 'Fred Darlington möchte künftig Neuigkeiten und Einladungen erhalten.',
		'content'   => eg_email_summary_rows(
			array(
				'Name'    => 'Fred Darlington',
				'Kontakt' => 'freddarlington545@gmail.com',
			),
			'admin'
		) . '<p>Die Einwilligung liegt vor. Es ist nichts weiter zu tun.</p>',
		'cta_url'   => eg_email_public_url(),
		'cta_text'  => 'Zur Website',
	)
);

foreach ( $results as $key => $sent ) {
	printf( "%s=%s\n", $key, $sent ? 'sent' : 'failed' );
}

if ( in_array( false, $results, true ) ) {
	exit( 1 );
}
