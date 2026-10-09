/**
 * Fair Member Fees – admin: on "Add member", show only the fields of the chosen account option.
 * Without JavaScript all fields stay visible and the server uses the chosen option.
 */
( function () {
	function update( form ) {
		const checked = form.querySelector( 'input[name="account_mode"]:checked' );
		const mode = checked ? checked.value : 'none';
		form.querySelectorAll( '[data-famefe-mode]' ).forEach( ( element ) => {
			element.hidden = element.dataset.famefeMode !== mode;
		} );
	}

	document.querySelectorAll( '.famefe-account-modes' ).forEach( ( fieldset ) => {
		const form = fieldset.closest( 'form' );
		form.addEventListener( 'change', ( event ) => {
			if ( event.target.name === 'account_mode' ) {
				update( form );
			}
		} );
		update( form );
	} );
} )();
