( function ( mw, $ ) {
	if ( mw.config.get( 'wgCodeEditorCurrentLanguage' ) ) { // If the CodeEditor is used then just exit;
		return;
	}

	// codeMirror needs a special textselection jQuery function to work, save the current one to restore when
	// CodeMirror get's disabled.
	var origTextSelection = $.fn.textSelection,
		codeMirror = mw.user.options.get( 'usecodemirror' ) === '1' || mw.user.options.get( 'usecodemirror' ) === 1,
		api = new mw.Api(),
		// function for a textselection function for CodeMirror
		cmTextSelection = function ( command, options ) {
			if ( !codeMirror || codeMirror.getTextArea() !== this[ 0 ] ) {
				return origTextSelection.call( this, command, options );
			}
			var fn, retval;

			fn = {
				/**
				 * Get the contents of the textarea
				 */
				getContents: function () {
					return codeMirror.doc.getValue();
				},

				setContents: function ( newContents ) {
					codeMirror.doc.setValue( newContents );
				},

				/**
				 * Get the currently selected text in this textarea. Will focus the textarea
				 * in some browsers (IE/Opera)
				 */
				getSelection: function () {
					return codeMirror.doc.getSelection();
				},

				/**
				 * Inserts text at the beginning and end of a text selection, optionally
				 * inserting text at the caret when selection is empty.
				 */
				encapsulateSelection: function ( options ) {
					return this.each( function () {
						var insertText,
							selText,
							selectPeri = options.selectPeri,
							pre = options.pre,
							post = options.post,
							startCursor = codeMirror.doc.getCursor( true ),
							endCursor = codeMirror.doc.getCursor( false );

						if ( options.selectionStart !== undefined ) {
							// fn[command].call( this, options );
							fn.setSelection( { start: options.selectionStart, end: options.selectionEnd } ); // not tested
						}

						selText = codeMirror.doc.getSelection();
						if ( !selText ) {
							selText = options.peri;
						} else if ( options.replace ) {
							selectPeri = false;
							selText = options.peri;
						} else {
							selectPeri = false;
							while ( selText.charAt( selText.length - 1 ) === ' ' ) {
								// Exclude ending space char
								selText = selText.substring( 0, selText.length - 1 );
								post += ' ';
							}
							while ( selText.charAt( 0 ) === ' ' ) {
								// Exclude prepending space char
								selText = selText.substring( 1, selText.length );
								pre = ' ' + pre;
							}
						}

						/**
						* Do the splitlines stuff.
						*
						* Wrap each line of the selected text with pre and post
						*/
						function doSplitLines( selText, pre, post ) {
							var i,
								insertText = '',
								selTextArr = selText.split( '\n' );

							for ( i = 0; i < selTextArr.length; i++ ) {
								insertText += pre + selTextArr[ i ] + post;
								if ( i !== selTextArr.length - 1 ) {
									insertText += '\n';
								}
							}
							return insertText;
						}

						if ( options.splitlines ) {
							selectPeri = false;
							insertText = doSplitLines( selText, pre, post );
						} else {
							insertText = pre + selText + post;
						}

						if ( options.ownline ) {
							if ( startCursor.ch !== 0 ) {
								insertText = '\n' + insertText;
								pre += '\n';
							}

							if ( codeMirror.doc.getLine( endCursor.line ).length !== endCursor.ch ) {
								insertText += '\n';
								post += '\n';
							}
						}

						codeMirror.doc.replaceSelection( insertText );

						if ( selectPeri ) {
							codeMirror.doc.setSelection(
									codeMirror.doc.posFromIndex( codeMirror.doc.indexFromPos( startCursor ) + pre.length ),
									codeMirror.doc.posFromIndex( codeMirror.doc.indexFromPos( startCursor ) + pre.length + selText.length )
								);
						}
					} );
				},

				/**
				 * Get the position (in resolution of bytes not necessarily characters)
				 * in a textarea
				 */
				getCaretPosition: function ( options ) {
					var caretPos = codeMirror.doc.indexFromPos( codeMirror.doc.getCursor( true ) ),
						endPos = codeMirror.doc.indexFromPos( codeMirror.doc.getCursor( false ) );
					if ( options.startAndEnd ) {
						return [ caretPos, endPos ];
					}
					return caretPos;
				},

				setSelection: function ( options ) {
					return this.each( function () {
						codeMirror.doc.setSelection( codeMirror.doc.posFromIndex( options.start ), codeMirror.doc.posFromIndex( options.end ) );
					} );
				},

				/**
				* Scroll a textarea to the current cursor position. You can set the cursor
				* position with setSelection()
				*/
				scrollToCaretPosition: function () {
					return this.each( function () {
						codeMirror.scrollIntoView( null );
					} );
				}
			};

			switch ( command ) {
				// case 'getContents': // no params
				// case 'setContents': // no params with defaults
				// case 'getSelection': // no params
				case 'encapsulateSelection':
					options = $.extend( {
						pre: '', // Text to insert before the cursor/selection
						peri: '', // Text to insert between pre and post and select afterwards
						post: '', // Text to insert after the cursor/selection
						ownline: false, // Put the inserted text on a line of its own
						replace: false, // If there is a selection, replace it with peri instead of leaving it alone
						selectPeri: true, // Select the peri text if it was inserted (but not if there was a selection and replace==false, or if splitlines==true)
						splitlines: false, // If multiple lines are selected, encapsulate each line individually
						selectionStart: undefined, // Position to start selection at
						selectionEnd: undefined // Position to end selection at. Defaults to start
					}, options );
					break;
				case 'getCaretPosition':
					options = $.extend( {
						// Return [start, end] instead of just start
						startAndEnd: false
					}, options );
					// FIXME: We may not need character position-based functions if we insert markers in the right places
					break;
				case 'setSelection':
					options = $.extend( {
						// Position to start selection at
						start: undefined,
						// Position to end selection at. Defaults to start
						end: undefined,
						// Element to start selection in (iframe only)
						startContainer: undefined,
						// Element to end selection in (iframe only). Defaults to startContainer
						endContainer: undefined
					}, options );

					if ( options.end === undefined ) {
						options.end = options.start;
					}
					if ( options.endContainer === undefined ) {
						options.endContainer = options.startContainer;
					}
					// FIXME: We may not need character position-based functions if we insert markers in the right places
					break;
				case 'scrollToCaretPosition':
					options = $.extend( {
						force: false // Force a scroll even if the caret position is already visible
					}, options );
					break;
			}

			retval = fn[ command ].call( this, options );
			codeMirror.focus();

			return retval;
		},
		/**
		 * Adds the CodeMirror button to WikiEditor
		 */
		addCodeMirrorToWikiEditor = function () {
			if ( $( '#wikiEditor-section-main' ).length > 0 ) {
				var msg = codeMirror ? 'codemirror-disable-label' : 'codemirror-enable-label';

				$( '#wpTextbox1' ).wikiEditor(
					'addToToolbar',
					{
						section: 'main',
						groups: {
							codemirror: {
								tools: {
									CodeMirror: {
										label: mw.msg( msg ),
										type: 'button',
										// FIXME: There should be a better way?
										icon: mw.config.get( 'wgExtensionAssetsPath' ) + '/CodeMirror/resources/images/cm-' + ( codeMirror ? 'on.png' : 'off.png' ),
										action: {
											type: 'callback',
											execute: function ( context ) {
												switchCodeMirror( context );
											}
										}
									}
								}
							}
						}
					}
				);
			}
		},
		originHooksTextarea = $.valHooks.textarea;

	// define JQuery hook for searching and replacing text using JS if CodeMirror is enabled, see Bug: T108711
	$.valHooks.textarea = {
		get: function ( elem ) {
			if ( elem.id === 'wpTextbox1' && codeMirror ) {
				return codeMirror.doc.getValue();
			} else if ( originHooksTextarea ) {
				return originHooksTextarea.get( elem );
			}
			return elem.value;
		},
		set: function ( elem, value ) {
			if ( elem.id === 'wpTextbox1' && codeMirror ) {
				return codeMirror.doc.setValue( value );
			} else if ( originHooksTextarea ) {
				return originHooksTextarea.set( elem, value );
			}
			elem.value = value;
		}
	};

	/**
	 * Save CodeMirror enabled pref.
	 *
	 * @param {boolean} prefValue True, if CodeMirror should be enabled by default, otherwise false.
	 */
	function setCodeEditorPreference( prefValue ) {
		if ( mw.user.isAnon() ) { // Skip it for anon users
			return;
		}
		api.postWithToken( 'options', {
			action: 'options',
			optionname: 'usecodemirror',
			optionvalue: prefValue ? 1 : 0
		} ).fail( function ( code, result ) {
			// FIXME: Should this throw an user visible error message?
			mw.log.warn( 'Failed to set code editor preference: ' + code + '\n' + result.error );
		} );
	}

	/**
	 * Enables or disables CodeMirror
	 *
	 * @param {undefined} context Doc needed
	 */
	function switchCodeMirror( context ) {
		var $img, $src;

		if ( context !== false ) {
			$img = context.modules.toolbar.$toolbar.find( 'img.tool[rel=CodeMirror]' );
		} else {
			$img = $( '#CodeMirrorButton' );
		}

		if ( codeMirror ) {
			setCodeEditorPreference( false );
			codeMirror.save();
			codeMirror.toTextArea();
			codeMirror = false;
			$.fn.textSelection = origTextSelection;
			$src = mw.config.get( 'wgExtensionAssetsPath' ) + '/CodeMirror/resources/images/' + ( context ? 'cm-off.png' : 'old-cm-off.png' );
			$img
				.attr( 'src', $src )
				.attr( 'title', mw.msg( 'codemirror-enable-label' ) );
		} else {
			enableCodeMirror();
			$src = mw.config.get( 'wgExtensionAssetsPath' ) + '/CodeMirror/resources/images/' + ( context ? 'cm-on.png' : 'old-cm-on.png' );
			$img
				.attr( 'src', $src )
				.attr( 'title', mw.msg( 'codemirror-disable-label' ) );
			setCodeEditorPreference( true );
		}
	}

	/**
	 * Replaces the default textarea with CodeMirror
	 */
	function enableCodeMirror() {
		var textbox1 = $( '#wpTextbox1' );

		if ( textbox1[ 0 ].style.display === 'none' ) {
			return;
		}
		codeMirror = CodeMirror.fromTextArea( textbox1[ 0 ], {
				mwextFunctionSynonyms: mw.config.get( 'extCodeMirrorFunctionSynonyms' ),
				mwextTags: mw.config.get( 'extCodeMirrorTags' ),
				mwextDoubleUnderscore: mw.config.get( 'extCodeMirrorDoubleUnderscore' ),
				mwextUrlProtocols: mw.config.get( 'extCodeMirrorUrlProtocols' ),
				mwextModes: mw.config.get( 'extCodeMirrorExtModes' ),
				styleActiveLine: true,
				lineWrapping: true,
				readOnly: textbox1[ 0 ].readOnly,
				// select mediawiki as text input mode
				mode: 'text/mediawiki',
				extraKeys: {
					Tab: false
				}
			} );
		// Our best friend, IE, needs some special css
		if ( window.navigator.userAgent.indexOf( 'Trident/' ) > -1 ) {
			$( '.CodeMirror' ).addClass( 'CodeMirrorIE' );
		}

		// set the hight of the textarea
		codeMirror.setSize( null, textbox1.height() );
		// Overwrite default textselection of WikiEditor to work with CodeMirror, too
		$.fn.textSelection = cmTextSelection;
	}

	/* Check if view is in edit mode and that the required modules are available. Then, customize the toolbar … */
	if ( $.inArray( mw.config.get( 'wgAction' ), [ 'edit', 'submit' ] ) !== -1 ) {
		// This function shouldn't be called without user.options is loaded, but it's not guaranteed
		mw.loader.using( 'user.options', function () {
			// This can be the string "0" if the user disabled the preference - Bug T54542#555387
			if ( mw.user.options.get( 'usebetatoolbar' ) === 1 || mw.user.options.get( 'usebetatoolbar' ) === '1' ) {
				// load wikiEditor's toolbar (if not already) and add our button
				$.when(
					mw.loader.using( 'ext.wikiEditor.toolbar' ), $.ready
				).then( addCodeMirrorToWikiEditor );
			} else {
				// If WikiEditor isn't enabled, add CodeMirror button to the default wiki editor toolbar
				var $image = $( '<img>' ).attr( {
					width: 23,
					height: 22,
					src: mw.config.get( 'wgExtensionAssetsPath' ) + '/CodeMirror/resources/images/old-cm-' + ( codeMirror ? 'on.png' : 'off.png' ),
					alt: 'CodeMirror',
					title: 'CodeMirror',
					id: 'CodeMirrorButton',
					'class': 'mw-toolbar-editbutton'
				} ).click( function () {
					switchCodeMirror( false );
					return false;
				} );

				$( '#toolbar' ).append( $image );
			}
		} );
	}

	// enable CodeMirror
	if ( codeMirror ) {
		enableCodeMirror();
	}
}( mediaWiki, jQuery ) );
