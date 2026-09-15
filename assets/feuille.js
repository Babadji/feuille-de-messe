/**
 * Barre de repères : met en avant le moment de la messe où l'on se trouve et
 * fait défiler la puce correspondante à l'écran. Utile quand on suit la messe
 * sur son téléphone et qu'on cherche où l'on en est.
 */
( function () {
	'use strict';

	var chips = Array.prototype.slice.call(
		document.querySelectorAll( '.fdm-chip[href^="#"]' )
	);
	if ( ! chips.length || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	var cibles = chips.map( function ( c ) {
		return document.getElementById( decodeURIComponent( c.hash.slice( 1 ) ) );
	} );

	var doux = ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var actif = null;

	var obs = new IntersectionObserver(
		function ( entrees ) {
			entrees.forEach( function ( en ) {
				if ( ! en.isIntersecting ) {
					return;
				}
				var i = cibles.indexOf( en.target );
				if ( i < 0 || i === actif ) {
					return;
				}
				if ( null !== actif && chips[ actif ] ) {
					chips[ actif ].removeAttribute( 'aria-current' );
				}
				actif = i;
				chips[ i ].setAttribute( 'aria-current', 'true' );
				chips[ i ].scrollIntoView( {
					inline: 'center',
					block: 'nearest',
					behavior: doux ? 'smooth' : 'auto'
				} );
			} );
		},
		{ rootMargin: '-25% 0px -65% 0px' }
	);

	cibles.forEach( function ( t ) {
		if ( t ) {
			obs.observe( t );
		}
	} );
}() );
