<?php
/**
 * Contrôle de l'analyse sur les deux mises en page connues.
 *
 *   php tests/lancer.php            → les jeux d'essai livrés
 *   php tests/lancer.php f.docx     → inspecte un vrai fichier
 *
 * Fait tourner le vrai code du plugin, avec le minimum de WordPress simulé.
 */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $m;
	public function __construct( $c = '', $m = '' ) { $this->m = $m; }
	public function get_error_message() { return $this->m; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = '' ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( $s, ENT_NOQUOTES, 'UTF-8' ); }
function esc_html__( $s, $d = '' ) { return esc_html( $s ); }
function esc_attr( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr__( $s, $d = '' ) { return esc_attr( $s ); }
function sanitize_title( $t ) {
	$t = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $t );
	return trim( strtolower( preg_replace( '/[^A-Za-z0-9]+/', '-', $t ) ), '-' );
}
function wp_date( $f, $ts ) {
	$mois  = array( '', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
		'août', 'septembre', 'octobre', 'novembre', 'décembre' );
	$jours = array( 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi' );
	return $jours[ (int) date( 'w', $ts ) ] . ' ' . (int) date( 'j', $ts )
		. ' ' . $mois[ (int) date( 'n', $ts ) ] . ' ' . date( 'Y', $ts );
}

$inc = dirname( __DIR__ ) . '/includes/';
require $inc . 'class-fdm-docx.php';
require $inc . 'class-fdm-parser.php';
require $inc . 'class-fdm-render.php';

/** Analyse un fichier et rend la structure, ou arrête avec le message d'erreur. */
function analyser( $f ) {
	$p = FDM_Docx::paragraphs( $f );
	if ( is_wp_error( $p ) ) {
		return array( 'erreur' => $p->get_error_message() );
	}
	$d = FDM_Parser::parse( $p, basename( $f ) );
	if ( is_wp_error( $d ) ) {
		return array( 'erreur' => $d->get_error_message() );
	}
	return $d;
}

/* ------------------------------------------------------------------ */
/* Mode inspection : un fichier passé en argument                      */
/* ------------------------------------------------------------------ */
if ( $argc > 1 ) {
	foreach ( array_slice( $argv, 1 ) as $f ) {
		echo "\n", basename( $f ), "\n", str_repeat( '-', 60 ), "\n";
		$d = analyser( $f );
		if ( isset( $d['erreur'] ) ) {
			echo "  ÉCHEC : {$d['erreur']}\n";
			continue;
		}
		printf( "  date       : %s (%s)\n", $d['dates'], $d['date_iso'] );
		printf( "  temps      : %s\n", $d['temps'] );
		printf( "  intentions %d | fêtes %d | jours %d | note %d\n",
			count( $d['intentions'] ), count( $d['fetes'] ),
			count( $d['annonces'] ), count( $d['note'] ) );
		printf( "  %d repères : %s\n", count( $d['messe'] ),
			implode( ' / ', array_column( $d['messe'], 'titre' ) ) );
	}
	exit( 0 );
}

/* ------------------------------------------------------------------ */
/* Contrôles                                                           */
/* ------------------------------------------------------------------ */

$attendus = array(
	'ancien-format.docx' => array(
		'date_iso'  => '2026-09-20',
		'temps'     => '25ème dimanche du temps de l’Eglise',
		'jours'     => array( 'Lundi 21', 'Mardi 22', 'Mercredi 23', 'Dimanche 27' ),
		'reperes'   => 19,
		'premier'   => 'Chant d’entrée',
		'dernier'   => 'Envoi',
		'note_min'  => 2,
		'fetes_min' => 3,
	),
	'livret-2026.docx'   => array(
		// Le titre ne porte qu'une date, et la messe est à l'envers dans le
		// document : c'est la mise en page du 18 octobre 2026.
		'date_iso'  => '2026-10-18',
		'temps'     => 'Fête de St Exemple',
		'jours'     => array( 'Mardi 20', 'Vendredi 23', 'Samedi 24', 'Dimanche 25' ),
		'reperes'   => 19,
		'premier'   => 'Chant d’entrée',
		'dernier'   => 'Envoi',
		'note_min'  => 0,
		'fetes_min' => 0,
	),
);

$echecs = 0;
$verifier = function ( $ok, $quoi, $obtenu = null ) use ( &$echecs ) {
	if ( $ok ) {
		echo "    ok   $quoi\n";
		return;
	}
	$echecs++;
	echo "    KO   $quoi";
	if ( null !== $obtenu ) {
		echo "  → obtenu : " . ( is_array( $obtenu ) ? implode( ', ', $obtenu ) : $obtenu );
	}
	echo "\n";
};

foreach ( $attendus as $fichier => $a ) {
	echo "\n$fichier\n";
	$d = analyser( __DIR__ . '/jeux-essai/' . $fichier );

	if ( isset( $d['erreur'] ) ) {
		$echecs++;
		echo "    KO   analyse impossible : {$d['erreur']}\n";
		continue;
	}

	$titres = array_column( $d['messe'], 'titre' );
	$jours  = array_column( $d['annonces'], 'jour' );

	$verifier( $d['date_iso'] === $a['date_iso'], 'date', $d['date_iso'] );
	$verifier( $d['temps'] === $a['temps'], 'temps liturgique', $d['temps'] );
	$verifier( count( $d['messe'] ) === $a['reperes'],
		$a['reperes'] . ' repères', count( $d['messe'] ) );
	$verifier( reset( $titres ) === $a['premier'], 'commence par ' . $a['premier'], reset( $titres ) );
	$verifier( end( $titres ) === $a['dernier'], 'finit par ' . $a['dernier'], end( $titres ) );
	$verifier( $jours === $a['jours'], 'jours d’annonces', $jours );
	$verifier( count( $d['note'] ) >= $a['note_min'], 'encart de fin' , count( $d['note'] ) );
	$verifier( count( $d['fetes'] ) >= $a['fetes_min'], 'fêtes de la semaine', count( $d['fetes'] ) );
	$verifier( count( $d['intentions'] ) >= 1, 'intentions', count( $d['intentions'] ) );

	// L'ordre liturgique doit être strictement croissant : c'est ce qui
	// garantit qu'un document imposé en livret est bien remis en place.
	$rangs = array_column( $d['messe'], 'rang' );
	$trie  = $rangs;
	sort( $trie );
	$verifier( $rangs === $trie, 'ordre liturgique respecté' );
}

echo "\n", str_repeat( '=', 60 ), "\n";
echo $echecs ? "$echecs contrôle(s) en échec\n" : "Tous les contrôles passent\n";
exit( $echecs ? 1 : 0 );
