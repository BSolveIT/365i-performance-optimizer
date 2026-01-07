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
