<?php
/**
 * Affichage public : page de la feuille, adresse permanente, boutons d'accès.
 *
 * @package Feuille_De_Messe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDM_Front {

	public static function init() {
		add_shortcode( 'feuille_bouton', array( __CLASS__, 'shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'adresse_permanente' ) );
		add_filter( 'the_content', array( __CLASS__, 'contenu' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'bouton_weekend' ) );
	}

	/**
	 * /feuille mène toujours à la feuille du moment.
	 *
	 * Une redirection plutôt qu'un gabarit d'archive : cela fonctionne avec
	 * n'importe quel thème, Elementor compris, sans avoir à se battre avec la
	 * chaîne des templates. Redirection temporaire (302) puisque la cible
	 * change chaque semaine — une 301 serait mise en cache par le navigateur
	 * et renverrait indéfiniment sur la feuille d'une semaine passée.
	 */
	public static function adresse_permanente() {
		if ( ! is_post_type_archive( FDM_Cpt::TYPE ) ) {
			return;
		}
		$post = FDM_Cpt::courante();
		if ( $post ) {
			wp_safe_redirect( get_permalink( $post ), 302 );
			exit;
		}
	}

	public static function assets() {
		if ( is_singular( FDM_Cpt::TYPE ) ) {
			wp_enqueue_style( 'fdm-feuille', FDM_URL . 'assets/feuille.css', array(), FDM_VERSION );
			wp_enqueue_script( 'fdm-feuille', FDM_URL . 'assets/feuille.js', array(), FDM_VERSION, true );
		}
	}

	/**
	 * Enrichit le contenu de la feuille : en-tête, barre de repères, document
	 * d'origine. Le corps lui-même vient de post_content, donc reste modifiable
	 * dans l'éditeur.
	 */
	public static function contenu( $contenu ) {
		if ( ! is_singular( FDM_Cpt::TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $contenu;
		}

		$post_id = get_the_ID();
		$doc     = FDM_Cpt::struct( $post_id );
		$date    = get_post_meta( $post_id, FDM_Cpt::META_DATE, true );
		$temps   = get_post_meta( $post_id, FDM_Cpt::META_TEMPS, true );
		$docx    = (int) get_post_meta( $post_id, FDM_Cpt::META_DOCX, true );

		$tete = '<header class="fdm-tete">';
		if ( ! empty( $doc['paroisse'] ) ) {
			$tete .= '<p class="fdm-surtitre">' . esc_html( $doc['paroisse'] ) . '</p>';
		}
		$tete .= '<h1>' . esc_html( FDM_Render::jour_long( $date ) ) . '</h1>';
		if ( $temps ) {
			$tete .= '<p class="fdm-temps">' . esc_html( $temps ) . '</p>';
		}
		$tete .= '</header>';

		$pied = '';
		if ( $docx ) {
			$pied = '<footer class="fdm-pied"><a class="fdm-telecharger" href="'
				. esc_url( wp_get_attachment_url( $docx ) ) . '" download>'
				. esc_html__( 'Télécharger la feuille', 'feuille-de-messe' ) . '</a></footer>';
		}

		return '<div class="fdm"><div class="fdm-page">'
			. $tete
			. FDM_Render::nav( $doc )
			. $contenu
			. $pied
			. '</div></div>';
	}

	/**
	 * [feuille_bouton] — lien vers la feuille du moment, avec sa date.
	 * Ne renvoie rien s'il n'y a pas de feuille : mieux vaut pas de bouton
	 * qu'un bouton menant à une page vide.
	 */
	public static function shortcode( $atts ) {
		$post = FDM_Cpt::courante();
		if ( ! $post ) {
			return '';
		}
		$atts = shortcode_atts(
			array( 'libelle' => '' ),
			$atts,
			'feuille_bouton'
		);

		$date    = get_post_meta( $post->ID, FDM_Cpt::META_DATE, true );
		$libelle = $atts['libelle'];
		if ( '' === $libelle ) {
			$ts      = $date ? strtotime( $date ) : 0;
			$libelle = $ts
				? sprintf( /* translators: date courte */ __( 'Feuille du %s', 'feuille-de-messe' ), wp_date( 'j F', $ts ) )
				: __( 'Feuille de messe', 'feuille-de-messe' );
		}

		return '<a class="fdm-bouton" href="' . esc_url( get_permalink( $post ) ) . '">'
			. esc_html( $libelle ) . '</a>' . self::style_bouton();
	}

	/**
	 * Bouton flottant du week-end, sur téléphone.
	 *
	 * Il est TOUJOURS présent dans le HTML, masqué, et révélé par JavaScript
	 * selon l'heure du visiteur. C'est indispensable ici : le site sert des
	 * pages mises en cache, donc une décision prise côté PHP (« on est
	 * dimanche ») resterait figée dans le cache les jours suivants.
	 */
	public static function bouton_weekend() {
		$o = FDM_Settings::get();
		if ( 'off' === $o['portee'] ) {
			return;
		}
		if ( 'accueil' === $o['portee'] && ! is_front_page() ) {
			return;
		}
		// Inutile sur la feuille elle-même.
		if ( is_singular( FDM_Cpt::TYPE ) ) {
			return;
		}

		$post = FDM_Cpt::courante();
		if ( ! $post ) {
			return;
		}

		$date = get_post_meta( $post->ID, FDM_Cpt::META_DATE, true );
		$ts   = $date ? strtotime( $date ) : 0;
		$sous = $ts ? wp_date( 'j F', $ts ) : '';

		echo self::style_bouton(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS interne.
		?>
		<a class="fdm-flottant" id="fdm-flottant" hidden
			href="<?php echo esc_url( get_permalink( $post ) ); ?>">
			<span class="fdm-flottant-titre"><?php esc_html_e( 'Feuille de messe', 'feuille-de-messe' ); ?></span>
			<?php if ( $sous ) : ?>
				<span class="fdm-flottant-date"><?php echo esc_html( $sous ); ?></span>
			<?php endif; ?>
		</a>
		<script>
		(function () {
			var el = document.getElementById('fdm-flottant');
			if (!el) { return; }
			var dj = <?php echo (int) $o['debut_jour']; ?>, dh = <?php echo (int) $o['debut_heure']; ?>;
			var fj = <?php echo (int) $o['fin_jour']; ?>, fh = <?php echo (int) $o['fin_heure']; ?>;
			var n = new Date();
			var p = n.getDay() * 24 + n.getHours() + n.getMinutes() / 60;
			var a = dj * 24 + dh, b = fj * 24 + fh;
			// La fenêtre peut enjamber la fin de semaine (samedi -> dimanche).
			var dedans = (a <= b) ? (p >= a && p <= b) : (p >= a || p <= b);
			if (dedans) { el.hidden = false; }
		})();
		</script>
		<?php
	}

	/**
	 * Styles des boutons, en ligne : ils peuvent apparaître sur n'importe
	 * quelle page du site, et une feuille de style supplémentaire chargée
	 * partout pour vingt lignes de CSS n'en vaut pas le coût.
	 */
	private static function style_bouton() {
		static $fait = false;
		if ( $fait ) {
			return '';
		}
		$fait = true;

		return '<style id="fdm-bouton-css">'
			. '.fdm-bouton{display:inline-block;background:#1A76F3;color:#fff;font-family:Lato,system-ui,sans-serif;'
			. 'font-weight:700;font-size:.8rem;letter-spacing:.64px;text-transform:uppercase;text-decoration:none;'
			. 'padding:.85rem 1.4rem;border-radius:4px;line-height:1.2}'
			. '.fdm-bouton:hover,.fdm-bouton:focus{background:#02235F;color:#fff}'
			. '.fdm-flottant{position:fixed;left:16px;right:16px;bottom:16px;z-index:9999;display:flex;'
			. 'align-items:baseline;justify-content:center;gap:.5rem;background:#1A76F3;color:#fff;'
			. 'font-family:Lato,system-ui,sans-serif;text-decoration:none;padding:1rem;border-radius:4px;'
			. 'box-shadow:0 6px 20px rgba(2,35,95,.28)}'
			. '.fdm-flottant-titre{font-weight:700;font-size:.85rem;letter-spacing:.64px;text-transform:uppercase}'
			. '.fdm-flottant-date{font-size:.85rem;opacity:.85}'
			. '.fdm-flottant[hidden]{display:none}'
			. '@media (min-width:782px){.fdm-flottant{display:none}}'
			. '</style>';
	}
}
