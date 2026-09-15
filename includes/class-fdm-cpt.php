<?php
/**
 * Type de contenu « Feuille de messe » et accès à la feuille courante.
 *
 * @package Feuille_De_Messe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDM_Cpt {

	const TYPE = 'feuille';

	/** Dimanche couvert, au format Y-m-d. Sert au tri et à l'unicité. */
	const META_DATE = '_fdm_date';
	/** Temps liturgique lu dans le document. */
	const META_TEMPS = '_fdm_temps';
	/** Pièce jointe : le .docx d'origine, toujours téléchargeable. */
	const META_DOCX = '_fdm_docx';
	/** Structure analysée, en JSON : sert à la navigation et à la ré-analyse. */
	const META_STRUCT = '_fdm_struct';

	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Feuilles de messe', 'feuille-de-messe' ),
					'singular_name' => __( 'Feuille de messe', 'feuille-de-messe' ),
					'menu_name'     => __( 'Feuilles de messe', 'feuille-de-messe' ),
					'all_items'     => __( 'Toutes les feuilles', 'feuille-de-messe' ),
					'edit_item'     => __( 'Modifier la feuille', 'feuille-de-messe' ),
					'search_items'  => __( 'Chercher une feuille', 'feuille-de-messe' ),
					'not_found'     => __( 'Aucune feuille pour le moment.', 'feuille-de-messe' ),
				),
				'public'          => true,
				'show_ui'         => true,
				'menu_icon'       => 'dashicons-media-text',
				'menu_position'   => 22,
				'supports'        => array( 'title', 'editor', 'revisions' ),
				'has_archive'     => 'feuille',
				'rewrite'         => array(
					'slug'       => 'feuille',
					'with_front' => false,
				),
				'capability_type' => 'post',
				'show_in_rest'    => false,
			)
		);
	}

	/**
	 * La feuille à afficher aujourd'hui.
	 *
	 * On prend la feuille publiée dont le dimanche est le plus récent, en
	 * acceptant les sept jours à venir : une feuille déposée le jeudi pour le
	 * dimanche suivant devient donc visible immédiatement.
	 *
	 * @return WP_Post|null
	 */
	public static function courante() {
		static $cache = false;
		if ( false !== $cache ) {
			return $cache;
		}

		$posts = get_posts(
			array(
				'post_type'        => self::TYPE,
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'meta_key'         => self::META_DATE,
				'orderby'          => 'meta_value',
				'order'            => 'DESC',
				'suppress_filters' => false,
				'meta_query'       => array(
					array(
						'key'     => self::META_DATE,
						'value'   => gmdate( 'Y-m-d', current_time( 'timestamp' ) + WEEK_IN_SECONDS ),
						'compare' => '<=',
						'type'    => 'DATE',
					),
				),
			)
		);

		$cache = $posts ? $posts[0] : null;
		return $cache;
	}

	/**
	 * Feuille déjà enregistrée pour ce dimanche, s'il y en a une.
	 * Permet de mettre à jour au lieu de créer un doublon — et donc de ne pas
	 * casser le QR code déjà imprimé.
	 *
	 * @return int ID de l'article, ou 0.
	 */
	public static function pour_date( $iso ) {
		if ( ! $iso ) {
			return 0;
		}
		$posts = get_posts(
			array(
				'post_type'   => self::TYPE,
				'post_status' => array( 'publish', 'draft', 'pending' ),
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_query'  => array(
					array(
						'key'   => self::META_DATE,
						'value' => $iso,
					),
				),
			)
		);
		return $posts ? (int) $posts[0] : 0;
	}

	/**
	 * Structure analysée d'une feuille.
	 */
	public static function struct( $post_id ) {
		$json = get_post_meta( $post_id, self::META_STRUCT, true );
		if ( ! $json ) {
			return array();
		}
		$doc = json_decode( $json, true );
		return is_array( $doc ) ? $doc : array();
	}
}
