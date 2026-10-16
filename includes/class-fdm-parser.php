<?php
/**
 * Analyse d'une feuille de messe : transforme la suite de paragraphes Word
 * en structure exploitable (annonces de la semaine + déroulé de la messe).
 *
 * Trois principes guident ce découpage :
 *
 * 1. On ne se fie JAMAIS au gras. Dans ces feuilles, le gras est appliqué de
 *    façon irrégulière, y compris sur des paragraphes courants.
 * 2. On ne se fie PAS NON PLUS à l'ordre du document. La feuille du 18 octobre
 *    2026 était imposée en livret : elle commençait par la fin de la messe,
 *    puis les infos pratiques, puis le titre, puis seulement le début de la
 *    messe. Un découpage en deux moitiés, comme le faisait la version
 *    précédente, ne pouvait pas la lire.
 * 3. On s'appuie sur le vocabulaire liturgique, stable d'une semaine à
 *    l'autre, et on remet les blocs dans l'ordre de la messe à la fin.
 *
 * @package Feuille_De_Messe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDM_Parser {

	const MOIS = 'janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre';

	/**
	 * Repères du déroulé de la messe, DANS L'ORDRE de la célébration.
	 * C'est cet ordre qui sert à remettre les blocs en place quand le document
	 * est imposé en livret.
	 *
	 * Clé = motif reconnu, valeur = [nom affiché, nom court].
	 * Le nom affiché rétablit les accents que les capitales de Word perdent.
	 */
	private static function reperes() {
		return array(
			'CHANT D[’\']ENTREE'            => array( 'Chant d’entrée', 'Entrée' ),
			'PREPARATION PENITENTIELLE'     => array( 'Préparation pénitentielle', 'Pénitentiel' ),
			'Kyrie'                         => array( 'Kyrie', 'Kyrie' ),
			'Gloria'                        => array( 'Gloria', 'Gloria' ),
			'1[èe]re lecture'               => array( 'Première lecture', '1ʳᵉ lecture' ),
			'Ps\s*\d+'                      => array( '', 'Psaume' ),
			'2[èe]me lecture'               => array( 'Deuxième lecture', '2ᵉ lecture' ),
			'Acclamation de l[’\']Evangile' => array( 'Acclamation de l’Évangile', 'Alléluia' ),
			'CREDO'                         => array( 'Credo', 'Credo' ),
			// « P. universelle » dans les feuilles ordinaires, « P.U. » dans
			// celle du 18 octobre 2026.
			'P\.\s*(?:U\.|universelle)'     => array( 'Prière universelle', 'Prière univ.' ),
			'OFFERTOIRE'                    => array( 'Offertoire', 'Offertoire' ),
			'PRIERE SUR LES OFFRANDES'      => array( 'Prière sur les offrandes', 'Offrandes' ),
			'SANCTUS'                       => array( 'Sanctus', 'Sanctus' ),
			'Anamn[èe]se'                   => array( 'Anamnèse', 'Anamnèse' ),
			'Agnus Dei'                     => array( 'Agnus Dei', 'Agnus Dei' ),
			'COMMUNION'                     => array( 'Communion', 'Communion' ),
			'Sainte Vierge'                 => array( 'Sainte Vierge', 'Vierge' ),
			'Envoi'                         => array( 'Envoi', 'Envoi' ),
		);
	}

	/** L'Évangile n'a pas de deux-points : son titre occupe la ligne. */
	const RANG_EVANGILE = 8;

	/**
	 * @param array[] $paragraphes  Sortie de FDM_Docx::paragraphs().
	 * @param string  $nom_fichier  Nom du fichier, dernier recours pour la date.
	 * @return array|WP_Error
	 */
	public static function parse( $paragraphes, $nom_fichier = '' ) {
		$titre = self::reperer_date( $paragraphes, $nom_fichier );
		if ( is_wp_error( $titre ) ) {
			return $titre;
		}

		$doc = array(
			'paroisse'   => self::reperer_paroisse( $paragraphes ),
			'dates'      => $titre['dates'],
			'date_iso'   => $titre['iso'],
			'temps'      => $titre['temps'],
			'contact'    => array(),
			'intentions' => array(),
			'fetes'      => array(),
			'annonces'   => array(),
			'messe'      => array(),
			'note'       => array(),
		);

		self::parcourir( $paragraphes, $titre['lignes'], $doc );
		self::remettre_en_ordre( $doc );

		$doc['confiance'] = count( $doc['messe'] );

		return $doc;
	}

	/* ------------------------------------------------------------------ */
	/* Date et titre                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Cherche la date partout dans le document, par ordre de préférence :
	 * « 19 et 20 septembre 2026 », puis « 18 octobre 2026 », puis le nom du
	 * fichier. À qualité égale on retient le paragraphe centré le plus court :
	 * les zones de texte de Word produisent parfois une ligne qui concatène
	 * tout le titre plusieurs fois.
	 */
	private static function reperer_date( $paragraphes, $nom_fichier ) {
		$deux = '/(\d{1,2})\s+et\s+(\d{1,2})\s+(' . self::MOIS . ')\s+(\d{4})/ui';
		$une  = '/(\d{1,2})\s+(' . self::MOIS . ')\s+(\d{4})/ui';

		$meilleur = null;
		foreach ( $paragraphes as $i => $p ) {
			$txt = trim( $p['text'] );
			if ( '' === $txt ) {
				continue;
			}
			if ( preg_match( $deux, $txt, $m ) ) {
				$cand = array( 'i' => $i, 'rang' => 0, 'long' => mb_strlen( $txt ), 'centre' => $p['center'],
					'jour' => $m[2], 'mois' => $m[3], 'annee' => $m[4],
					'dates' => $m[1] . ' et ' . $m[2] . ' ' . $m[3] . ' ' . $m[4] );
			} elseif ( preg_match( $une, $txt, $m ) ) {
				$cand = array( 'i' => $i, 'rang' => 1, 'long' => mb_strlen( $txt ), 'centre' => $p['center'],
					'jour' => $m[1], 'mois' => $m[2], 'annee' => $m[3],
					'dates' => $m[1] . ' ' . $m[2] . ' ' . $m[3] );
			} else {
				continue;
			}
			if ( null === $meilleur || self::meilleur_candidat( $cand, $meilleur ) ) {
				$meilleur = $cand;
			}
		}

		// Dernier recours : le nom du fichier porte souvent la date.
		if ( null === $meilleur && $nom_fichier && preg_match( $une, $nom_fichier, $m ) ) {
			$meilleur = array( 'i' => -1, 'rang' => 2, 'long' => 0, 'centre' => false,
				'jour' => $m[1], 'mois' => $m[2], 'annee' => $m[3],
				'dates' => $m[1] . ' ' . $m[2] . ' ' . $m[3] );
		}

		if ( null === $meilleur ) {
			return new WP_Error(
				'fdm_date',
				__( 'Aucune date n’a été trouvée, ni dans le document ni dans le nom du fichier. Ajoutez la date dans le document (par exemple « 18 octobre 2026 ») ou renommez le fichier.', 'feuille-de-messe' )
			);
		}

		$zone = self::zone_titre( $paragraphes, $meilleur['i'] );

		return array(
			'iso'    => self::date_iso( $meilleur['jour'], $meilleur['mois'], $meilleur['annee'] ),
			'dates'  => $meilleur['dates'],
			'temps'  => self::temps_liturgique( $paragraphes, $zone, $meilleur['i'] ),
			'lignes' => $zone,
		);
	}

	/** Centré d'abord, puis « deux dates » d'abord, puis le plus court. */
	private static function meilleur_candidat( $a, $b ) {
		if ( $a['centre'] !== $b['centre'] ) {
			return $a['centre'];
		}
		if ( $a['rang'] !== $b['rang'] ) {
			return $a['rang'] < $b['rang'];
		}
		return $a['long'] < $b['long'];
	}

	/** Le titre et son sous-titre : les paragraphes centrés qui se suivent. */
	private static function zone_titre( $paragraphes, $i ) {
		if ( $i < 0 ) {
			return array();
		}
		$zone = array( $i );
		if ( empty( $paragraphes[ $i ]['center'] ) ) {
			return $zone;
		}
		for ( $j = $i - 1; $j >= 0; $j-- ) {
			if ( '' === trim( $paragraphes[ $j ]['text'] ) || ! empty( $paragraphes[ $j ]['center'] ) ) {
				$zone[] = $j;
				if ( '' !== trim( $paragraphes[ $j ]['text'] ) ) {
					continue;
				}
			}
			break;
		}
		$total = count( $paragraphes );
		for ( $j = $i + 1; $j < $total; $j++ ) {
			if ( '' === trim( $paragraphes[ $j ]['text'] ) ) {
				$zone[] = $j;
				continue;
			}
			if ( ! empty( $paragraphes[ $j ]['center'] ) ) {
				$zone[] = $j;
				continue;
			}
			break;
		}
		return $zone;
	}

	/** Le sous-titre : la ligne de la zone de titre qui ne porte pas la date. */
	private static function temps_liturgique( $paragraphes, $zone, $i_date ) {
		$une = '/(\d{1,2})\s+(' . self::MOIS . ')\s+(\d{4})/ui';
		sort( $zone );
		$vus = array();

		foreach ( $zone as $j ) {
			if ( $j === $i_date ) {
				continue;
			}
			$t = trim( $paragraphes[ $j ]['text'] );
			// On écarte les lignes vides, les lignes qui répètent la date, et
			// les lignes interminables que produisent les zones de texte Word
			// en concaténant tout le titre plusieurs fois.
			if ( '' === $t || mb_strlen( $t ) > 120 || preg_match( $une, $t ) ) {
				continue;
			}
			$cle = mb_strtolower( $t );
			if ( ! isset( $vus[ $cle ] ) ) {
				$vus[ $cle ] = $t;
			}
		}

		return implode( ' — ', array_values( $vus ) );
	}

	private static function date_iso( $jour, $mois, $annee ) {
		$num = array_flip( explode( '|', self::MOIS ) );
		$m   = mb_strtolower( $mois );
		if ( ! isset( $num[ $m ] ) ) {
			return '';
		}
		return sprintf( '%04d-%02d-%02d', (int) $annee, $num[ $m ] + 1, (int) $jour );
	}

	private static function reperer_paroisse( $paragraphes ) {
		foreach ( $paragraphes as $p ) {
			if ( preg_match( '/^(Paroisse\s+[^:]{2,60}?)\s*:/ui', trim( $p['text'] ), $m ) ) {
				return trim( $m[1] );
			}
		}
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Parcours                                                            */
	/* ------------------------------------------------------------------ */

	private static function parcourir( $paragraphes, $zone_titre, &$doc ) {
		$reperes = self::reperes();
		$motif   = '/^(' . implode( '|', array_keys( $reperes ) ) . ')\s*(?=[:(]|$)\s*:?\s*(?:\(([^)]*)\))?\s*:?\s*(.*)$/ui';
		$zone    = array_flip( $zone_titre );

		$ctx     = '';   // '', 'contact', 'intentions', 'fetes', 'annonces', 'messe'
		$courant = -1;   // index du bloc de messe en cours

		foreach ( $paragraphes as $i => $p ) {
			// La zone de titre ne contient ni annonces ni liturgie : elle clôt
			// la section en cours. C'est elle qui, dans le livret, sépare les
			// annonces du début de la messe.
			if ( isset( $zone[ $i ] ) ) {
				$ctx = '';
				continue;
			}

			$s = trim( $p['text'] );
			if ( '' === $s ) {
				continue;
			}

			// --- ancres des sections pratiques ---
			if ( preg_match( '/^intentions de messe\b/ui', $s ) ) {
				$ctx = 'intentions';
				$reste = self::apres_deux_points( $s );
				if ( '' !== $reste ) {
					$doc['intentions'][] = $reste;
				}
				continue;
			}
			if ( preg_match( '/^f[êe]tes de la semaine\b/ui', $s ) ) {
				$ctx = 'fetes';
				foreach ( preg_split( '/[;:]\s*(?=le\s+\d)/u', self::apres_deux_points( $s, true ) ) as $bout ) {
					$bout = trim( $bout, " \t;:." );
					if ( '' !== $bout ) {
						$doc['fetes'][] = $bout;
					}
				}
				continue;
			}
			if ( preg_match( '/^annonces de la semaine\b/ui', $s ) ) {
				$ctx = 'annonces';
				continue;
			}
			if ( preg_match( '/^(paroisse\s|t[ée]l\s*:|site\s*:|cur[ée]\b|mail\s*:)/ui', $s ) ) {
				$ctx = 'contact';
				$doc['contact'][] = $s;
				continue;
			}

			// --- repères liturgiques ---
			$bloc = self::reperer_bloc( $s, $motif, $reperes );
			if ( null !== $bloc ) {
				$doc['messe'][] = $bloc['bloc'];
				$courant        = count( $doc['messe'] ) - 1;
				$ctx            = 'messe';
				if ( '' !== $bloc['reste'] ) {
					$doc['messe'][ $courant ]['lignes'][] = self::ligne( $bloc['reste'] );
				}
				continue;
			}

			// --- texte courant, rattaché au contexte ---
			switch ( $ctx ) {
				case 'intentions':
					$doc['intentions'][] = $s;
					break;
				case 'fetes':
					$doc['fetes'][] = $s;
					break;
				case 'annonces':
					if ( ! self::annonce( $p['text'], $doc ) ) {
						$ctx = ''; // ligne qui n'appartient plus à l'horaire
					}
					break;
				case 'contact':
					$doc['contact'][] = $s;
					break;
				case 'messe':
					// Encart d'annonce en fin de feuille : une ligne centrée,
					// assez longue, et qui contient de vrais mots — ce dernier
					// point écarte les lignes de tirets servant de séparateur.
					if ( ! empty( $p['center'] ) && mb_strlen( $s ) > 25
						&& preg_match( '/\p{L}{3,}/u', $s ) ) {
						$doc['note'][] = $s;
					} else {
						$doc['messe'][ $courant ]['lignes'][] = self::ligne( $s );
					}
					break;
			}
		}
	}

	private static function apres_deux_points( $s, $tout_si_absent = false ) {
		$pos = mb_strpos( $s, ':' );
		if ( false === $pos ) {
			return $tout_si_absent ? $s : '';
		}
		return trim( mb_substr( $s, $pos + 1 ) );
	}

	/**
	 * Un repère ne vaut titre que suivi de « : », de « ( » ou de rien. Sinon
	 * « Kyrie eleison… » ou « Agnus Dei, qui tollis… », qui sont le chant
	 * lui-même, seraient pris pour des titres.
	 */
	private static function reperer_bloc( $s, $motif, $reperes ) {
		// L'Évangile occupe toute sa ligne et peut être suivi d'une indication
		// (« puis après la lecture ») : on ne peut pas exiger une fin de ligne.
		if ( preg_match( '/^(Evangile selon\s+[^(]{2,60}?)\s*(?:\(([^)]*)\))?\s*$/ui', $s, $m )
			|| preg_match( '/^(Evangile selon\s+[^(]{2,60}?)\s*\(([^)]*)\)/ui', $s, $m ) ) {
			return array(
				'bloc'  => self::bloc( 'É' . mb_substr( trim( $m[1] ), 1 ), 'Évangile', isset( $m[2] ) ? $m[2] : '', self::RANG_EVANGILE ),
				'reste' => '',
			);
		}

		if ( ! preg_match( $motif, $s, $m ) ) {
			return null;
		}
		list( $long, $court, $rang ) = self::nommer( $m[1], $reperes );
		return array(
			'bloc'  => self::bloc( $long, $court, isset( $m[2] ) ? $m[2] : '', $rang ),
			'reste' => isset( $m[3] ) ? trim( $m[3], " :" ) : '',
		);
	}

	private static function bloc( $long, $court, $rubrique, $rang ) {
		return array(
			'titre'    => $long,
			'court'    => $court,
			'slug'     => sanitize_title( $long ),
			'rubrique' => trim( $rubrique ),
			'rang'     => $rang,
			'lignes'   => array(),
		);
	}

	private static function nommer( $trouve, $reperes ) {
		$rang = 0;
		foreach ( $reperes as $motif => $noms ) {
			if ( preg_match( '/^' . $motif . '$/ui', trim( $trouve ) ) ) {
				if ( '' === $noms[0] ) {
					return array( preg_replace( '/^Ps\s*/ui', 'Psaume ', trim( $trouve ) ), $noms[1], $rang );
				}
				return array( $noms[0], $noms[1], $rang );
			}
			$rang++;
		}
		return array( trim( $trouve ), trim( $trouve ), 999 );
	}

	/**
	 * Remet les blocs dans l'ordre de la messe. Indispensable pour les
	 * documents imposés en livret, sans effet sur les feuilles ordinaires,
	 * déjà dans le bon ordre.
	 */
	private static function remettre_en_ordre( &$doc ) {
		$i = 0;
		foreach ( $doc['messe'] as &$b ) {
			$b['_ordre'] = $i++;
		}
		unset( $b );
		usort(
			$doc['messe'],
			function ( $a, $b ) {
				if ( $a['rang'] === $b['rang'] ) {
					return $a['_ordre'] <=> $b['_ordre'];
				}
				return $a['rang'] <=> $b['rang'];
			}
		);
		foreach ( $doc['messe'] as &$b ) {
			unset( $b['_ordre'] );
		}
		unset( $b );
	}

	/* ------------------------------------------------------------------ */
	/* Annonces                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Une ligne d'annonce : « Mardi 22 <tab> 7h-8h adoration <tab> 15h30 chapelet ».
	 * Une ligne commençant par une tabulation, ou par une heure, prolonge le
	 * jour précédent.
	 *
	 * @return bool false si la ligne n'appartient manifestement plus à l'horaire.
	 */
	private static function annonce( $brut, &$doc ) {
		$jours  = 'Lundi|Mardi|Mercredi|Jeudi|Vendredi|Samedi|Dimanche';
		$heure  = '\d{1,2}\s*h(?:\s*\d{2})?';
		$avec_tab = ( false !== strpos( $brut, "\t" ) );

		$cellules = array_map( 'trim', explode( "\t", $brut ) );
		$tete     = array_shift( $cellules );
		$items    = array_values( array_filter( $cellules, function ( $c ) {
			return '' !== $c;
		} ) );

		$est_jour  = ( '' !== $tete && preg_match( '/^(' . $jours . ')\b\s*(\d{1,2})?\s*(.*)$/ui', $tete, $m ) );
		$est_heure = ( '' !== $tete && preg_match( '/^' . $heure . '/u', $tete ) );

		// Ni jour, ni heure, ni tabulation : on est sorti de l'horaire.
		if ( ! $est_jour && ! $est_heure && ! $avec_tab ) {
			return false;
		}

		if ( $est_jour ) {
			$libelle = trim( $m[1] . ' ' . ( isset( $m[2] ) ? $m[2] : '' ) );
			if ( ! empty( $m[3] ) ) {
				array_unshift( $items, trim( $m[3] ) );
			}
			$doc['annonces'][] = array( 'jour' => $libelle, 'items' => array() );
		} elseif ( '' !== $tete ) {
			array_unshift( $items, $tete );
		}

		if ( ! $doc['annonces'] ) {
			$doc['annonces'][] = array( 'jour' => '', 'items' => array() );
		}

		$dernier = count( $doc['annonces'] ) - 1;
		foreach ( $items as $item ) {
			if ( preg_match( '/^(' . $heure . '(?:\s*[-–]\s*' . $heure . ')?)\s*(.*)$/u', $item, $mm ) ) {
				$doc['annonces'][ $dernier ]['items'][] = array(
					'heure' => preg_replace( '/\s+/u', ' ', $mm[1] ),
					'quoi'  => $mm[2],
				);
			} else {
				$doc['annonces'][ $dernier ]['items'][] = array( 'heure' => '', 'quoi' => $item );
			}
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Lignes de chant                                                     */
	/* ------------------------------------------------------------------ */

	private static function ligne( $s ) {
		if ( preg_match( '/^(tous|le prêtre|le pretre)\s*:\s*(.*)$/uis', $s, $m ) ) {
			return array(
				'type'  => 'dialogue',
				'qui'   => ( 0 === mb_stripos( $m[1], 'tous' ) ) ? 'tous' : 'pretre',
				'texte' => $m[2],
			);
		}
		if ( preg_match( '/^(R\s*[.\/:]|Ref\s*:)\s*(.*)$/uis', $s, $m ) ) {
			return array( 'type' => 'refrain', 'texte' => $m[2] );
		}
		if ( preg_match( '/^(\d{1,2})\s*[.\-–)]\s*(.*)$/uis', $s, $m ) ) {
			return array( 'type' => 'couplet', 'num' => $m[1], 'texte' => $m[2] );
		}
		return array( 'type' => 'texte', 'texte' => $s );
	}
}
