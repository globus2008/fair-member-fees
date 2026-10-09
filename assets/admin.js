/**
 * Fair Member Fees – admin member form.
 * - "Add member": show only the fields of the chosen account option.
 * - Choosing a user account fills in the first name, last name and e-mail of the account
 *   (REST famefe/v1/user-details/<id>) and warns when the account already belongs to another member.
 * Without JavaScript all fields stay visible and the server takes the details from the account itself.
 */
( function () {
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

	document.querySelectorAll( '.famefe-member-form' ).forEach( ( form ) => {
		const select = form.querySelector( '#famefe-user' );
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
