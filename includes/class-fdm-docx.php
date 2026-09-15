<?php
/**
 * Lecture d'un fichier .docx : on ne garde que ce dont l'analyse a besoin,
 * c'est-à-dire la suite des paragraphes, leur texte et leur centrage.
 *
 * Un .docx est une archive zip contenant du XML : aucune bibliothèque externe
 * n'est nécessaire, ZipArchive et DOMDocument suffisent.
 *
 * @package Feuille_De_Messe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FDM_Docx {

	const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

	/**
	 * @param string $path Chemin du .docx.
	 * @return array[]|WP_Error Liste de ['text' => string, 'center' => bool].
	 */
	public static function paragraphs( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'fdm_zip', __( 'L’extension PHP ZipArchive est requise pour lire les fichiers Word. Demandez-la à votre hébergeur.', 'feuille-de-messe' ) );
		}
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'fdm_lecture', __( 'Fichier illisible.', 'feuille-de-messe' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'fdm_zip_open', __( 'Ce fichier n’est pas un .docx valide.', 'feuille-de-messe' ) );
		}
		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $xml || '' === $xml ) {
			return new WP_Error( 'fdm_xml', __( 'Document Word vide ou illisible (word/document.xml introuvable).', 'feuille-de-messe' ) );
		}

		$avant = libxml_use_internal_errors( true );
		$dom   = new DOMDocument();
		$ok    = $dom->loadXML( $xml );
		libxml_clear_errors();
		libxml_use_internal_errors( $avant );

		if ( ! $ok ) {
			return new WP_Error( 'fdm_xml_parse', __( 'Le contenu du document Word n’a pas pu être analysé.', 'feuille-de-messe' ) );
		}

		$xpath = new DOMXPath( $dom );
		$xpath->registerNamespace( 'w', self::NS );

		$paragraphes = array();

		foreach ( $xpath->query( '//w:p' ) as $p ) {
			$texte = '';

			// getElementsByTagNameNS parcourt les descendants dans l'ordre du
			// document : cela récupère aussi les runs situés dans un w:hyperlink,
			// qui portent souvent les adresses mail et le site de la paroisse.
			foreach ( $p->getElementsByTagNameNS( self::NS, '*' ) as $noeud ) {
				switch ( $noeud->localName ) {
					case 't':
						$texte .= $noeud->textContent;
						break;
					case 'tab':
						$texte .= "\t";
						break;
					case 'br':
						$texte .= "\n";
						break;
				}
			}

			$centre = ( $xpath->query( './/w:jc[@w:val="center"]', $p )->length > 0 );

			// Les espaces insécables de Word feraient échouer les expressions
			// régulières qui attendent un espace ordinaire.
			$texte = str_replace( "\xc2\xa0", ' ', $texte );

			$paragraphes[] = array(
				'text'   => trim( $texte, "\n" ),
				'center' => $centre,
			);
		}

		if ( ! $paragraphes ) {
			return new WP_Error( 'fdm_vide', __( 'Aucun paragraphe trouvé dans le document.', 'feuille-de-messe' ) );
		}

		return $paragraphes;
	}
}
