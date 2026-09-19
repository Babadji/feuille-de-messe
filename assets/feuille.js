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

/**
 * Décale la feuille sous l'en-tête fixe du site.
 *
 * On ne peut pas mesurer « .elementor-location-header » : ses enfants sont en
 * position fixed, donc hors flux, et le conteneur fait 0 px de haut. On ne peut
 * pas non plus coder une valeur en dur — elle diffère entre ordinateur et
 * téléphone, et changerait au moindre remaniement de l'en-tête.
 *
 * On sonde donc le haut de l'écran : jusqu'à quelle hauteur le point central
 * est-il recouvert par un élément fixe de l'en-tête ? On retire la barre
 * d'administration, déjà compensée par la marge que WordPress pose sur <html>.
 * Comme en-tête et barre d'admin sont tous deux fixes, le calcul reste juste
 * quelle que soit la position de défilement.
 */
( function () {
	'use strict';

	var ENTETE = '[data-elementor-type="header"], .elementor-location-header, .site-header, #masthead';

	function estFixe( el ) {
		for ( var n = el; n && n !== document.body; n = n.parentElement ) {
			var p = getComputedStyle( n ).position;
			if ( 'fixed' === p || 'sticky' === p ) {
				return true;
			}
		}
		return false;
	}

	function couvert( x, y ) {
		var el = document.elementFromPoint( x, y );
		return !! ( el && el.closest( ENTETE ) && estFixe( el ) );
	}

	function hauteurEntete() {
		var cx = Math.round( window.innerWidth / 2 );
		var bas = 0;
		var y;

		// Balayage grossier, puis affinage au pixel : la mesure est rejouée
		// plusieurs fois, autant qu'elle reste bon marché.
		for ( y = 1; y <= 400; y += 8 ) {
			if ( couvert( cx, y ) ) {
				bas = y;
			}
		}
		if ( ! bas ) {
			return 0;
		}
		for ( y = bas + 1; y <= bas + 8; y += 1 ) {
			if ( couvert( cx, y ) ) {
				bas = y;
			}
		}

		// La barre d'administration est déjà compensée par la marge que
		// WordPress pose sur <html> : la compter deux fois décalerait tout.
		var barre = document.getElementById( 'wpadminbar' );
		var admin = barre ? barre.getBoundingClientRect().height : 0;
		return Math.max( 0, bas + 1 - admin );
	}

	var dernier = null;

	function appliquer() {
		if ( ! document.querySelector( '.fdm-page' ) ) {
			return;
		}
		var h = hauteurEntete();
		if ( h !== dernier ) {
			dernier = h;
			document.documentElement.style.setProperty( '--fdm-entete', h + 'px' );
		}
	}

	// L'en-tête Elementor ne passe en position fixed qu'une fois son propre
	// script exécuté. Une mesure au chargement tombe donc trop tôt et ne
	// trouve rien à mesurer — c'est ce qui laissait le titre passer sous le
	// menu, sur téléphone comme sur ordinateur. On insiste jusqu'à obtenir
	// une hauteur, puis on s'arrête. Si l'en-tête n'est pas fixe du tout, la
	// mesure vaut zéro à bon droit et on cesse au bout de trois secondes.
	var essais = 0;

	function insister() {
		appliquer();
		essais += 1;
		if ( ! dernier && essais < 12 ) {
			setTimeout( insister, 250 );
		}
	}

	insister();
	window.addEventListener( 'load', appliquer );

	// Les titres manuscrits peuvent changer la hauteur de l'en-tête au moment
	// où la police arrive.
	if ( document.fonts && document.fonts.ready ) {
		document.fonts.ready.then( appliquer );
	}

	var minuteur;
	window.addEventListener( 'resize', function () {
		clearTimeout( minuteur );
		minuteur = setTimeout( appliquer, 150 );
	} );
}() );
