<?php
/**
 * Plugin Name:       Feuille de messe
 * Plugin URI:        https://github.com/Babadji/feuille-de-messe
 * Description:       Convertit la feuille de messe Word déposée en back office en une page mobile lisible, avec un accès rapide le week-end.
 * Version:           0.2.0
 * Author:            Claire Abadji (+ Claude Code)
 * License:           GPL-2.0-or-later
 * Text Domain:       feuille-de-messe
 * Requires PHP:      7.3
 * GitHub Plugin URI: Babadji/feuille-de-messe
 * Primary Branch:    main
 *
 * Mises à jour assurées par PM Updater : il lit l'en-tête « Version: » ci-dessus
 * sur la branche « main » du dépôt. Pour publier une mise à jour, il suffit donc
 * d'incrémenter ce numéro et de pousser — ni tag ni release à créer.
 *
 * @package Feuille_De_Messe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FDM_VERSION', '0.2.0' );
define( 'FDM_FILE', __FILE__ );
define( 'FDM_DIR', plugin_dir_path( __FILE__ ) );
define( 'FDM_URL', plugin_dir_url( __FILE__ ) );

require_once FDM_DIR . 'includes/class-fdm-docx.php';
require_once FDM_DIR . 'includes/class-fdm-parser.php';
require_once FDM_DIR . 'includes/class-fdm-render.php';
require_once FDM_DIR . 'includes/class-fdm-cpt.php';
require_once FDM_DIR . 'includes/class-fdm-settings.php';
require_once FDM_DIR . 'includes/class-fdm-liturgie.php';
require_once FDM_DIR . 'includes/class-fdm-front.php';

if ( is_admin() ) {
	require_once FDM_DIR . 'includes/class-fdm-admin.php';
}

add_action( 'init', array( 'FDM_Cpt', 'register' ), 0 );
add_action( 'init', array( 'FDM_Front', 'init' ) );
add_action( 'init', array( 'FDM_Liturgie', 'init' ) );

if ( is_admin() ) {
	add_action( 'plugins_loaded', array( 'FDM_Admin', 'init' ) );
	add_action( 'plugins_loaded', array( 'FDM_Settings', 'init' ) );
}

/**
 * Les règles de réécriture (/feuille) n'existent qu'une fois le CPT déclaré :
 * on les régénère à l'activation, jamais à chaque chargement.
 */
register_activation_hook( __FILE__, function () {
	FDM_Cpt::register();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
