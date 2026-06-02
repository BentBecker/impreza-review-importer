/* global reviewsRI, jQuery */
( function ( $ ) {
	'use strict';

	var CHUNK_SIZE = 20;

	// Import Tab
	$( '#ri-form' ).on( 'submit', function ( e ) {
		e.preventDefault();

		var fileInput = document.getElementById( 'ri-file' );
		var file      = fileInput.files[0];

		if ( ! file ) return;

		var category  = $( '#ri-category' ).val();
		var duplicate = $( 'input[name="duplicate"]:checked' ).val();

		var reader = new FileReader();

		reader.onerror = function () {
			alert( reviewsRI.i18n.error_parse );
		};

		reader.onload = function ( evt ) {
			var items;
			try {
				items = JSON.parse( evt.target.result );
			} catch ( err ) {
				alert( reviewsRI.i18n.error_parse );
				return;
			}

			if ( ! Array.isArray( items ) ) {
				alert( reviewsRI.i18n.error_parse );
				return;
			}

			runImport( items, category, duplicate );
		};

		reader.readAsText( file );
	} );

	function runImport( items, category, duplicate ) {
		var total  = items.length;
		var done   = 0;
		var chunks = [];

		for ( var i = 0; i < total; i += CHUNK_SIZE ) {
			chunks.push( items.slice( i, i + CHUNK_SIZE ) );
		}

		$( '#ri-progress' ).show();
		$( '#ri-results' ).show();
		$( '#ri-log' ).empty();
		setProgress( 0, total );
		setStatus( reviewsRI.i18n.importing );

		var chunkIndex = 0;

		function processNext() {
			if ( chunkIndex >= chunks.length ) {
				setStatus( reviewsRI.i18n.done );
				return;
			}

			var chunk = chunks[ chunkIndex++ ];

			$.ajax( {
				url:      reviewsRI.ajax_url,
				type:     'POST',
				dataType: 'json',
				data: {
					action:    'ri_import',
					nonce:     reviewsRI.nonce,
					items:     JSON.stringify( chunk ),
					category:  category,
					duplicate: duplicate
				}
			} )
			.done( function ( response ) {
				if ( ! response.success ) {
					appendLog( 'error', response.data || reviewsRI.i18n.error_server );
					done += chunk.length;
					setProgress( done, total );
					processNext();
					return;
				}

				$.each( response.data, function ( i, result ) {
					appendLog( result.status, result.message );
				} );

				done += chunk.length;
				setProgress( done, total );
				processNext();
			} )
			.fail( function () {
				appendLog( 'error', reviewsRI.i18n.error_server );
				done += chunk.length;
				setProgress( done, total );
				processNext();
			} );
		}

		processNext();
	}

	function setProgress( done, total ) {
		var pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
		$( '#ri-bar' ).css( 'width', pct + '%' );
	}

	function setStatus( msg ) {
		$( '#ri-status' ).text( msg );
	}

	function appendLog( status, message ) {
		$( '#ri-log' ).append(
			$( '<li>' )
				.addClass( 'status-' + status )
				.text( message )
		);
	}

	// Shortcodes Tab
	var $opts = $( '#ri-sc-opts' );

	if ( $opts.length ) {
		var previewTimer = null;

		function getActivePanel() {
			var radio = document.querySelector( 'input[name="ri_tpl"]:checked' );
			if ( ! radio ) { return null; }
			return $opts.find( '.ri-sc-opts-panel[data-tpl="' + radio.value + '"]' )[0];
		}

		function buildShortcode() {
			var radio = document.querySelector( 'input[name="ri_tpl"]:checked' );
			if ( ! radio ) { return ''; }
			var tag   = radio.dataset.shortcode;
			var panel = getActivePanel();
			var code  = '[' + tag;
			if ( panel ) {
				$( panel ).find( '[data-attr]' ).each( function () {
					var val = $( this ).val().trim();
					if ( val !== '' ) {
						code += ' ' + this.dataset.attr + '="' + val + '"';
					}
				} );
			}
			code += ']';
			return code;
		}

		function updateCode() {
			$( '#ri-sc-code' ).text( buildShortcode() );
		}

		function requestPreview() {
			clearTimeout( previewTimer );
			previewTimer = setTimeout( function () {
				var $preview = $( '#ri-sc-preview' );
				$preview.html( '<p class="ri-preview-placeholder">' + reviewsRI.i18n.preview_loading + '</p>' );

				$.ajax( {
					url:      reviewsRI.ajax_url,
					type:     'POST',
					dataType: 'json',
					data: {
						action:    'ri_preview',
						nonce:     reviewsRI.preview_nonce,
						shortcode: buildShortcode()
					}
				} )
				.done( function ( res ) {
					if ( res.success && res.data ) {
						$preview.html( res.data );
					} else {
						$preview.html( '<p class="ri-preview-placeholder">' + reviewsRI.i18n.preview_empty + '</p>' );
					}
				} )
				.fail( function () {
					$preview.html( '<p class="ri-preview-placeholder ri-preview-error">' + reviewsRI.i18n.preview_error + '</p>' );
				} );
			}, 350 );
		}

		function onChange() {
			updateCode();
			requestPreview();
		}

		// Template radio switching.
		$( 'input[name="ri_tpl"]' ).on( 'change', function () {
			$opts.find( '.ri-sc-opts-panel' ).prop( 'hidden', true );
			$opts.find( '.ri-sc-opts-panel[data-tpl="' + this.value + '"]' ).prop( 'hidden', false );
			onChange();
		} );

		// Any field change in the options area.
		$opts.on( 'input change', '[data-attr]', function () {
			// Update color hex label next to color picker.
			if ( this.type === 'color' ) {
				$( this ).siblings( '.ri-sc-color-value' ).text( this.value );
			}
			onChange();
		} );

		// Reset color to default.
		$opts.on( 'click', '.ri-sc-color-reset', function () {
			var $wrap  = $( this ).closest( '.ri-sc-color-wrap' );
			var def    = this.dataset.reset;
			$wrap.find( 'input[type="color"]' ).val( def ).trigger( 'input' );
			$wrap.find( '.ri-sc-color-value' ).text( def );
		} );

		// Copy button.
		$( '#ri-sc-copy' ).on( 'click', function () {
			var text = $( '#ri-sc-code' ).text();
			if ( ! text ) { return; }
			var $btn = $( this );
			navigator.clipboard.writeText( text ).then( function () {
				$btn.text( reviewsRI.i18n.copied );
				setTimeout( function () { $btn.text( reviewsRI.i18n.copy ); }, 2000 );
			} );
		} );

		// Initial render.
		onChange();
	}

} )( jQuery );
