/**
 * Fair Member Fees – sorting and searching of block tables (no dependencies).
 *
 * - <th data-sort="text|number"> gets a sort button; each cell may carry data-value with the raw value.
 *   Only tbody rows move, the totals in tfoot stay at the bottom.
 * - <input data-famefe-search="ID"> filters the rows of the table with id ID by their data-search text.
 * - Buttons with data-famefe-confirm (delete in the hours list) ask for confirmation.
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

	function cellValue( row, index, type ) {
		const cell = row.cells[ index ];
		if ( ! cell ) {
			return type === 'number' ? -Infinity : '';
		}
		const raw = cell.hasAttribute( 'data-value' )
			? cell.getAttribute( 'data-value' )
			: cell.textContent.trim();
		if ( type === 'number' ) {
			const number = parseFloat( raw );
			return isNaN( number ) ? -Infinity : number;
		}
		return raw.toLocaleLowerCase();
	}

	function sortTable( table, th ) {
		const index = th.cellIndex;
		const type = th.dataset.sort;
		const ascending = th.getAttribute( 'aria-sort' ) !== 'ascending';
		const collator = new Intl.Collator( document.documentElement.lang || undefined, {
			sensitivity: 'base',
		} );
		table.querySelectorAll( 'th[aria-sort]' ).forEach( ( other ) =>
			other.removeAttribute( 'aria-sort' )
		);
		th.setAttribute( 'aria-sort', ascending ? 'ascending' : 'descending' );
		const body = table.tBodies[ 0 ];
		const rows = Array.from( body.rows );
		rows.sort( ( a, b ) => {
			const x = cellValue( a, index, type );
			const y = cellValue( b, index, type );
			const order = type === 'number' ? x - y : collator.compare( x, y );
			return ascending ? order : -order;
		} );
		rows.forEach( ( row ) => body.appendChild( row ) );
	}

	function init( root ) {
		root.querySelectorAll( 'table.famefe-table th[data-sort]' ).forEach( ( th ) => {
			if ( th.querySelector( 'button' ) ) {
				return;
			}
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'famefe-sort';
			while ( th.firstChild ) {
				button.appendChild( th.firstChild );
			}
			th.appendChild( button );
			button.addEventListener( 'click', () =>
				sortTable( th.closest( 'table' ), th )
			);
		} );
		root.querySelectorAll( 'input[data-famefe-search]' ).forEach( ( input ) => {
			const table = document.getElementById( input.dataset.famefeSearch );
			if ( ! table ) {
				return;
			}
			input.hidden = false;
			input.addEventListener( 'input', () => {
				const query = input.value.trim().toLocaleLowerCase();
				Array.from( table.tBodies[ 0 ].rows ).forEach( ( row ) => {
					const text = ( row.dataset.search || row.textContent ).toLocaleLowerCase();
					row.hidden = query !== '' && ! text.includes( query );
				} );
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => init( document ) );
	} else {
		init( document );
	}
} )();
