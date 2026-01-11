( function () {
	'use strict';

	const detectBtn = document.getElementById( 'i365-po-detect' );
	const toast     = document.getElementById( 'i365-po-toast' );
	const toastText = toast ? toast.querySelector( '.i365-po-toast__text' ) : null;
	const toastClose = toast ? toast.querySelector( '.i365-po-toast__close' ) : null;
	const logBox    = document.getElementById( 'i365-po-detect-log' );
	const logToggle = document.querySelector( '[name="i365_po_settings[enable_detect_log]"]' );
	const logWrap   = logBox ? logBox.parentElement : null;
	const fontSuggestions = document.getElementById( 'i365-po-font-suggestions' );
	if ( ! detectBtn || ! window.I365PODetect ) {
		return;
	}

	const data = window.I365PODetect;

	detectBtn.addEventListener( 'click', function ( event ) {
		event.preventDefault();

		const preconnect = document.querySelector( '[name="i365_po_settings[preconnect_hosts]"]' );
		const stylesheet = document.querySelector( '[name="i365_po_settings[preload_stylesheet]"]' );
		const font       = document.querySelector( '[name="i365_po_settings[preload_font]"]' );
		const hero       = document.querySelector( '[name="i365_po_settings[preload_hero]"]' );

		if ( toast && toastText && data.messages ) {
			toastText.textContent = data.messages.running || 'Detecting…';
			toast.classList.add( 'is-visible' );
		}

		// Call local AJAX detection.
		const formData = new FormData();
		formData.append( 'action', 'i365_po_detect' );
		formData.append( 'nonce', data.nonce );

		fetch( data.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		} )
			.then( ( res ) => res.json() )
			.then( ( payload ) => {
				if ( ! payload || ! payload.success ) {
					throw new Error( payload && payload.data && payload.data.message ? payload.data.message : 'Detection failed' );
				}

				const resp = payload.data;

				if ( preconnect ) {
					const hosts = resp.preconnect && Array.isArray( resp.preconnect ) ? resp.preconnect.filter( Boolean ).join( '\n' ) : [ data.home, data.fontsCdn ].filter( Boolean ).join( '\n' );
					preconnect.value = hosts;
				}

				if ( stylesheet && resp.stylesheet ) {
					stylesheet.value = resp.stylesheet;
				} else if ( stylesheet && data.stylesheet ) {
					stylesheet.value = data.stylesheet;
				}

				if ( font ) {
					if ( resp.font ) {
						font.value = resp.font;
					} else if ( data.font ) {
						font.value = data.font;
					} else if ( ! font.value ) {
						font.placeholder = font.placeholder || 'https://example.com/path-to-font.woff2';
					}
				}

				if ( fontSuggestions ) {
					renderFontSuggestions( resp.font_list || [], font );
				}

				if ( hero && resp.hero ) {
					hero.value = resp.hero;
				} else if ( hero && data.hero ) {
					hero.value = data.hero;
				}

				showStatus( preconnect, stylesheet, font, hero, data.messages.completed || 'Detection updated fields.' );
			} )
			.catch( ( err ) => {
				showStatus( preconnect, stylesheet, font, hero, ( data.messages && data.messages.failed ) || err.message || 'Detection failed', true );
				if ( logToggle && logToggle.checked && logBox ) {
					logBox.textContent = err.message || 'Detection failed';
					logBox.classList.add( 'is-visible' );
				}
			} );
	} );

	if ( toastClose && toast ) {
		toastClose.addEventListener( 'click', () => {
			toast.classList.remove( 'is-visible' );
		} );
	}

	if ( logToggle && logBox ) {
		logToggle.addEventListener( 'change', () => {
			if ( ! logToggle.checked ) {
				logBox.classList.remove( 'is-visible' );
			}
		} );
	}

	function showStatus( preconnect, stylesheet, font, hero, message, isError = false ) {
		if ( toast && toastText ) {
			const parts = [];
			if ( preconnect && preconnect.value ) {
				parts.push( 'Preconnect hosts set' );
			}
			if ( stylesheet && stylesheet.value ) {
				parts.push( 'Stylesheet detected' );
			}
			if ( hero && hero.value ) {
				parts.push( 'Hero image detected' );
			}
			if ( font && font.value ) {
				parts.push( 'Font detected' );
			} else {
				parts.push( 'No font detected—add your primary font URL if needed' );
			}

			toastText.textContent = parts.join( '. ' ) + '. ' + ( message || '' );
			toast.classList.remove( 'is-error' );
			if ( isError ) {
				toast.classList.add( 'is-error' );
			}
			toast.classList.add( 'is-visible' );

			clearTimeout( toast.dataset.timeoutId );
			const timeoutId = setTimeout( () => {
				toast.classList.remove( 'is-visible' );
			}, 10000 );
			toast.dataset.timeoutId = timeoutId;
		}

		if ( logToggle && logToggle.checked && logBox ) {
			const lines = [];
			lines.push( `[${ new Date().toLocaleTimeString() }] Auto-detect run:` );
			lines.push( `Preconnect: ${ preconnect && preconnect.value ? preconnect.value.replace(/\n/g, ', ') : 'n/a' }` );
			lines.push( `Stylesheet: ${ stylesheet && stylesheet.value ? stylesheet.value : 'n/a' }` );
			lines.push( `Font: ${ font && font.value ? font.value : 'n/a' }` );
			lines.push( `Hero: ${ hero && hero.value ? hero.value : 'n/a' }` );
			if ( message ) {
				lines.push( `Status: ${ message }` );
			}
			logBox.textContent = lines.join( '\n' );
			logBox.classList.add( 'is-visible' );
			if ( logWrap ) {
				logWrap.classList.add( 'is-visible' );
			}
		}
	}

	function renderFontSuggestions( list, fontInput ) {
		if ( ! fontSuggestions ) {
			return;
		}
		fontSuggestions.innerHTML = '';
		if ( ! list || ! list.length ) {
			return;
		}

		const title = document.createElement( 'div' );
		title.textContent = 'Detected font candidates (click to use):';
		title.className = 'i365-po-suggestions__title';
		fontSuggestions.appendChild( title );

		list.slice( 0, 4 ).forEach( ( url ) => {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'button button-secondary i365-po-suggestions__btn';
			btn.textContent = url;
			btn.title = 'Use this font URL';
			btn.addEventListener( 'click', () => {
				if ( fontInput ) {
					fontInput.value = url;
				}
			} );
			fontSuggestions.appendChild( btn );
		} );
	}
}() );

/**
 * Utilities: Backups, Profiles, Import/Export
 */
( function () {
	'use strict';

	if ( ! window.I365POUtilities ) {
		return;
	}

	const utils = window.I365POUtilities;
	const toast = document.getElementById( 'i365-po-toast' );
	const toastText = toast ? toast.querySelector( '.i365-po-toast__text' ) : null;

	/**
	 * Show a toast message.
	 */
	function showToast( message, isError = false ) {
		if ( ! toast || ! toastText ) {
			return;
		}
		toastText.textContent = message;
		toast.classList.remove( 'is-error' );
		if ( isError ) {
			toast.classList.add( 'is-error' );
		}
		toast.classList.add( 'is-visible' );

		clearTimeout( toast.dataset.timeoutId );
		const timeoutId = setTimeout( () => {
			toast.classList.remove( 'is-visible' );
		}, 8000 );
		toast.dataset.timeoutId = timeoutId;
	}

	/**
	 * Make an AJAX request.
	 */
	function ajaxRequest( action, data = {} ) {
		const formData = new FormData();
		formData.append( 'action', action );
		formData.append( 'nonce', utils.nonce );

		for ( const key in data ) {
			formData.append( key, data[ key ] );
		}

		return fetch( utils.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		} ).then( ( res ) => res.json() );
	}

	/*
	|--------------------------------------------------------------------------
	| Backup & Restore
	|--------------------------------------------------------------------------
	*/

	// Restore backup
	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'i365-po-restore-backup' ) ) {
			return;
		}

		const timestamp = e.target.dataset.timestamp;
		if ( ! timestamp ) {
			return;
		}

		if ( ! confirm( utils.messages.confirmRestore ) ) {
			return;
		}

		e.target.disabled = true;
		e.target.textContent = utils.messages.processing;

		ajaxRequest( 'i365_po_restore_backup', { timestamp: timestamp } )
			.then( ( response ) => {
				if ( response.success ) {
					showToast( response.data.message );
					if ( response.data.reload ) {
						setTimeout( () => window.location.reload(), 1000 );
					}
				} else {
					showToast( response.data.message || utils.messages.error, true );
					e.target.disabled = false;
					e.target.textContent = 'Restore';
				}
			} )
			.catch( () => {
				showToast( utils.messages.error, true );
				e.target.disabled = false;
				e.target.textContent = 'Restore';
			} );
	} );

	// Delete backup
	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'i365-po-delete-backup' ) ) {
			return;
		}

		const timestamp = e.target.dataset.timestamp;
		if ( ! timestamp ) {
			return;
		}

		if ( ! confirm( utils.messages.confirmDeleteBackup ) ) {
			return;
		}

		e.target.disabled = true;

		ajaxRequest( 'i365_po_delete_backup', { timestamp: timestamp } )
			.then( ( response ) => {
				if ( response.success ) {
					showToast( response.data.message );
					const container = document.getElementById( 'i365-po-backups-container' );
					if ( container && response.data.backups ) {
						container.innerHTML = response.data.backups;
					}
				} else {
					showToast( response.data.message || utils.messages.error, true );
					e.target.disabled = false;
				}
			} )
			.catch( () => {
				showToast( utils.messages.error, true );
				e.target.disabled = false;
			} );
	} );

	/*
	|--------------------------------------------------------------------------
	| Profiles
	|--------------------------------------------------------------------------
	*/

	// Apply profile
	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'i365-po-apply-profile' ) ) {
			return;
		}

		const profile = e.target.dataset.profile;
		if ( ! profile ) {
			return;
		}

		if ( ! confirm( utils.messages.confirmApplyProfile ) ) {
			return;
		}

		e.target.disabled = true;
		e.target.textContent = utils.messages.processing;

		ajaxRequest( 'i365_po_apply_profile', { profile: profile } )
			.then( ( response ) => {
				if ( response.success ) {
					showToast( response.data.message );
					if ( response.data.reload ) {
						setTimeout( () => window.location.reload(), 1000 );
					}
				} else {
					showToast( response.data.message || utils.messages.error, true );
					e.target.disabled = false;
					e.target.textContent = 'Apply';
				}
			} )
			.catch( () => {
				showToast( utils.messages.error, true );
				e.target.disabled = false;
				e.target.textContent = 'Apply';
			} );
	} );

	// Save profile
	const saveProfileBtn = document.getElementById( 'i365-po-save-profile' );
	if ( saveProfileBtn ) {
		saveProfileBtn.addEventListener( 'click', function () {
			const nameInput = document.getElementById( 'i365-po-profile-name' );
			const descInput = document.getElementById( 'i365-po-profile-desc' );

			const name = nameInput ? nameInput.value.trim() : '';
			const description = descInput ? descInput.value.trim() : '';

			if ( ! name ) {
				showToast( utils.messages.profileNameRequired, true );
				if ( nameInput ) {
					nameInput.focus();
				}
				return;
			}

			saveProfileBtn.disabled = true;
			saveProfileBtn.textContent = utils.messages.processing;

			ajaxRequest( 'i365_po_save_profile', { name: name, description: description } )
				.then( ( response ) => {
					if ( response.success ) {
						showToast( response.data.message );
						const container = document.getElementById( 'i365-po-profiles-container' );
						if ( container && response.data.profiles ) {
							container.innerHTML = response.data.profiles;
						}
						if ( nameInput ) {
							nameInput.value = '';
						}
						if ( descInput ) {
							descInput.value = '';
						}
					} else {
						showToast( response.data.message || utils.messages.error, true );
					}
					saveProfileBtn.disabled = false;
					saveProfileBtn.textContent = 'Save Current as Profile';
				} )
				.catch( () => {
					showToast( utils.messages.error, true );
					saveProfileBtn.disabled = false;
					saveProfileBtn.textContent = 'Save Current as Profile';
				} );
		} );
	}

	// Delete profile
	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'i365-po-delete-profile' ) ) {
			return;
		}

		const profile = e.target.dataset.profile;
		if ( ! profile ) {
			return;
		}

		if ( ! confirm( utils.messages.confirmDeleteProfile ) ) {
			return;
		}

		e.target.disabled = true;

		ajaxRequest( 'i365_po_delete_profile', { profile: profile } )
			.then( ( response ) => {
				if ( response.success ) {
					showToast( response.data.message );
					const container = document.getElementById( 'i365-po-profiles-container' );
					if ( container && response.data.profiles ) {
						container.innerHTML = response.data.profiles;
					}
				} else {
					showToast( response.data.message || utils.messages.error, true );
					e.target.disabled = false;
				}
			} )
			.catch( () => {
				showToast( utils.messages.error, true );
				e.target.disabled = false;
			} );
	} );

	/*
	|--------------------------------------------------------------------------
	| Import / Export
	|--------------------------------------------------------------------------
	*/

	// Export
	const exportBtn = document.getElementById( 'i365-po-export' );
	if ( exportBtn ) {
		exportBtn.addEventListener( 'click', function () {
			exportBtn.disabled = true;
			exportBtn.textContent = utils.messages.processing;

			ajaxRequest( 'i365_po_export' )
				.then( ( response ) => {
					if ( response.success && response.data.json ) {
						// Create and trigger download.
						const blob = new Blob( [ response.data.json ], { type: 'application/json' } );
						const url = URL.createObjectURL( blob );
						const a = document.createElement( 'a' );
						a.href = url;
						a.download = response.data.filename || 'performance-optimizer-settings.json';
						document.body.appendChild( a );
						a.click();
						document.body.removeChild( a );
						URL.revokeObjectURL( url );
						showToast( 'Settings exported successfully.' );
					} else {
						showToast( response.data.message || utils.messages.error, true );
					}
					exportBtn.disabled = false;
					exportBtn.textContent = 'Export Settings';
				} )
				.catch( () => {
					showToast( utils.messages.error, true );
					exportBtn.disabled = false;
					exportBtn.textContent = 'Export Settings';
				} );
		} );
	}

	// Import
	const importBtn = document.getElementById( 'i365-po-import-btn' );
	const importFile = document.getElementById( 'i365-po-import-file' );
	const importFilename = document.getElementById( 'i365-po-import-filename' );

	if ( importBtn && importFile ) {
		importBtn.addEventListener( 'click', function () {
			importFile.click();
		} );

		importFile.addEventListener( 'change', function () {
			const file = importFile.files[ 0 ];
			if ( ! file ) {
				return;
			}

			if ( ! file.name.endsWith( '.json' ) ) {
				showToast( utils.messages.invalidFile, true );
				importFile.value = '';
				return;
			}

			if ( importFilename ) {
				importFilename.textContent = file.name;
			}

			if ( ! confirm( utils.messages.confirmImport ) ) {
				importFile.value = '';
				if ( importFilename ) {
					importFilename.textContent = '';
				}
				return;
			}

			importBtn.disabled = true;
			importBtn.textContent = utils.messages.processing;

			const reader = new FileReader();
			reader.onload = function ( e ) {
				const json = e.target.result;

				ajaxRequest( 'i365_po_import', { json: json } )
					.then( ( response ) => {
						if ( response.success ) {
							showToast( response.data.message );
							if ( response.data.reload ) {
								setTimeout( () => window.location.reload(), 1000 );
							}
						} else {
							showToast( response.data.message || utils.messages.error, true );
						}
						importBtn.disabled = false;
						importBtn.textContent = 'Import Settings';
						importFile.value = '';
						if ( importFilename ) {
							importFilename.textContent = '';
						}
					} )
					.catch( () => {
						showToast( utils.messages.error, true );
						importBtn.disabled = false;
						importBtn.textContent = 'Import Settings';
						importFile.value = '';
						if ( importFilename ) {
							importFilename.textContent = '';
						}
					} );
			};

			reader.readAsText( file );
		} );
	}

	/*
	|--------------------------------------------------------------------------
	| Database Cleanup
	|--------------------------------------------------------------------------
	*/

	const dbAnalyzeBtn = document.getElementById( 'i365-po-db-analyze' );
	const dbCleanupBtn = document.getElementById( 'i365-po-db-cleanup' );
	const dbStatsContainer = document.getElementById( 'i365-po-db-stats-container' );

	if ( dbAnalyzeBtn ) {
		dbAnalyzeBtn.addEventListener( 'click', function () {
			dbAnalyzeBtn.disabled = true;
			dbAnalyzeBtn.textContent = utils.messages.processing || 'Analyzing...';

			ajaxRequest( 'i365_po_db_analyze' )
				.then( ( response ) => {
					if ( response.success && response.data.html ) {
						dbStatsContainer.innerHTML = response.data.html;
						updateCleanupButton();
						showToast( 'Database analyzed.' );
					} else {
						showToast( response.data.message || utils.messages.error, true );
					}
					dbAnalyzeBtn.disabled = false;
					dbAnalyzeBtn.textContent = 'Analyze Database';
				} )
				.catch( () => {
					showToast( utils.messages.error, true );
					dbAnalyzeBtn.disabled = false;
					dbAnalyzeBtn.textContent = 'Analyze Database';
				} );
		} );
	}

	if ( dbCleanupBtn ) {
		dbCleanupBtn.addEventListener( 'click', function () {
			const checkboxes = dbStatsContainer.querySelectorAll( 'input[name="db_cleanup_items[]"]:checked' );
			const items = Array.from( checkboxes ).map( ( cb ) => cb.value );

			if ( items.length === 0 ) {
				showToast( 'Please select items to clean up.', true );
				return;
			}

			if ( ! confirm( 'Clean up selected items? A backup will be created first.' ) ) {
				return;
			}

			dbCleanupBtn.disabled = true;
			dbCleanupBtn.textContent = utils.messages.processing || 'Cleaning...';

			// Send items as JSON since FormData doesn't handle arrays well.
			const formData = new FormData();
			formData.append( 'action', 'i365_po_db_cleanup' );
			formData.append( 'nonce', utils.nonce );
			items.forEach( ( item ) => formData.append( 'items[]', item ) );

			fetch( utils.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			} )
				.then( ( res ) => res.json() )
				.then( ( response ) => {
					if ( response.success ) {
						showToast( response.data.message );
						if ( response.data.html ) {
							dbStatsContainer.innerHTML = response.data.html;
						}
						updateCleanupButton();
					} else {
						showToast( response.data.message || utils.messages.error, true );
					}
					dbCleanupBtn.disabled = false;
					dbCleanupBtn.textContent = 'Clean Selected';
				} )
				.catch( () => {
					showToast( utils.messages.error, true );
					dbCleanupBtn.disabled = false;
					dbCleanupBtn.textContent = 'Clean Selected';
				} );
		} );
	}

	// Update cleanup button state based on checkbox selection.
	function updateCleanupButton() {
		if ( ! dbCleanupBtn || ! dbStatsContainer ) {
			return;
		}

		const hasChecked = dbStatsContainer.querySelectorAll( 'input[name="db_cleanup_items[]"]:checked' ).length > 0;
		const hasCheckable = dbStatsContainer.querySelectorAll( 'input[name="db_cleanup_items[]"]:not(:disabled)' ).length > 0;

		dbCleanupBtn.disabled = ! hasChecked;

		// Also listen for checkbox changes.
		const checkboxes = dbStatsContainer.querySelectorAll( 'input[name="db_cleanup_items[]"]' );
		checkboxes.forEach( ( cb ) => {
			cb.addEventListener( 'change', function () {
				const checked = dbStatsContainer.querySelectorAll( 'input[name="db_cleanup_items[]"]:checked' ).length > 0;
				dbCleanupBtn.disabled = ! checked;
			} );
		} );
	}

	/*
	|--------------------------------------------------------------------------
	| Local Fonts
	|--------------------------------------------------------------------------
	*/

	const downloadFontsBtn = document.getElementById( 'i365-po-download-fonts' );
	const clearFontsBtn = document.getElementById( 'i365-po-clear-fonts' );
	const fontsUrlInput = document.getElementById( 'i365-po-fonts-url' );
	const fontsStatus = document.getElementById( 'i365-po-fonts-status' );

	if ( downloadFontsBtn ) {
		downloadFontsBtn.addEventListener( 'click', function () {
			downloadFontsBtn.disabled = true;
			downloadFontsBtn.textContent = utils.messages.processing || 'Downloading...';

			const url = fontsUrlInput ? fontsUrlInput.value.trim() : '';

			ajaxRequest( 'i365_po_download_fonts', { url: url } )
				.then( ( response ) => {
					if ( response.success ) {
						showToast( response.data.message );
						updateFontsStatus( response.data.fonts_info );
					} else {
						showToast( response.data.message || utils.messages.error, true );
					}
					downloadFontsBtn.disabled = false;
					downloadFontsBtn.textContent = 'Download Fonts';
				} )
				.catch( () => {
					showToast( utils.messages.error, true );
					downloadFontsBtn.disabled = false;
					downloadFontsBtn.textContent = 'Download Fonts';
				} );
		} );
	}

	if ( clearFontsBtn ) {
		clearFontsBtn.addEventListener( 'click', function () {
			if ( ! confirm( 'Clear all downloaded local fonts? You will need to download them again.' ) ) {
				return;
			}

			clearFontsBtn.disabled = true;
			clearFontsBtn.textContent = utils.messages.processing || 'Clearing...';

			ajaxRequest( 'i365_po_clear_fonts' )
				.then( ( response ) => {
					if ( response.success ) {
						showToast( response.data.message );
						updateFontsStatus( response.data.fonts_info );
					} else {
						showToast( response.data.message || utils.messages.error, true );
					}
					clearFontsBtn.disabled = false;
					clearFontsBtn.textContent = 'Clear Local Fonts';
				} )
				.catch( () => {
					showToast( utils.messages.error, true );
					clearFontsBtn.disabled = false;
					clearFontsBtn.textContent = 'Clear Local Fonts';
				} );
		} );
	}

	/**
	 * Update fonts status display.
	 */
	function updateFontsStatus( info ) {
		if ( ! fontsStatus || ! info ) {
			return;
		}

		let html = '';
		if ( info.has_fonts ) {
			html = '<div class="i365-po-fonts-info">';
			html += '<p><strong>Status:</strong> ' + info.font_count + ' font files downloaded</p>';
			if ( info.downloaded ) {
				const downloadedDate = new Date( info.downloaded * 1000 );
				html += '<p><strong>Downloaded:</strong> ' + downloadedDate.toLocaleString() + '</p>';
			}
			if ( info.disk_usage > 0 ) {
				html += '<p><strong>Disk usage:</strong> ' + formatBytes( info.disk_usage ) + '</p>';
			}
			html += '</div>';
		} else {
			html = '<p class="i365-po-fonts-empty">No local fonts downloaded yet. Click "Download Fonts" to scan your site and download Google Fonts locally.</p>';
		}

		fontsStatus.innerHTML = html;

		// Update clear button state.
		if ( clearFontsBtn ) {
			clearFontsBtn.disabled = ! info.has_fonts;
		}
	}

	/**
	 * Format bytes to human readable size.
	 */
	function formatBytes( bytes ) {
		if ( bytes === 0 ) {
			return '0 Bytes';
		}
		const k = 1024;
		const sizes = [ 'Bytes', 'KB', 'MB', 'GB' ];
		const i = Math.floor( Math.log( bytes ) / Math.log( k ) );
		return parseFloat( ( bytes / Math.pow( k, i ) ).toFixed( 2 ) ) + ' ' + sizes[ i ];
	}
}() );
