/**
 * Fair Member Fees – admin member form.
 * - "Add member": show only the fields of the chosen account option.
 * - Choosing a user account fills in the first name, last name and e-mail of the account
 *   (REST famefe/v1/user-details/<id>) and warns when the account already belongs to another member.
 * - Delete links and buttons ask for confirmation (data-famefe-confirm).
 * Without JavaScript all fields stay visible and the server takes the details from the account itself.
 */
( function () {
	// Links and buttons with data-famefe-confirm ask before they delete something.
	document.addEventListener( 'click', ( event ) => {
		const element = event.target.closest( '[data-famefe-confirm]' );
		// eslint-disable-next-line no-alert
		if ( element && ! window.confirm( element.dataset.famefeConfirm ) ) {
			event.preventDefault();
		}
	} );

	function updateModes( form ) {
		const checked = form.querySelector( 'input[name="account_mode"]:checked' );
		const mode = checked ? checked.value : 'none';
		form.querySelectorAll( '[data-famefe-mode]' ).forEach( ( element ) => {
			element.hidden = element.dataset.famefeMode !== mode;
		} );
	}

	function warn( select, text ) {
		let note = select.parentNode.querySelector( '.famefe-account-warning' );
		if ( ! text ) {
			if ( note ) {
				note.remove();
			}
			return;
		}
		if ( ! note ) {
			note = document.createElement( 'p' );
			note.className = 'famefe-account-warning';
			select.insertAdjacentElement( 'afterend', note );
		}
		note.textContent = text;
	}

	async function fillFromAccount( form, select ) {
		const userId = parseInt( select.value, 10 );
		warn( select, '' );
		if ( ! userId ) {
			return;
		}
		let details;
		try {
			const response = await fetch( form.dataset.famefeDetails + userId, {
				headers: { 'X-WP-Nonce': form.dataset.famefeNonce },
				credentials: 'same-origin',
			} );
			if ( ! response.ok ) {
				return;
			}
			details = await response.json();
		} catch ( error ) {
			return;
		}
		if ( String( select.value ) !== String( userId ) ) {
			return; // Another account was chosen meanwhile.
		}
		const fields = {
			'famefe-first': details.first_name,
			'famefe-last': details.last_name,
			'famefe-email': details.email,
		};
		Object.entries( fields ).forEach( ( [ id, value ] ) => {
			const input = form.querySelector( '#' + id );
			if ( input && ! input.readOnly && value ) {
				input.value = value;
			}
		} );
		const own = form.querySelector( 'input[name="id"]' );
		if ( details.member_id && String( details.member_id ) !== ( own ? own.value : '' ) ) {
			warn( select, form.dataset.famefeTaken );
		}
	}

	// Lower case without diacritics, so that "sarka" finds "Šárka".
	function plain( text ) {
		return text.normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' ).toLowerCase();
	}

	/**
	 * Search field above the account list: keeps only the matching accounts in the list. When the chosen
	 * account no longer matches, the first match is chosen (and its details filled in).
	 */
	function setupSearch( form, select, input ) {
		const count = input.nextElementSibling;
		const all = Array.from( select.options );
		const none = all.find( ( option ) => option.value === '0' );
		input.hidden = false;
		input.addEventListener( 'input', () => {
			const words = plain( input.value ).split( /\s+/ ).filter( Boolean );
			const before = select.value;
			const matches = words.length
				? all.filter( ( option ) => {
						const text = plain( option.textContent );
						return option.value !== '0' && words.every( ( word ) => text.includes( word ) );
				  } )
				: all;
			select.replaceChildren( ...( matches.length ? matches : [ none ] ) );
			if ( ! matches.includes( all.find( ( option ) => option.value === before ) ) ) {
				select.value = select.options[ 0 ].value;
			} else {
				select.value = before;
			}
			if ( count ) {
				count.textContent = ! words.length
					? ''
					: matches.length
					? form.dataset.famefeFound.replace( '%d', matches.length )
					: form.dataset.famefeNone;
			}
			if ( select.value !== before ) {
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		} );
		// Enter in the search field must not send the form.
		input.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
			}
		} );
	}

	document.querySelectorAll( '.famefe-member-form' ).forEach( ( form ) => {
		const select = form.querySelector( '#famefe-user' );
		const search = form.querySelector( '.famefe-user-search' );
		if ( select && search ) {
			setupSearch( form, select, search );
		}
		const modes = form.querySelector( '.famefe-account-modes' );
		if ( modes ) {
			form.addEventListener( 'change', ( event ) => {
				if ( event.target.name === 'account_mode' ) {
					updateModes( form );
					if ( event.target.value === 'existing' && select ) {
						fillFromAccount( form, select );
					}
				}
			} );
			updateModes( form );
		}
		if ( select ) {
			select.addEventListener( 'change', () => fillFromAccount( form, select ) );
			// Account preselected from the profile link: fill the empty form at once.
			const first = form.querySelector( '#famefe-first' );
			if ( modes && parseInt( select.value, 10 ) && first && first.value === '' ) {
				fillFromAccount( form, select );
			}
		}
	} );
} )();
