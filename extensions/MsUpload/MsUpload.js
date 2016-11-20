( function ( $, mw  ) {
	var msuVars = mw.config.get( 'msuVars' );
	var MsUpload = {

		fileError: function ( uploader, file, errorText ) {
			file.li.warning.text( errorText );
			file.li.addClass( 'yellow' );
			file.li.type.addClass( 'error' );
			file.li.click( function () { // Remove li at click
				file.li.fadeOut( 'fast', function () {
					$( this ).remove();
					uploader.trigger( 'CheckFiles' );
				} );
			} );
		},

		galleryArray: [],
		insertGallery: function () {
			var galleryText = 'File:' + MsUpload.galleryArray.join( '\nFile:' );
			mw.toolbar.insertTags( '<gallery>\n' + galleryText + '\n</gallery>\n', '', '', '' );
		},

		filesArray: [],
		insertFiles: function () {
			mw.toolbar.insertTags( '[[File:' + MsUpload.filesArray.join( ']]\n[[File:' ) + ']]\n', '', '', '' );
		},

		insertLinks: function () {
			if ( msuVars.useMsLinks === true ) {
				mw.toolbar.insertTags( '*{{#l:' + MsUpload.filesArray.join( '}}\n*{{#l:' ) + '}}\n', '', '', '' );
			} else {
				mw.toolbar.insertTags( '*[[:File:' + MsUpload.filesArray.join( ']]\n*[[:File:' ) + ']]\n', '', '', '' );
			}
		},

		unconfirmedReplacements: 0,
		warningText: function ( fileItem, warning, uploader ) {
			switch ( warning ) {
				case '':
				case '&nbsp;':
				case '&#160;':
					$( fileItem.warning ).empty()
						.siblings( '.file-name' ).show()
						.siblings( '.file-name-input' ).hide()
						.siblings( '.file-extension' ).hide();
					break;

				case 'Error: Unknown result from API':
				case 'Error: Request failed':
					$( fileItem.warning ).text( warning );
					break;

				default:
					// IMPORTANT! The code below assumes that every warning not captured by the code above is about a file being replaced
					$( fileItem.warning ).html( warning );

					// We break when the particula warning when a file name starts with IMG
					if ( warning.indexOf( 'The name of the file you are uploading begins with' ) === 0 ) {
						break; // When the file name starts with "IMG", MediaWiki issues this warning. Display it and continue.
					}
					if ( warning.indexOf( 'Der Dateiname beginnt mit' ) === 0 ) {
						break; // Make it work for German too. Must be done this way because the error response doesn't include an error code.
					}

					// When hovering over the link to the file about to be replaced, show the thumbnail
					$( fileItem.warning ).find( 'a' ).mouseover( function () {
						$( fileItem.warning ).find( 'div.thumb' ).show();
					} ).mouseout( function () {
						$( fileItem.warning ).find( 'div.thumb' ).hide();
					} );

					// If a file with the same name already exists, add a checkbox to confirm the replacement
					if ( msuVars.confirmReplace ) {

						MsUpload.unconfirmedReplacements++;

						var title = $( fileItem.warning ).siblings( '.file-name' );

						var checkbox = $( '<input>' ).attr( 'type', 'checkbox' ).click( function () {
							if ( $( this ).is( ':checked' ) ) {
								title.show().next().hide();
								MsUpload.unconfirmedReplacements--;
							} else {
								title.hide().next().show().select();
								MsUpload.unconfirmedReplacements++;
							}
							uploader.trigger( 'CheckFiles' );
						} );
						$( '<label>' ).append( checkbox ).append( mw.msg( 'msu-replace-file' ) ).appendTo( fileItem.warning );
					}
					break;
			}
			uploader.trigger( 'CheckFiles' );
			fileItem.loading.hide();
		},

		checkUploadWarning: function ( filename, fileItem, uploader ) {
			$.ajax( { url: mw.util.wikiScript( 'api' ), dataType: 'json', type: 'POST',
			data: {
				format: 'json',
				action: 'query',
				titles: 'File:' + filename,
				prop: 'imageinfo',
				iiprop: 'uploadwarning'
			}, success: function ( data ) {
				if ( data && data.query && data.query.pages ) {
					var pages = data.query.pages;
					$.each( pages, function ( index, value ) {
						MsUpload.warningText( fileItem, value.imageinfo[ 0 ].html, uploader ); // Pass on the warning message
						return false; // Break out
					} );
				} else {
					MsUpload.warningText( fileItem, 'Error: Unknown result from API', uploader );
				}
			}, error: function () {
				MsUpload.warningText( fileItem, 'Error: Request failed', uploader );
			} } );
		},

		build: function ( file, uploader ) {

			// Auto category
			if ( msuVars.showAutoCat && mw.config.get( 'wgNamespaceNumber' ) === 14 ) {
				file.cat = msuVars.checkAutoCat; // Predefine
				$( '<input>' ).attr( {
					'class': 'msupload-check-index',
					type: 'checkbox',
					checked: file.cat
				} ).change( function () {
					file.cat = this.checked; // Save
				} ).appendTo( file.li );

				$( '<span>' ).attr( 'class', 'msupload-check-span' ).text( mw.config.get( 'wgPageName' ).replace( /_/g, ' ' ) ).appendTo( file.li );
			}

			// Insert an input field for changing the file title
			var fileNameInput = $( '<input>' ).attr( {
				'class': 'file-name-input',
				size: file.name.length,
				name: 'filename',
				value: file.name.substr( 0, file.name.length - file.extension.length - 1 )
			} ).change( function () {
				file.name = this.value + '.' + file.extension;
				$( this ).prev().text( file.name );
				MsUpload.unconfirmedReplacements = 0; // Hack! If the user renames a file to avoid replacing it, this forces the Upload button to appear, but it also does when a user just renames a file that wasn't about to replace another
				MsUpload.checkUploadWarning( this.value, file.li, uploader );
			} ).keydown( function ( event ) {
				// For convenience, when pressing enter, save the new title
				if ( event.keyCode === 13 ) {
					$( this ).change();
					event.preventDefault();
				}
			} ).hide().insertAfter( file.li.title );

			var fileExtension = $( '<span>' ).addClass( 'file-extension' ).text( '.' + file.extension ).hide().insertAfter( fileNameInput );

			file.li.title.click( function () {
				file.li.title.hide();
				fileNameInput.show().select();
				fileExtension.show();
			} );

			// Insert the progress bar
			var progressState = $( '<span>' ).addClass( 'file-progress-state' );
			file.li.children().first().after( progressState );
		},

		checkExtension: function ( file, uploader ) {
			mw.log( file );

			file.li.loading.show();
			file.extension = file.name.split( '.' ).pop().toLowerCase();

			if ( $.inArray( file.extension, mw.config.get( 'wgFileExtensions' ) ) !== -1 ) {
				switch ( file.extension ) {
					case 'jpg': case 'jpeg': case 'png': case 'gif': case 'bmp': case 'tif': case 'tiff':
						file.group = 'image';
						try {
							var image = new o.Image();
							image.onload = function () {
								this.embed( file.li.type.get( 0 ), {
									width: 30,
									height: 30,
									crop: true
								} );
							};
							image.load( file.getSource() );
							file.li.type.addClass( 'file-load' );
						} catch ( event ) {
							file.li.type.addClass( 'image' );
						}
						break;

					case 'mov': case 'avi':
						file.group = 'video';
						file.li.type.addClass( 'video' );
						break;

					case 'pdf':
						file.li.type.addClass( 'pdf' );
						break;
				}
				MsUpload.checkUploadWarning( file.name, file.li, uploader );

				file.li.cancel = $( '<span>' ).attr( { 'class': 'file-cancel', title: mw.msg( 'msu-cancel-upload' ) } );
				file.li.cancel.click( function () {
					uploader.removeFile( file );
					if ( file.group === 'image' ) {
						var index = $.inArray( file.name, MsUpload.galleryArray );
						if ( index !== -1 ) {
							MsUpload.galleryArray.splice( index, 1 );
						}
						uploader.trigger( 'CheckFiles' );
					}
					file.li.fadeOut( 'fast', function () {
						$( this ).remove();
						uploader.trigger( 'CheckFiles' );
					} );
				} );
				file.li.prepend( file.li.cancel );

				MsUpload.build( file, uploader );
			} else { // Wrong datatype
				file.li.loading.hide( 'fast', function () {
					uploader.removeFile( file );
					uploader.refresh();
				} );
				MsUpload.fileError( uploader, file, mw.msg( 'msu-ext-not-allowed', mw.config.get( 'wgFileExtensions' ).length ) + ' ' + mw.config.get( 'wgFileExtensions' ).join( ',' ) );
			}
		},

		cleanAll: function () {
			MsUpload.galleryArray.length = 0; // Reset
			MsUpload.uploader.splice( 0, MsUpload.uploader.files.length );
			$( '#msupload-list .file' ).hide( 'fast', function () {
				$( this ).remove();
				$( '#msupload-insert-gallery' ).unbind( 'click' );
				$( '#msupload-bottom' ).hide();
			} );
		},

		uploader: null,
		createUploader: function () {
			// Define the GUI elements
			var uploadDiv = $( '<div>' ).attr( 'id', 'msupload-div' ),
				uploadContainer = $( '<div>' ).attr( { id: 'msupload-container', 'class': 'start-loading', title: mw.msg( 'msu-button-title' ) } ),
				uploadButton = $( '<div>' ).attr( 'id', 'msupload-select' ),
				statusDiv = $( '<div>' ).attr( 'id', 'msupload-status' ).hide(),
				uploadList = $( '<ul>' ).attr( 'id', 'msupload-list' ),
				bottomDiv = $( '<div>' ).attr( 'id', 'msupload-bottom' ).hide(),
				startButton = $( '<a>' ).attr( 'id', 'msupload-files' ).hide(),
				cleanAll = $( '<a>' ).attr( 'id', 'msupload-clean-all' ).text( mw.msg( 'msu-clean-all' ) ).hide(),
				galleryInsert = $( '<a>' ).attr( 'id', 'msupload-insert-gallery' ).hide(),
				filesInsert = $( '<a>' ).attr( 'id', 'msupload-insert-files' ).hide(),
				linksInsert = $( '<a>' ).attr( 'id', 'msupload-insert-links' ).hide(),
				uploadDrop = $( '<div>' ).attr( 'id', 'msupload-dropzone' ).hide();

			// Add them to the DOM
			bottomDiv.append( startButton, cleanAll, galleryInsert, filesInsert, linksInsert );
			uploadDiv.append( statusDiv, uploadDrop, uploadList, bottomDiv );
			$( '#wikiEditor-ui-toolbar' ).after( uploadDiv );
			uploadContainer.append( uploadButton );
			$( '#wikiEditor-ui-toolbar .group-insert' ).append( uploadContainer );

			// Create the Uploader object
			MsUpload.uploader = new plupload.Uploader( {
				runtimes: 'html5,flash,silverlight,html4',
				browse_button: 'msupload-select',
				container: 'msupload-container',
				max_file_size: '100mb',
				drop_element: 'msupload-dropzone',
				url: msuVars.scriptPath + '/api.php',
				flash_swf_url: msuVars.scriptPath + '/extensions/MsUpload/plupload/Moxie.swf',
				silverlight_xap_url: msuVars.path + '/extensions/MsUpload/plupload/Moxie.xap'
			} );

			// Bind events
			MsUpload.uploader.bind( 'PostInit', MsUpload.onPostInit );
			MsUpload.uploader.bind( 'FilesAdded', MsUpload.onFilesAdded );
			MsUpload.uploader.bind( 'QueueChanged', MsUpload.onQueueChanged );
			MsUpload.uploader.bind( 'StateChanged', MsUpload.onStateChanged );
			MsUpload.uploader.bind( 'FilesRemoved', MsUpload.onFilesRemoved );
			MsUpload.uploader.bind( 'BeforeUpload', MsUpload.onBeforeUpload );
			MsUpload.uploader.bind( 'UploadProgress', MsUpload.onUploadProgress );
			MsUpload.uploader.bind( 'Error', MsUpload.onError );
			MsUpload.uploader.bind( 'FileUploaded', MsUpload.onFileUploaded );
			MsUpload.uploader.bind( 'CheckFiles', MsUpload.onCheckFiles );
			MsUpload.uploader.bind( 'UploadComplete', MsUpload.onCheckFiles );

			startButton.click( function ( event ) {
				MsUpload.uploader.start();
				event.preventDefault();
			} );

			// Initialise
			MsUpload.uploader.init();
		},

		onPostInit: function ( uploader ) {
			mw.log( 'MsUpload DEBUG: runtime: ' + uploader.runtime + ' features: ' + JSON.stringify( uploader.features ) );
			$( '#msupload-container' ).removeClass( 'start-loading' );
			if ( uploader.features.dragdrop && msuVars.useDragDrop ) {
				$( '#msupload-dropzone' ).text( mw.msg( 'msu-dropzone' ) ).show();
				$( '#msupload-dropzone' ).bind( 'dragover', function () {
					$( this ).addClass( 'drop-over' ).css( 'padding', 20 );
				} ).bind( 'dragleave', function () {
					$( this ).removeClass( 'drop-over' ).css( 'padding', 0 );
				} ).bind( 'drop', function () {
					$( this ).removeClass( 'drop-over' ).css( 'padding', 0 );
				} );
			} else {
				$( '#msupload-div' ).addClass( 'nodragdrop' );
			}
		},

		onFilesAdded: function ( uploader, files ) {
			$.each( files, function ( i, file ) {
				// iOS6 by SLBoat
				if ( ( navigator.platform === 'iPad' || navigator.platform === 'iPhone' ) ) {
					if ( file.name.indexOf( 'image' ) !== -1 && file.name.length < 11 ) {
						var heute = new Date(),
							fileNameApple = navigator.platform + '_image_' + heute.getFullYear() + '-' + heute.getMonth() + '-' + heute.getDate() + '-' + heute.getTime(); // Because each image is named 'image.jpg' in iOS6
						file.name = fileNameApple + '_' + i + '.' + file.name.split( '.' ).pop(); // image_Y-M-D_0.jpg
					}
				}
				file.li = $( '<li>' ).attr( 'id', file.id ).addClass( 'file' ).appendTo( $( '#msupload-list' ) );
				file.li.type = $( '<span>' ).addClass( 'file-type' ).appendTo( file.li );
				file.li.title = $( '<span>' ).addClass( 'file-name' ).text( file.name ).appendTo( file.li );
				file.li.size = $( '<span>' ).addClass( 'file-size' ).text( plupload.formatSize( file.size ) ).appendTo( file.li );
				file.li.loading = $( '<span>' ).addClass( 'file-loading' ).appendTo( file.li );
				file.li.warning = $( '<span>' ).addClass( 'file-warning' ).appendTo( file.li );
				MsUpload.checkExtension( file, uploader );
			} );
			uploader.refresh(); // Reposition Flash/Silverlight
			uploader.trigger( 'CheckFiles' );
		},

		onQueueChanged: function ( uploader ) {
			uploader.trigger( 'CheckFiles' );
		},

		onStateChanged: function ( uploader ) {
			mw.log( uploader.state );
			if ( uploader.files.length === ( uploader.total.uploaded + uploader.total.failed ) ) {
				// mw.log( 'State: ' + uploader.files.length ) // All files uploaded
			}
		},

		onFilesRemoved: function ( /* uploader, files */ ) {
			mw.log( 'Files removed' );
			// uploader.trigger( 'CheckFiles' );
		},

		onBeforeUpload: function ( uploader, file ) {
			file.li.title.text( file.name ).show(); // Show title
			$( '#' + file.id + ' .file-name-input' ).hide(); // Hide the file name input
			$( '#' + file.id + ' .file-extension' ).hide(); // Hide the file extension
			uploader.settings.multipart_params = {
				filename: file.name,
				token: mw.user.tokens.get( 'editToken' ),
				action: 'upload',
				ignorewarnings: true,
				comment: mw.msg( 'msu-comment' ),
				format: 'json'
			}; // Set multipart_params
			$( '#' + file.id + ' .file-progress-state' ).text( '0%' );
		},

		onUploadProgress: function ( uploader, file ) {
			$( '#' + file.id + ' .file-progress-state' ).text( file.percent + '%' );
		},

		onError: function ( uploader, error ) {
			mw.log( error );
			$( '#' + error.file.id + ' .file-warning' ).html(
				'Error ' + error.code + ', ' + error.message + ( error.file ? ', File: ' + error.file.name : '' )
			);
			$( '#msupload-status' ).append( error.message );
			uploader.refresh(); // Reposition Flash/Silverlight
		},

		onFileUploaded: function ( uploader, file, success ) {
			mw.log( success );
			file.li.title.unbind( 'click' );
			file.li.title.unbind( 'mouseover' );
			$( '#' + file.id + ' .file-cancel' ).fadeOut( 'fast' );
			$( '#' + file.id + ' .file-progress-state' ).fadeOut( 'fast' );

			try {
				var result = $.parseJSON( success.response );
				if ( result.error ) {
					MsUpload.fileError( uploader, file, result.error.info );
				} else {
					file.li.type.addClass( 'ok' );
					file.li.addClass( 'green' );
					file.li.warning.fadeOut( 'fast' );

					if ( file.cat && mw.config.get( 'wgNamespaceNumber' ) === 14 ) { // Should the categroy be set?
						$.get( mw.util.wikiScript(), {
							action: 'ajax',
							rs: 'MsUpload::saveCat',
							rsargs: [ file.name, mw.config.get( 'wgPageName' ) ]
						}, 'json' );
					}
					$( '<a>' ).text( mw.msg( 'msu-insert-link' ) ).click( function () {
						if ( msuVars.useMsLinks === true ) {
							mw.toolbar.insertTags( '{{#l:' + file.name + '}}', '', '', '' ); // Insert link
						} else {
							mw.toolbar.insertTags( '[[:File:' + file.name + ']]', '', '', '' ); // Insert link
						}
					} ).appendTo( file.li );
					if ( file.group === 'image' ) {
						MsUpload.galleryArray.push( file.name );
						if ( MsUpload.galleryArray.length === 2 ) { // Bind click function only the first time
							$( '#msupload-insert-gallery' ).click( MsUpload.insertGallery ).text( mw.msg( 'msu-insert-gallery' ) ).show();
						}
						$( '<span>' ).text( ' | ' ).appendTo( file.li );
						$( '<a>' ).text( mw.msg( 'msu-insert-image' ) ).click( function () {
							mw.toolbar.insertTags( '[[File:' + file.name + msuVars.imgParams + ']]', '', '', '' );
						} ).appendTo( file.li );
					} else if ( file.group === 'video' ) {
						$( '<span>' ).text( ' | ' ).appendTo( file.li );
						$( '<a>' ).text( mw.msg( 'msu-insert-video' ) ).click( function () {
							mw.toolbar.insertTags( '[[File:' + file.name + ']]', '', '', '' );
						} ).appendTo( file.li );
					}
					MsUpload.filesArray.push( file.name );
					if ( MsUpload.filesArray.length === 2 ) { // Bind click function only the first time
						$( '#msupload-insert-files' ).click( MsUpload.insertFiles ).text( mw.msg( 'msu-insert-files' ) ).show();
						$( '#msupload-insert-links' ).click( MsUpload.insertLinks ).text( mw.msg( 'msu-insert-links' ) ).show();
					}
				}
			} catch ( error ) {
				MsUpload.fileError( uploader, file, 'Error: ' + success.response.replace( /(<([^>]+)>)/ig, '' ) ); // Remove html tags
			}
			uploader.removeFile( file ); // For preventing a second upload afterwards
		},

		onCheckFiles: function ( uploader ) {
			var filesLength = uploader.files.length,
				listLength = $( '#msupload-list li' ).length;

			mw.log( 'files: ' + filesLength + ', gallery: ' + MsUpload.galleryArray.length + ', list: ' + listLength );

			if ( filesLength ) {
				$( '#msupload-bottom' ).show();
				if ( filesLength === 1 ) {
					$( '#msupload-files' ).text( mw.msg( 'msu-upload-this' ) ).show();
				} else {
					$( '#msupload-files' ).text( mw.msg( 'msu-upload-all' ) ).show();
				}
			} else {
				$( '#msupload-files' ).hide();
			}

			if ( MsUpload.unconfirmedReplacements ) {
				$( '#msupload-files' ).hide();
			}

			if ( MsUpload.filesArray.length > 1 ) {
				$( '#msupload-insert-files' ).show();
				$( '#msupload-insert-links' ).show();
			} else {
				$( '#msupload-insert-files' ).hide();
				$( '#msupload-insert-links' ).hide();
			}

			if ( MsUpload.galleryArray.length > 1 ) {
				$( '#msupload-insert-gallery' ).show();
				$( '#msupload-bottom' ).show();
			} else {
				$( '#msupload-insert-gallery' ).hide();
			}

			if ( listLength ) {
				$( '#msupload-bottom' ).show();
				$( '#msupload-clean-all' ).text( mw.msg( 'msu-clean-all' ) ).click( MsUpload.cleanAll ).show();
			} else {
				$( '#msupload-bottom' ).hide();
			}
			uploader.refresh(); // Reposition Flash/Silverlight
		},

		init: function () {
			if ( $.inArray( mw.config.get( 'wgAction' ), [ 'edit', 'submit' ] ) !== -1 ) {
				mw.loader.using( 'user.options', function () {
					if ( mw.user.options.get( 'usebetatoolbar' ) ) {
						mw.loader.using( 'ext.wikiEditor.toolbar', function () {
							$.when(
								mw.loader.using( 'ext.wikiEditor.toolbar' ), $.ready
							).then( MsUpload.createUploader );
						} );
					}
				} );
			}
		}
	};

	$( MsUpload.init );
}( jQuery, mediaWiki ) );
