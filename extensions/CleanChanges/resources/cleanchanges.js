( function ( mw, $ ) {
	'use strict';

	window.toggleVisibilityE = function ( levelId, otherId, linkId, type ) {
		var thisLevel = document.getElementById( levelId ),
			otherLevel = document.getElementById( otherId ),
			linkLevel = document.getElementById( linkId );

		if ( thisLevel.style.display === 'none' ) {
			thisLevel.style.display = type;
			otherLevel.style.display = 'none';
			linkLevel.style.display = 'inline';
		} else {
			thisLevel.style.display = 'none';
			otherLevel.style.display = 'inline';
			linkLevel.style.display = 'none';
		}
	};

	window.showUserInfo = function ( sourceVar, targetId ) {
		$( '#' + targetId ).html( mw.config.get( sourceVar ) );
	};

	$( function () {
		$( '.mw-cleanchanges-showuserinfo' ).each( function () {
			var $this, id, target;

			$this = $( this );
			id = $this.data( 'mw-userinfo-id' );
			target = $this.data( 'mw-userinfo-target' );
			if ( id !== undefined ) {
				$this.click( function ( e ) {
					e.preventDefault();
					window.showUserInfo( 'wgUserInfo' + id, target );
				} );
			}
		} );
		$( '.mw-cleanchanges-showblock' ).each( function () {
			var $this, level, other, link;

			$this = $( this );
			level = $this.data( 'mw-cleanchanges-level' );
			other = $this.data( 'mw-cleanchanges-other' );
			link = $this.data( 'mw-cleanchanges-link' );
			if ( level !== undefined ) {
				$this.click( function ( e ) {
					e.preventDefault();
					window.toggleVisibilityE( level, other, link, 'block' );
				} );
			}
		} );

	} );
}( mediaWiki, jQuery ) );
