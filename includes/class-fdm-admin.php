<?php
/**
 * Back office : dépôt du .docx, aperçu avant publication, ré-analyse.
 *
 * Le parcours tient en deux écrans. On dépose le fichier, on voit le rendu
 * exact, on publie. Rien n'est mis en ligne tant que le bouton « Publier »
 * n'a pas été actionné : entre les deux, la feuille existe en brouillon.
 *
 * @package Feuille_De_Messe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDM_Admin {

	const PAGE = 'fdm-depot';
	const CAP  = 'edit_posts';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_fdm_deposer', array( __CLASS__, 'deposer' ) );
		add_action( 'admin_post_fdm_publier', array( __CLASS__, 'publier' ) );
		add_action( 'admin_post_fdm_reanalyser', array( __CLASS__, 'reanalyser' ) );
		add_action( 'add_meta_boxes_' . FDM_Cpt::TYPE, array( __CLASS__, 'metabox' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . FDM_Cpt::TYPE,
			__( 'Déposer une feuille', 'feuille-de-messe' ),
			__( 'Déposer une feuille', 'feuille-de-messe' ),
			self::CAP,
			self::PAGE,
			array( __CLASS__, 'ecran' )
		);
	}

	public static function assets( $hook ) {
		if ( false === strpos( $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'fdm-feuille', FDM_URL . 'assets/feuille.css', array(), FDM_VERSION );
	}

	/* ------------------------------------------------------------------ */
	/* Écran                                                               */
	/* ------------------------------------------------------------------ */

	public static function ecran() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'feuille-de-messe' ) );
		}

		$apercu = isset( $_GET['apercu'] ) ? (int) $_GET['apercu'] : 0;

		echo '<div class="wrap"><h1>' . esc_html__( 'Déposer une feuille de messe', 'feuille-de-messe' ) . '</h1>';

		if ( isset( $_GET['erreur'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['erreur'] ) ) ) . '</p></div>';
		}

		if ( $apercu && get_post( $apercu ) ) {
			self::ecran_apercu( $apercu );
		} else {
			self::ecran_depot();
		}

		echo '</div>';
	}

	private static function ecran_depot() {
		?>
		<p class="description" style="max-width:40em">
			<?php esc_html_e( 'Déposez le document Word envoyé par l’abbé. Le rendu mobile vous est montré avant toute mise en ligne.', 'feuille-de-messe' ); ?>
		</p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fdm_deposer" />
			<?php wp_nonce_field( 'fdm_deposer' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="fdm_fichier"><?php esc_html_e( 'Fichier Word (.docx)', 'feuille-de-messe' ); ?></label></th>
					<td><input type="file" id="fdm_fichier" name="fdm_fichier" accept=".docx" required /></td>
				</tr>
			</table>
			<?php submit_button( __( 'Analyser le document', 'feuille-de-messe' ) ); ?>
		</form>
		<?php
	}

	private static function ecran_apercu( $post_id ) {
		$doc   = FDM_Cpt::struct( $post_id );
		$post  = get_post( $post_id );
		$docx  = (int) get_post_meta( $post_id, FDM_Cpt::META_DOCX, true );
		$date  = get_post_meta( $post_id, FDM_Cpt::META_DATE, true );
		$temps = get_post_meta( $post_id, FDM_Cpt::META_TEMPS, true );
		$deja  = ( 'publish' === $post->post_status );

		echo '<div class="notice notice-info"><p>';
		printf(
			/* translators: 1: nombre de repères liturgiques */
			esc_html__( '%1$d repères liturgiques reconnus. Vérifiez le rendu ci-dessous, puis publiez.', 'feuille-de-messe' ),
			(int) count( isset( $doc['messe'] ) ? $doc['messe'] : array() )
		);
		echo '</p></div>';

		if ( ! empty( $doc['messe'] ) && count( $doc['messe'] ) < 10 ) {
			echo '<div class="notice notice-warning"><p>'
				. esc_html__( 'Peu de repères reconnus : la mise en forme du document a peut-être changé. Regardez le rendu de près avant de publier.', 'feuille-de-messe' )
				. '</p></div>';
		}

		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row">' . esc_html__( 'Dimanche couvert', 'feuille-de-messe' ) . '</th><td><strong>'
			. esc_html( FDM_Render::jour_long( $date ) ) . '</strong></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Temps liturgique', 'feuille-de-messe' ) . '</th><td>' . esc_html( $temps ) . '</td></tr>';
		if ( $docx ) {
			echo '<tr><th scope="row">' . esc_html__( 'Document d’origine', 'feuille-de-messe' ) . '</th><td><a href="'
				. esc_url( wp_get_attachment_url( $docx ) ) . '">' . esc_html( get_the_title( $docx ) ) . '</a></td></tr>';
		}
		echo '</table>';

		echo '<p>';
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=fdm_publier&post=' . $post_id ),
			'fdm_publier_' . $post_id
		);
		printf(
			'<a href="%s" class="button button-primary button-hero">%s</a> ',
			esc_url( $url ),
			$deja ? esc_html__( 'Mettre à jour la feuille en ligne', 'feuille-de-messe' ) : esc_html__( 'Publier la feuille', 'feuille-de-messe' )
		);
		printf(
			'<a href="%s" class="button">%s</a>',
			esc_url( get_edit_post_link( $post_id ) ),
			esc_html__( 'Modifier le texte', 'feuille-de-messe' )
		);
		echo '</p>';

		echo '<h2>' . esc_html__( 'Aperçu', 'feuille-de-messe' ) . '</h2>';
		echo '<div class="fdm" style="max-width:34rem;border:1px solid #dcdcde;border-radius:4px;background:#fff">';
		echo '<div class="fdm-page">';
		echo '<header class="fdm-tete"><p class="fdm-surtitre">' . esc_html( isset( $doc['paroisse'] ) ? $doc['paroisse'] : '' ) . '</p>'
			. '<h1>' . esc_html( FDM_Render::jour_long( $date ) ) . '</h1>'
			. '<p class="fdm-temps">' . esc_html( $temps ) . '</p></header>';
		echo wp_kses_post( $post->post_content );
		echo '</div></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Actions                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Dépôt : téléverse, analyse, crée ou met à jour un brouillon.
	 */
	public static function deposer() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'feuille-de-messe' ) );
		}
		check_admin_referer( 'fdm_deposer' );

		if ( empty( $_FILES['fdm_fichier']['name'] ) ) {
			self::retour_erreur( __( 'Aucun fichier reçu.', 'feuille-de-messe' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attach_id = media_handle_upload( 'fdm_fichier', 0 );
		if ( is_wp_error( $attach_id ) ) {
			self::retour_erreur( $attach_id->get_error_message() );
		}

		$resultat = self::analyser( (int) $attach_id );
		if ( is_wp_error( $resultat ) ) {
			wp_delete_attachment( (int) $attach_id, true );
			self::retour_erreur( $resultat->get_error_message() );
		}

		wp_safe_redirect(
			admin_url( 'edit.php?post_type=' . FDM_Cpt::TYPE . '&page=' . self::PAGE . '&apercu=' . $resultat )
		);
		exit;
	}

	/**
	 * Analyse un .docx déjà en médiathèque et enregistre la feuille.
	 *
	 * @param int $attach_id Pièce jointe .docx.
	 * @return int|WP_Error ID de la feuille.
	 */
	private static function analyser( $attach_id ) {
		$chemin = get_attached_file( $attach_id );
		if ( ! $chemin ) {
			return new WP_Error( 'fdm_fichier', __( 'Fichier introuvable sur le serveur.', 'feuille-de-messe' ) );
		}

		$paragraphes = FDM_Docx::paragraphs( $chemin );
		if ( is_wp_error( $paragraphes ) ) {
			return $paragraphes;
		}

		$doc = FDM_Parser::parse( $paragraphes );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}

		$titre = FDM_Render::jour_long( $doc['date_iso'] );
		if ( '' === $titre ) {
			$titre = $doc['dates'];
		}

		// Même dimanche : on met à jour, pour ne pas créer de doublon et pour
		// que l'adresse de la feuille (donc le QR code imprimé) ne change pas.
		$existant = FDM_Cpt::pour_date( $doc['date_iso'] );

		$donnees = array(
			'post_type'    => FDM_Cpt::TYPE,
			'post_title'   => $titre,
			'post_content' => FDM_Render::body( $doc ),
		);

		if ( $existant ) {
			$donnees['ID'] = $existant;
			$post_id       = wp_update_post( wp_slash( $donnees ), true );
		} else {
			$donnees['post_status'] = 'draft';
			$post_id                = wp_insert_post( wp_slash( $donnees ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$ancien = (int) get_post_meta( $post_id, FDM_Cpt::META_DOCX, true );
		if ( $ancien && $ancien !== (int) $attach_id ) {
			wp_delete_attachment( $ancien, true );
		}

		update_post_meta( $post_id, FDM_Cpt::META_DATE, $doc['date_iso'] );
		update_post_meta( $post_id, FDM_Cpt::META_TEMPS, $doc['temps'] );
		update_post_meta( $post_id, FDM_Cpt::META_DOCX, (int) $attach_id );
		update_post_meta( $post_id, FDM_Cpt::META_STRUCT, wp_slash( wp_json_encode( $doc ) ) );
		wp_update_post( array( 'ID' => (int) $attach_id, 'post_parent' => $post_id ) );

		return (int) $post_id;
	}

	public static function publier() {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( ! $post_id || ! current_user_can( 'publish_post', $post_id ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'feuille-de-messe' ) );
		}
		check_admin_referer( 'fdm_publier_' . $post_id );

		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		wp_safe_redirect( get_permalink( $post_id ) );
		exit;
	}

	/**
	 * Ré-analyse le .docx déjà joint, sans avoir à le redéposer.
	 */
	public static function reanalyser() {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'feuille-de-messe' ) );
		}
		check_admin_referer( 'fdm_reanalyser_' . $post_id );

		$docx = (int) get_post_meta( $post_id, FDM_Cpt::META_DOCX, true );
		if ( ! $docx ) {
			self::retour_erreur( __( 'Aucun document Word joint à cette feuille.', 'feuille-de-messe' ) );
		}

		$resultat = self::analyser( $docx );
		if ( is_wp_error( $resultat ) ) {
			self::retour_erreur( $resultat->get_error_message() );
		}

		wp_safe_redirect(
			admin_url( 'edit.php?post_type=' . FDM_Cpt::TYPE . '&page=' . self::PAGE . '&apercu=' . $post_id )
		);
		exit;
	}

	public static function metabox() {
		add_meta_box(
			'fdm_source',
			__( 'Document d’origine', 'feuille-de-messe' ),
			function ( $post ) {
				$docx = (int) get_post_meta( $post->ID, FDM_Cpt::META_DOCX, true );
				if ( $docx ) {
					echo '<p><a href="' . esc_url( wp_get_attachment_url( $docx ) ) . '">'
						. esc_html( get_the_title( $docx ) ) . '</a></p>';
					$url = wp_nonce_url(
						admin_url( 'admin-post.php?action=fdm_reanalyser&post=' . $post->ID ),
						'fdm_reanalyser_' . $post->ID
					);
					echo '<p><a href="' . esc_url( $url ) . '" class="button">'
						. esc_html__( 'Ré-analyser le document', 'feuille-de-messe' ) . '</a></p>';
					echo '<p class="description">'
						. esc_html__( 'Écrase le texte ci-contre à partir du Word. Vos modifications manuelles seront perdues.', 'feuille-de-messe' )
						. '</p>';
				} else {
					echo '<p class="description">' . esc_html__( 'Aucun document joint.', 'feuille-de-messe' ) . '</p>';
				}
			},
			FDM_Cpt::TYPE,
			'side'
		);
	}

	private static function retour_erreur( $message ) {
		wp_safe_redirect(
			admin_url( 'edit.php?post_type=' . FDM_Cpt::TYPE . '&page=' . self::PAGE . '&erreur=' . rawurlencode( $message ) )
		);
		exit;
	}
}
