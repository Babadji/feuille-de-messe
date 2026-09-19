<?php
/**
 * Temps liturgiques : textes modifiables et mise à disposition du front.
 *
 * Le calcul du temps lui-même reste dans l'agenda (snippet WP Code) : c'est
 * lui qui sait quelle semaine il affiche. Le plugin ne fournit que les
 * NOTICES — ce qu'on lit dans la carte au survol — pour qu'elles soient
 * modifiables depuis WordPress plutôt qu'enfouies dans du code.
 *
 * Elles sont publiées dans « window.FDM_LITURGIE », que l'agenda lit s'il la
 * trouve. S'il ne la trouve pas, il garde ses textes par défaut : le snippet
 * continue donc de fonctionner même si ce plugin est désactivé.
 *
 * @package Feuille_De_Messe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDM_Liturgie {

	const OPTION = 'fdm_liturgie';

	/**
	 * Notices par défaut.
	 *
	 * Elles sont rédigées, non recopiées : les traductions liturgiques
	 * françaises appartiennent à l'AELF. Chaque notice renvoie vers la page
	 * officielle, qui fait foi. Les champs étant modifiables, le texte d'un
	 * site diocésain peut y être repris — c'est alors une décision éditoriale
	 * de la paroisse, à prendre avec l'accord de la source.
	 */
	public static function defauts() {
		$ordinaire = 'https://liturgie.catholique.fr/celebrer-dans-le-temps/le-temps-ordinaire/';
		$avent     = 'https://liturgie.catholique.fr/annee-liturgique/de-lavent-au-temps-de-noel';
		$careme    = 'https://liturgie.catholique.fr/celebrer-dans-le-temps/du-careme-au-temps-pascal/';
		$pascal    = 'https://liturgie.catholique.fr/celebrer-dans-le-temps/du-careme-au-temps-pascal/le-temps-pascal/';

		return array(
			'source'  => 'Conférence des évêques de France',
			'temps'   => array(
				'Temps ordinaire' => array(
					'lien'  => $ordinaire,
					'texte' => 'Du Baptême du Seigneur aux Cendres, puis de la Pentecôte à l’Avent. « Ordinaire » ne veut pas dire banal : c’est le temps où l’Église déploie, semaine après semaine, tout le mystère du Christ.',
				),
				'Avent'           => array(
					'lien'  => $avent,
					'texte' => 'Les quatre semaines qui ouvrent l’année liturgique. Un temps de veille et de conversion, pour préparer la venue du Christ.',
				),
				'Gaudete'         => array(
					'lien'  => $avent,
					'texte' => 'Troisième dimanche de l’Avent. Son nom vient du premier mot de l’antienne d’ouverture, « Réjouissez-vous » : Noël approche, et l’Église marque une pause dans l’austérité de l’attente.',
				),
				'Temps de Noël'   => array(
					'lien'  => $avent,
					'texte' => 'De la Nativité au Baptême du Seigneur. L’Église contemple Dieu venu partager notre condition d’homme.',
				),
				'Carême'          => array(
					'lien'  => $careme,
					'texte' => 'Quarante jours de prière, de jeûne et de partage, des Cendres jusqu’à Pâques, pour se préparer à célébrer la Résurrection.',
				),
				'Laetare'         => array(
					'lien'  => $careme,
					'texte' => 'Quatrième dimanche de Carême, « Réjouis-toi ». À mi-parcours, la joie de Pâques perce déjà sous la pénitence.',
				),
				'Semaine sainte'  => array(
					'lien'  => $careme,
					'texte' => 'Des Rameaux au Samedi saint. L’Église fait mémoire de la passion et de la mort du Christ.',
				),
				'Pâques'          => array(
					'lien'  => $pascal,
					'texte' => 'La Résurrection du Christ, sommet de toute l’année liturgique.',
				),
				'Temps pascal'    => array(
					'lien'  => $pascal,
					'texte' => 'Les cinquante jours qui vont de Pâques à la Pentecôte, célébrés dans la joie comme s’ils ne formaient qu’un seul grand dimanche.',
				),
				'Pentecôte'       => array(
					'lien'  => $pascal,
					'texte' => 'Le don de l’Esprit Saint aux apôtres, cinquante jours après Pâques. Le temps pascal s’y achève.',
				),
			),
			'couleurs' => array(
				'vert'   => 'Le vert dit l’espérance et la croissance de l’Église.',
				'violet' => 'Le violet marque la pénitence, la conversion et l’attente.',
				'rose'   => 'Le rose est un violet éclairci de blanc : la joie transparaît déjà sous la pénitence.',
				'rouge'  => 'Le rouge évoque le feu de l’Esprit Saint et le sang des martyrs.',
				'blanc'  => 'Le blanc dit la lumière, la joie et la résurrection.',
			),
		);
	}

	/**
	 * Notices en vigueur : les défauts, écrasés par ce qui a été saisi.
	 */
	public static function get() {
		$d = self::defauts();
		$o = get_option( self::OPTION, array() );
		if ( ! is_array( $o ) ) {
			return $d;
		}

		if ( ! empty( $o['source'] ) ) {
			$d['source'] = $o['source'];
		}
		foreach ( $d['temps'] as $nom => $def ) {
			if ( ! empty( $o['temps'][ $nom ]['texte'] ) ) {
				$d['temps'][ $nom ]['texte'] = $o['temps'][ $nom ]['texte'];
			}
			if ( ! empty( $o['temps'][ $nom ]['lien'] ) ) {
				$d['temps'][ $nom ]['lien'] = $o['temps'][ $nom ]['lien'];
			}
		}
		foreach ( $d['couleurs'] as $cle => $def ) {
			if ( ! empty( $o['couleurs'][ $cle ] ) ) {
				$d['couleurs'][ $cle ] = $o['couleurs'][ $cle ];
			}
		}
		return $d;
	}

	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'exporter' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'declarer' ) );
	}

	/**
	 * Publie les notices pour l'agenda. En pied de page, avant ses propres
	 * scripts, et sans dépendance : l'agenda s'en sert s'il la trouve.
	 */
	public static function exporter() {
		echo '<script id="fdm-liturgie">window.FDM_LITURGIE=' . wp_json_encode( self::get() ) . ';</script>' . "\n";
	}

	public static function declarer() {
		register_setting(
			'fdm_liturgie_group',
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'nettoyer' ) )
		);
	}

	public static function nettoyer( $entree ) {
		$propre = array( 'temps' => array(), 'couleurs' => array() );
		$d      = self::defauts();

		$propre['source'] = isset( $entree['source'] ) ? sanitize_text_field( $entree['source'] ) : '';

		foreach ( array_keys( $d['temps'] ) as $nom ) {
			$propre['temps'][ $nom ] = array(
				'texte' => isset( $entree['temps'][ $nom ]['texte'] ) ? sanitize_textarea_field( $entree['temps'][ $nom ]['texte'] ) : '',
				'lien'  => isset( $entree['temps'][ $nom ]['lien'] ) ? esc_url_raw( $entree['temps'][ $nom ]['lien'] ) : '',
			);
		}
		foreach ( array_keys( $d['couleurs'] ) as $cle ) {
			$propre['couleurs'][ $cle ] = isset( $entree['couleurs'][ $cle ] ) ? sanitize_textarea_field( $entree['couleurs'][ $cle ] ) : '';
		}
		return $propre;
	}

	/**
	 * Formulaire, affiché dans l'écran de réglages du plugin.
	 */
	public static function formulaire() {
		$v = self::get();
		$o = self::OPTION;
		?>
		<h2><?php esc_html_e( 'Temps liturgiques', 'feuille-de-messe' ); ?></h2>
		<p class="description" style="max-width:46em">
			<?php esc_html_e( 'Ces notices alimentent la carte affichée au survol du temps liturgique, dans l’agenda de la page Horaires. Laissez un champ vide pour revenir au texte d’origine.', 'feuille-de-messe' ); ?>
		</p>
		<p class="description" style="max-width:46em">
			<strong><?php esc_html_e( 'Sur les droits :', 'feuille-de-messe' ); ?></strong>
			<?php esc_html_e( 'les textes fournis sont rédigés pour la paroisse et renvoient vers la page officielle. Vous pouvez y substituer le texte d’un site diocésain ; c’est alors une décision éditoriale, à prendre avec l’accord de la source.', 'feuille-de-messe' ); ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'fdm_liturgie_group' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="fdm_src"><?php esc_html_e( 'Source citée', 'feuille-de-messe' ); ?></label></th>
					<td><input type="text" id="fdm_src" class="regular-text"
						name="<?php echo esc_attr( $o ); ?>[source]"
						value="<?php echo esc_attr( $v['source'] ); ?>" /></td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Notices par temps', 'feuille-de-messe' ); ?></h3>
			<table class="form-table" role="presentation">
				<?php foreach ( $v['temps'] as $nom => $t ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $nom ); ?></th>
						<td>
							<textarea rows="3" class="large-text"
								name="<?php echo esc_attr( $o ); ?>[temps][<?php echo esc_attr( $nom ); ?>][texte]"
							><?php echo esc_textarea( $t['texte'] ); ?></textarea>
							<input type="url" class="large-text code" style="margin-top:.4em"
								name="<?php echo esc_attr( $o ); ?>[temps][<?php echo esc_attr( $nom ); ?>][lien]"
								value="<?php echo esc_attr( $t['lien'] ); ?>"
								placeholder="https://…" />
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<h3><?php esc_html_e( 'Notices par couleur', 'feuille-de-messe' ); ?></h3>
			<table class="form-table" role="presentation">
				<?php
				$pastilles = array(
					'vert'   => '#3E7D53',
					'violet' => '#6B4E9B',
					'rose'   => '#D98BA8',
					'rouge'  => '#B3322B',
					'blanc'  => '#FFFFFF',
				);
				foreach ( $v['couleurs'] as $cle => $texte ) :
					?>
					<tr>
						<th scope="row">
							<span style="display:inline-block;width:12px;height:12px;border-radius:50%;
								background:<?php echo esc_attr( $pastilles[ $cle ] ); ?>;
								box-shadow:inset 0 0 0 1.5px #02235F;vertical-align:-1px;margin-right:.4em"></span>
							<?php echo esc_html( ucfirst( $cle ) ); ?>
						</th>
						<td><textarea rows="2" class="large-text"
							name="<?php echo esc_attr( $o ); ?>[couleurs][<?php echo esc_attr( $cle ); ?>]"
						><?php echo esc_textarea( $texte ); ?></textarea></td>
					</tr>
				<?php endforeach; ?>
			</table>

			<?php submit_button( __( 'Enregistrer les notices', 'feuille-de-messe' ) ); ?>
		</form>
		<?php
	}
}
