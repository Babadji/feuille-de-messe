<?php
/**
 * Analyse d'une feuille de messe : transforme la suite de paragraphes Word
 * en structure exploitable (annonces de la semaine + déroulé de la messe).
 *
 * Deux principes guident ce découpage :
 *
 * 1. On ne se fie JAMAIS au gras. Dans les feuilles de la paroisse, le gras est
 *    appliqué de façon irrégulière, y compris sur des paragraphes courants :
 *    s'en servir pour repérer les titres donne n'importe quoi.
 * 2. On s'appuie sur le vocabulaire liturgique, qui lui est stable d'une
 *    semaine à l'autre (« CHANT D'ENTREE », « Kyrie », « 1ère lecture »...).
 *
 * @package Feuille_De_Messe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDM_Parser {

	const MOIS = 'janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre';

	/**
	 * Repères du déroulé de la messe, dans l'ordre où ils apparaissent.
	 * Clé = motif reconnu dans le document, valeur = [nom affiché, nom court].
	 *
	 * Le nom affiché rétablit les accents que les capitales de Word perdent
	 * (« CHANT D'ENTREE » devient « Chant d'entrée ») ; le nom court sert à la
	 * barre de navigation, où la place manque sur un téléphone.
	 */
	private static function reperes() {
		return array(
			'CHANT D[’\']ENTREE'          => array( 'Chant d’entrée', 'Entrée' ),
			'PREPARATION PENITENTIELLE'   => array( 'Préparation pénitentielle', 'Pénitentiel' ),
			'Kyrie'                       => array( 'Kyrie', 'Kyrie' ),
			'Gloria'                      => array( 'Gloria', 'Gloria' ),
			'1[èe]re lecture'             => array( 'Première lecture', '1ʳᵉ lecture' ),
			'Ps\s*\d+'                    => array( '', 'Psaume' ),
			'2[èe]me lecture'             => array( 'Deuxième lecture', '2ᵉ lecture' ),
			'Acclamation de l[’\']Evangile' => array( 'Acclamation de l’Évangile', 'Alléluia' ),
			'CREDO'                       => array( 'Credo', 'Credo' ),
			'P\.\s*universelle'           => array( 'Prière universelle', 'Prière univ.' ),
			'OFFERTOIRE'                  => array( 'Offertoire', 'Offertoire' ),
			'PRIERE SUR LES OFFRANDES'    => array( 'Prière sur les offrandes', 'Offrandes' ),
			'SANCTUS'                     => array( 'Sanctus', 'Sanctus' ),
			'Anamn[èe]se'                 => array( 'Anamnèse', 'Anamnèse' ),
			'Agnus Dei'                   => array( 'Agnus Dei', 'Agnus Dei' ),
			'COMMUNION'                   => array( 'Communion', 'Communion' ),
			'Sainte Vierge'               => array( 'Sainte Vierge', 'Vierge' ),
			'Envoi'                       => array( 'Envoi', 'Envoi' ),
		);
	}

	/**
	 * @param array[] $paragraphes Sortie de FDM_Docx::paragraphs().
	 * @return array|WP_Error
	 */
	public static function parse( $paragraphes ) {
		$pivot = self::trouver_pivot( $paragraphes );
		if ( is_wp_error( $pivot ) ) {
			return $pivot;
		}

		preg_match(
			'/(\d{1,2})\s+et\s+(\d{1,2})\s+(' . self::MOIS . ')\s+(\d{4})/ui',
			$paragraphes[ $pivot ]['text'],
			$m
		);

		$doc = array(
			'paroisse'   => isset( $paragraphes[1] ) ? rtrim( trim( $paragraphes[1]['text'] ), " :" ) : '',
			'dates'      => $m[1] . ' et ' . $m[2] . ' ' . $m[3] . ' ' . $m[4],
			'date_iso'   => self::date_iso( $m[2], $m[3], $m[4] ),
			'temps'      => isset( $paragraphes[ $pivot + 1 ] ) ? trim( $paragraphes[ $pivot + 1 ]['text'] ) : '',
			'contact'    => array(),
			'intentions' => array(),
			'fetes'      => array(),
			'annonces'   => array(),
			'messe'      => array(),
			'note'       => array(),
		);

		self::partie_haute( $paragraphes, $pivot, $doc );
		self::partie_basse( $paragraphes, $pivot, $doc );

		// Sert à décider si l'import est assez sûr pour être publié tel quel.
		$doc['confiance'] = count( $doc['messe'] );

		return $doc;
	}

	/**
	 * Le paragraphe centré « Paroisse ... 19 et 20 septembre 2026 » sépare les
	 * annonces de la semaine du déroulé de la messe. C'est le seul point d'appui
	 * fiable du document : sans lui, on ne sait pas découper.
	 */
	private static function trouver_pivot( $paragraphes ) {
		$motif = '/(\d{1,2})\s+et\s+(\d{1,2})\s+(' . self::MOIS . ')\s+(\d{4})/ui';
		foreach ( $paragraphes as $i => $p ) {
			if ( $p['center'] && preg_match( $motif, $p['text'] ) ) {
				return $i;
			}
		}
		// Repli : la même ligne, même non centrée.
		foreach ( $paragraphes as $i => $p ) {
			if ( preg_match( $motif, $p['text'] ) ) {
				return $i;
			}
		}
		return new WP_Error(
			'fdm_pivot',
			__( 'Impossible de trouver la ligne de date (du type « Paroisse St Carlo ACUTIS 19 et 20 septembre 2026 »). Le document n’a pas la structure attendue.', 'feuille-de-messe' )
		);
	}

	private static function date_iso( $jour, $mois, $annee ) {
		$mois_num = array_flip( array_map( 'strtolower', explode( '|', self::MOIS ) ) );
		$mois     = mb_strtolower( $mois );
		if ( ! isset( $mois_num[ $mois ] ) ) {
			return '';
		}
		return sprintf( '%04d-%02d-%02d', (int) $annee, $mois_num[ $mois ] + 1, (int) $jour );
	}

	/**
	 * Avant le pivot : coordonnées, intentions, fêtes, annonces de la semaine.
	 */
	private static function partie_haute( $paragraphes, $pivot, &$doc ) {
		$mode = '';

		for ( $i = 0; $i < $pivot; $i++ ) {
			$s = trim( $paragraphes[ $i ]['text'] );
			if ( '' === $s ) {
				continue;
			}

			if ( $i >= 2 && $i <= 5 ) {
				$doc['contact'][] = $s;
			}

			if ( 0 === stripos( $s, 'intentions de messe' ) ) {
				$mode = 'intentions';
				continue;
			}
			if ( 0 === mb_stripos( $s, 'fêtes de la semaine' ) ) {
				$corps = ( false !== strpos( $s, ':' ) ) ? substr( $s, strpos( $s, ':' ) + 1 ) : $s;
				// Une fête par puce : bien plus lisible qu'un pavé sur téléphone.
				// Le document sépare tantôt par « ; », tantôt par « : ».
				foreach ( preg_split( '/[;:]\s*(?=le\s+\d)/u', $corps ) as $bout ) {
					$bout = trim( $bout, " \t;:." );
					if ( '' !== $bout ) {
						$doc['fetes'][] = $bout;
					}
				}
				$mode = '';
				continue;
			}
			if ( 0 === mb_stripos( $s, 'annonces de la semaine' ) ) {
				$mode = 'annonces';
				continue;
			}

			if ( 'intentions' === $mode ) {
				$doc['intentions'][] = $s;
			} elseif ( 'annonces' === $mode ) {
				self::annonce( $paragraphes[ $i ]['text'], $doc );
			}
		}
	}

	/**
	 * Une ligne d'annonce : « Mardi 22 <tab> 7h-8h adoration <tab> 15h30 chapelet ».
	 * Une ligne commençant par une tabulation prolonge le jour précédent.
	 */
	private static function annonce( $brut, &$doc ) {
		$cellules = array_map( 'trim', explode( "\t", $brut ) );
		$tete     = array_shift( $cellules );
		$items    = array_values( array_filter( $cellules, function ( $c ) {
			return '' !== $c;
		} ) );

		$jours = 'Lundi|Mardi|Mercredi|Jeudi|Vendredi|Samedi|Dimanche';
		if ( '' !== $tete && preg_match( '/^(' . $jours . ')\b\s*(\d{1,2})?\s*(.*)$/ui', $tete, $m ) ) {
			$libelle = trim( $m[1] . ' ' . ( isset( $m[2] ) ? $m[2] : '' ) );
			if ( ! empty( $m[3] ) ) {
				array_unshift( $items, trim( $m[3] ) );
			}
			$doc['annonces'][] = array(
				'jour'  => $libelle,
				'items' => array(),
			);
		} elseif ( '' !== $tete ) {
			array_unshift( $items, $tete );
		}

		if ( ! $doc['annonces'] ) {
			$doc['annonces'][] = array(
				'jour'  => '',
				'items' => array(),
			);
		}

		$dernier = count( $doc['annonces'] ) - 1;
		$heure   = '\d{1,2}\s*h(?:\s*\d{2})?';

		foreach ( $items as $item ) {
			if ( preg_match( '/^(' . $heure . '(?:\s*[-–]\s*' . $heure . ')?)\s*(.*)$/u', $item, $m ) ) {
				$doc['annonces'][ $dernier ]['items'][] = array(
					'heure' => preg_replace( '/\s+/u', ' ', $m[1] ),
					'quoi'  => $m[2],
				);
			} else {
				$doc['annonces'][ $dernier ]['items'][] = array(
					'heure' => '',
					'quoi'  => $item,
				);
			}
		}
	}

	/**
	 * Après le pivot : le déroulé de la messe, découpé sur les repères.
	 */
	private static function partie_basse( $paragraphes, $pivot, &$doc ) {
		$reperes = self::reperes();
		$motif   = '/^(' . implode( '|', array_keys( $reperes ) ) . ')\s*(?=[:(]|$)\s*:?\s*(?:\(([^)]*)\))?\s*:?\s*(.*)$/ui';
		$courant = -1;
		$total   = count( $paragraphes );

		for ( $i = $pivot + 2; $i < $total; $i++ ) {
			$s = trim( $paragraphes[ $i ]['text'] );
			if ( '' === $s ) {
				continue;
			}

			// « Evangile selon Saint Mathieu (20, 1-16) » : pas de deux-points,
			// le titre occupe toute la ligne. Cas traité à part.
			if ( preg_match( '/^(Evangile selon [^(]*)(?:\(([^)]*)\))?\s*$/ui', $s, $m ) ) {
				$doc['messe'][] = self::bloc(
					'É' . substr( trim( $m[1] ), 1 ),
					'Évangile',
					isset( $m[2] ) ? $m[2] : ''
				);
				$courant = count( $doc['messe'] ) - 1;
				continue;
			}

			// Le repère ne vaut titre que suivi de « : » ou « ( » ou de rien.
			// Sinon « Kyrie eleison... » ou « Agnus Dei, qui tollis... », qui
			// sont le chant lui-même, seraient pris pour des titres.
			if ( preg_match( $motif, $s, $m ) ) {
				list( $long, $court ) = self::nommer( $m[1], $reperes );
				$doc['messe'][]       = self::bloc( $long, $court, isset( $m[2] ) ? $m[2] : '' );
				$courant              = count( $doc['messe'] ) - 1;

				$reste = isset( $m[3] ) ? trim( $m[3], " :" ) : '';
				if ( '' !== $reste ) {
					$doc['messe'][ $courant ]['lignes'][] = self::ligne( $reste );
				}
				continue;
			}

			// Les lignes centrées de fin de document sont un encart d'annonce.
			if ( $paragraphes[ $i ]['center'] && $courant >= 0 ) {
				$doc['note'][] = $s;
				continue;
			}

			if ( $courant >= 0 ) {
				$doc['messe'][ $courant ]['lignes'][] = self::ligne( $s );
			}
		}
	}

	private static function bloc( $long, $court, $rubrique ) {
		return array(
			'titre'    => $long,
			'court'    => $court,
			'slug'     => sanitize_title( $long ),
			'rubrique' => trim( $rubrique ),
			'lignes'   => array(),
		);
	}

	/**
	 * Associe au texte trouvé son nom affiché et son nom court.
	 */
	private static function nommer( $trouve, $reperes ) {
		foreach ( $reperes as $motif => $noms ) {
			if ( preg_match( '/^' . $motif . '$/ui', trim( $trouve ) ) ) {
				// Le psaume porte son numéro : « Ps 144 » devient « Psaume 144 ».
				if ( '' === $noms[0] ) {
					return array( preg_replace( '/^Ps\s*/ui', 'Psaume ', trim( $trouve ) ), $noms[1] );
				}
				return $noms;
			}
		}
		return array( trim( $trouve ), trim( $trouve ) );
	}

	/**
	 * Type une ligne de chant ou de prière : refrain, couplet, réponse, texte.
	 */
	private static function ligne( $s ) {
		if ( preg_match( '/^(tous|le prêtre|le pretre)\s*:\s*(.*)$/uis', $s, $m ) ) {
			return array(
				'type'  => 'dialogue',
				'qui'   => ( 0 === mb_stripos( $m[1], 'tous' ) ) ? 'tous' : 'pretre',
				'texte' => $m[2],
			);
		}
		if ( preg_match( '/^(R\s*[.\/:]|Ref\s*:)\s*(.*)$/uis', $s, $m ) ) {
			return array(
				'type'  => 'refrain',
				'texte' => $m[2],
			);
		}
		if ( preg_match( '/^(\d{1,2})\s*[.\-–)]\s*(.*)$/uis', $s, $m ) ) {
			return array(
				'type'  => 'couplet',
				'num'   => $m[1],
				'texte' => $m[2],
			);
		}
		return array(
			'type'  => 'texte',
			'texte' => $s,
		);
	}
}
