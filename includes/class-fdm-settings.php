<?php
/**
 * Réglages : fenêtre d'affichage du bouton de week-end et portée du bouton.
 *
 * @package Feuille_De_Messe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDM_Settings {

	const OPTION = 'fdm_reglages';

	public static function defauts() {
		return array(
			// Numérotation JavaScript : 0 = dimanche ... 6 = samedi.
			// Par défaut du samedi 16 h au dimanche 20 h : la feuille couvre
			// aussi la messe anticipée du samedi soir.
			'debut_jour'  => 6,
			'debut_heure' => 16,
			'fin_jour'    => 0,
			'fin_heure'   => 20,
			// 'site' | 'accueil' | 'off'
			'portee'      => 'site',
		);
	}

	public static function get( $cle = null ) {
		$o = wp_parse_args( get_option( self::OPTION, array() ), self::defauts() );
		return ( null === $cle ) ? $o : $o[ $cle ];
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'declarer' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . FDM_Cpt::TYPE,
			__( 'Réglages', 'feuille-de-messe' ),
			__( 'Réglages', 'feuille-de-messe' ),
			'manage_options',
			'fdm-reglages',
			array( __CLASS__, 'ecran' )
		);
	}

	public static function declarer() {
		register_setting(
			'fdm_reglages_group',
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'nettoyer' ) )
		);
	}

	public static function nettoyer( $entree ) {
		$d = self::defauts();
		return array(
			'debut_jour'  => max( 0, min( 6, (int) ( $entree['debut_jour'] ?? $d['debut_jour'] ) ) ),
			'debut_heure' => max( 0, min( 23, (int) ( $entree['debut_heure'] ?? $d['debut_heure'] ) ) ),
			'fin_jour'    => max( 0, min( 6, (int) ( $entree['fin_jour'] ?? $d['fin_jour'] ) ) ),
			'fin_heure'   => max( 0, min( 23, (int) ( $entree['fin_heure'] ?? $d['fin_heure'] ) ) ),
			'portee'      => in_array( $entree['portee'] ?? '', array( 'site', 'accueil', 'off' ), true ) ? $entree['portee'] : $d['portee'],
		);
	}

	private static function jours() {
		return array(
			0 => __( 'Dimanche', 'feuille-de-messe' ),
			1 => __( 'Lundi', 'feuille-de-messe' ),
			2 => __( 'Mardi', 'feuille-de-messe' ),
			3 => __( 'Mercredi', 'feuille-de-messe' ),
			4 => __( 'Jeudi', 'feuille-de-messe' ),
			5 => __( 'Vendredi', 'feuille-de-messe' ),
			6 => __( 'Samedi', 'feuille-de-messe' ),
		);
	}

	public static function ecran() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'feuille-de-messe' ) );
		}
		$o     = self::get();
		$jours = self::jours();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Feuille de messe — réglages', 'feuille-de-messe' ); ?></h1>

			<h2><?php esc_html_e( 'Adresse permanente', 'feuille-de-messe' ); ?></h2>
			<p><?php esc_html_e( 'Cette adresse mène toujours à la feuille la plus récente. C’est elle qu’il faut mettre dans le QR code affiché à l’entrée de l’église et en pied de la feuille papier : elle ne change jamais.', 'feuille-de-messe' ); ?></p>
			<p><input type="text" class="large-text code" readonly
				value="<?php echo esc_attr( get_post_type_archive_link( FDM_Cpt::TYPE ) ); ?>"
				onfocus="this.select()" /></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'fdm_reglages_group' ); ?>
				<h2><?php esc_html_e( 'Bouton du week-end', 'feuille-de-messe' ); ?></h2>
				<p class="description" style="max-width:40em">
					<?php esc_html_e( 'Sur téléphone, un bouton d’accès rapide apparaît en bas de l’écran pendant cette période. Il est calculé à l’heure du visiteur, et non sur le serveur, pour rester juste malgré la mise en cache des pages.', 'feuille-de-messe' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Apparaît', 'feuille-de-messe' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION ); ?>[debut_jour]">
								<?php foreach ( $jours as $n => $l ) : ?>
									<option value="<?php echo esc_attr( $n ); ?>" <?php selected( $o['debut_jour'], $n ); ?>><?php echo esc_html( $l ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php esc_html_e( 'à', 'feuille-de-messe' ); ?>
							<input type="number" min="0" max="23" style="width:5em"
								name="<?php echo esc_attr( self::OPTION ); ?>[debut_heure]"
								value="<?php echo esc_attr( $o['debut_heure'] ); ?>" /> h
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Disparaît', 'feuille-de-messe' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION ); ?>[fin_jour]">
								<?php foreach ( $jours as $n => $l ) : ?>
									<option value="<?php echo esc_attr( $n ); ?>" <?php selected( $o['fin_jour'], $n ); ?>><?php echo esc_html( $l ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php esc_html_e( 'à', 'feuille-de-messe' ); ?>
							<input type="number" min="0" max="23" style="width:5em"
								name="<?php echo esc_attr( self::OPTION ); ?>[fin_heure]"
								value="<?php echo esc_attr( $o['fin_heure'] ); ?>" /> h
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Où l’afficher', 'feuille-de-messe' ); ?></th>
						<td>
							<?php
							$choix = array(
								'site'    => __( 'Sur tout le site', 'feuille-de-messe' ),
								'accueil' => __( 'Sur la page d’accueil seulement', 'feuille-de-messe' ),
								'off'     => __( 'Nulle part (désactivé)', 'feuille-de-messe' ),
							);
							foreach ( $choix as $v => $l ) :
								?>
								<label style="display:block;margin-bottom:.3em">
									<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[portee]"
										value="<?php echo esc_attr( $v ); ?>" <?php checked( $o['portee'], $v ); ?> />
									<?php echo esc_html( $l ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Bouton permanent (ordinateur)', 'feuille-de-messe' ); ?></h2>
				<p><?php esc_html_e( 'Pour l’en-tête Elementor, déposez un widget « Code court » contenant :', 'feuille-de-messe' ); ?></p>
				<p><input type="text" class="regular-text code" readonly value="[feuille_bouton]" onfocus="this.select()" /></p>
				<p class="description" style="max-width:40em">
					<?php esc_html_e( 'Le bouton affiche la date de la feuille en ligne et disparaît tout seul s’il n’y en a aucune.', 'feuille-de-messe' ); ?>
				</p>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
