<?php
/**
 * Mise en forme : structure analysée -> HTML.
 *
 * Le corps de la feuille est stocké dans le contenu de l'article, afin de
 * rester modifiable dans l'éditeur WordPress. Il n'emploie donc que des
 * balises que wp_kses_post laisse passer : pas de <nav>, pas d'attribut
 * « data- », pas d'aria- exotique. La barre de navigation et le pied de page,
 * qui ont besoin de ces éléments, sont produits à l'affichage.
 *
 * @package Feuille_De_Messe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDM_Render {

	/**
	 * Corps de la feuille, destiné à post_content.
	 */
	public static function body( $doc ) {
		$h = '';

		if ( $doc['intentions'] ) {
			$h .= '<section class="fdm-bloc"><h2>' . esc_html__( 'Intentions de messe', 'feuille-de-messe' ) . '</h2><ul class="fdm-intentions">';
			foreach ( $doc['intentions'] as $x ) {
				$h .= '<li>' . esc_html( $x ) . '</li>';
			}
			$h .= '</ul></section>';
		}

		if ( $doc['fetes'] ) {
			$h .= '<section class="fdm-bloc"><h2>' . esc_html__( 'Fêtes de la semaine', 'feuille-de-messe' ) . '</h2><ul class="fdm-fetes">';
			foreach ( $doc['fetes'] as $x ) {
				$h .= '<li>' . esc_html( $x ) . '</li>';
			}
			$h .= '</ul></section>';
		}

		if ( $doc['annonces'] ) {
			$h .= '<section class="fdm-bloc" id="fdm-semaine"><h2>' . esc_html__( 'Annonces de la semaine', 'feuille-de-messe' ) . '</h2><div class="fdm-semaine">';
			foreach ( $doc['annonces'] as $a ) {
				$h .= '<div class="fdm-jour">';
				if ( '' !== $a['jour'] ) {
					$h .= '<h3>' . esc_html( $a['jour'] ) . '</h3>';
				}
				$h .= '<ul>';
				foreach ( $a['items'] as $i ) {
					$h .= '<li><span class="fdm-h">' . esc_html( $i['heure'] ) . '</span>'
						. '<span class="fdm-q">' . esc_html( $i['quoi'] ) . '</span></li>';
				}
				$h .= '</ul></div>';
			}
			$h .= '</div></section>';
		}

		if ( $doc['messe'] ) {
			$h .= '<section class="fdm-bloc"><h2>' . esc_html__( 'Déroulé de la messe', 'feuille-de-messe' ) . '</h2></section>';
			foreach ( $doc['messe'] as $b ) {
				$h .= '<section class="fdm-moment" id="' . esc_attr( $b['slug'] ) . '">';
				$h .= '<h3>' . esc_html( $b['titre'] );
				if ( '' !== $b['rubrique'] ) {
					$h .= ' <span class="fdm-rubrique">' . esc_html( $b['rubrique'] ) . '</span>';
				}
				$h .= '</h3>';
				foreach ( $b['lignes'] as $l ) {
					$h .= self::ligne( $l );
				}
				$h .= '</section>';
			}
		}

		if ( $doc['note'] ) {
			$h .= '<aside class="fdm-encart"><p class="fdm-surtitre">' . esc_html__( 'À noter', 'feuille-de-messe' ) . '</p><p>'
				. implode( '<br />', array_map( 'esc_html', $doc['note'] ) ) . '</p></aside>';
		}

		return $h;
	}

	private static function ligne( $l ) {
		$texte = nl2br( esc_html( $l['texte'] ), false );

		switch ( $l['type'] ) {
			case 'refrain':
				return '<p class="fdm-refrain"><span class="fdm-marque">' . esc_html__( 'Refrain', 'feuille-de-messe' ) . '</span>' . $texte . '</p>';
			case 'couplet':
				return '<p class="fdm-couplet"><span class="fdm-num">' . esc_html( $l['num'] ) . '</span>' . $texte . '</p>';
			case 'dialogue':
				$qui = ( 'tous' === $l['qui'] ) ? __( 'Tous', 'feuille-de-messe' ) : __( 'Le prêtre', 'feuille-de-messe' );
				return '<p class="fdm-dit fdm-dit-' . esc_attr( $l['qui'] ) . '"><span class="fdm-qui">' . esc_html( $qui ) . '</span>' . $texte . '</p>';
			default:
				return '<p>' . $texte . '</p>';
		}
	}

	/**
	 * Barre de repères collante, produite à l'affichage à partir de la
	 * structure conservée en meta (et non du contenu, qui peut être modifié).
	 */
	public static function nav( $doc ) {
		if ( empty( $doc['messe'] ) ) {
			return '';
		}
		$h = '<nav class="fdm-barre" aria-label="' . esc_attr__( 'Déroulé de la messe', 'feuille-de-messe' ) . '"><div class="fdm-pistes">';
		if ( ! empty( $doc['annonces'] ) ) {
			$h .= '<a class="fdm-chip" href="#fdm-semaine">' . esc_html__( 'La semaine', 'feuille-de-messe' ) . '</a>';
		}
		foreach ( $doc['messe'] as $b ) {
			$h .= '<a class="fdm-chip" href="#' . esc_attr( $b['slug'] ) . '">' . esc_html( $b['court'] ) . '</a>';
		}
		return $h . '</div></nav>';
	}

	/**
	 * Libellé long d'une date ISO : « Dimanche 20 septembre 2026 ».
	 */
	public static function jour_long( $iso ) {
		if ( ! $iso ) {
			return '';
		}
		$ts = strtotime( $iso );
		if ( ! $ts ) {
			return '';
		}
		return ucfirst( wp_date( 'l j F Y', $ts ) );
	}
}
