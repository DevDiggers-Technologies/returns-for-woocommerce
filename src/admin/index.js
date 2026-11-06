/**
 * DevDiggers Returns admin screens. Delegated handlers, so they survive the framework's in-page navigation.
 */
import './style.less';

const i18n = ( window.ddwcwrAdminObj && window.ddwcwrAdminObj.i18n ) || {};

document.addEventListener( 'click', ( event ) => {
	const add = event.target.closest( '.ddwcwr-repeater-add' );

	if ( add ) {
		event.preventDefault();

		const section = add.closest( '.ddfw-fields-section' ) || document;
		const template = section.querySelector( '#ddwcwr-repeater-template' );
		const body = section.querySelector( '.ddwcwr-repeater tbody' );

		if ( template && body ) {
			body.insertAdjacentHTML( 'beforeend', template.innerHTML.replace( /__i__/g, String( Date.now() ) ) );

			const input = body.lastElementChild && body.lastElementChild.querySelector( 'input[type="text"]' );

			if ( input ) {
				input.focus();
			}
		}

		return;
	}

	const remove = event.target.closest( '.ddwcwr-repeater-remove' );

	if ( remove ) {
		event.preventDefault();
		remove.closest( 'tr' ).remove();
		return;
	}

	// Reset filters: empty every filter and the search box, then resubmit the list form (the status tab stays).
	const reset = event.target.closest( '.ddwcwr-reset-filters' );

	if ( reset ) {
		event.preventDefault();

		const form = reset.closest( 'form' );

		form.querySelectorAll( '.ddwcwr-filters select, .ddwcwr-filters input, input[name="s"]' ).forEach( ( field ) => {
			field.value = '';
		} );

		form.querySelectorAll( 'input[name="paged"]' ).forEach( ( field ) => field.remove() );
		form.submit();
		return;
	}

	const danger = event.target.closest( '.ddwcwr-confirm-delete' );

	if ( danger && ! window.confirm( i18n.confirmDelete ) ) { // eslint-disable-line no-alert
		event.preventDefault();
		return;
	}

	const process = event.target.closest( '.ddwcwr-confirm-process' );

	if ( process && ! window.confirm( i18n.confirmProcess ) ) { // eslint-disable-line no-alert
		event.preventDefault();
	}
} );

// Repeater rows: drag by the handle (jQuery UI Sortable ships with WordPress). Set up on first hover,
// because the framework swaps tab content without a page load.
if ( window.jQuery && window.jQuery.fn.sortable ) {
	window.jQuery( document ).on( 'mouseover focusin', '.ddwcwr-drag-handle', function () {
		const body = window.jQuery( this ).closest( 'tbody' );

		if ( ! body.data( 'ui-sortable' ) ) {
			body.sortable( {
				axis: 'y',
				handle: '.ddwcwr-drag-handle',
				placeholder: 'ddwcwr-sortable-placeholder',
				helper( event, row ) {
					// Keep cell widths while the row floats.
					row.children().each( function () {
						window.jQuery( this ).width( window.jQuery( this ).width() );
					} );

					return row;
				},
				start( event, ui ) {
					ui.placeholder.height( ui.item.outerHeight() ).html( '<td colspan="' + ui.item.children().length + '"></td>' );
				},
				stop( event, ui ) {
					ui.item.children().css( 'width', '' );
				},
			} );
		}
	} );
}

// Keyboard reordering on the drag handle.
document.addEventListener( 'keydown', ( event ) => {
	const handle = event.target.closest && event.target.closest( '.ddwcwr-drag-handle' );

	if ( ! handle || ( 'ArrowUp' !== event.key && 'ArrowDown' !== event.key ) ) {
		return;
	}

	event.preventDefault();

	const row = handle.closest( 'tr' );
	const sibling = 'ArrowUp' === event.key ? row.previousElementSibling : row.nextElementSibling;

	if ( sibling ) {
		row.parentNode.insertBefore( row, 'ArrowUp' === event.key ? sibling : sibling.nextElementSibling );
		handle.focus();
	}
} );

// One submit per click on request actions (refunds must never run twice).
document.addEventListener( 'submit', ( event ) => {
	const form = event.target;

	if ( ! form.classList || ! form.classList.contains( 'ddwcwr-admin-form' ) ) {
		return;
	}

	if ( form.dataset.submitting ) {
		event.preventDefault();
		return;
	}

	form.dataset.submitting = '1';
	form.querySelectorAll( 'button[type="submit"]' ).forEach( ( button ) => button.classList.add( 'is-busy' ) );
} );

// File pickers: show the chosen file names in the button.
document.addEventListener( 'change', ( event ) => {
	const picker = event.target.closest( '.ddwcwr-file-button' );

	if ( ! picker || 'file' !== event.target.type ) {
		return;
	}

	const name = picker.querySelector( '.ddwcwr-file-name' );
	const files = Array.from( event.target.files || [] ).map( ( file ) => file.name );

	name.textContent = files.length ? files.join( ', ' ) : name.dataset.empty;
} );

// Emails tab: turn an email on or off without leaving the page.
document.addEventListener( 'change', ( event ) => {
	const input = event.target;

	if ( ! input.matches( '.ddwcwr-email-toggle input' ) || ! window.ddwcwrAdminObj ) {
		return;
	}

	const body = new window.FormData();

	body.append( 'action', 'ddwcwr_toggle_email' );
	body.append( 'nonce', window.ddwcwrAdminObj.emailNonce );
	body.append( 'email', input.dataset.email );
	body.append( 'enabled', input.checked ? '1' : '' );
	input.disabled = true;

	window.fetch( window.ddwcwrAdminObj.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } )
		.then( ( response ) => response.json() )
		.then( ( result ) => {
			if ( ! result.success ) {
				throw new Error();
			}
		} )
		.catch( () => {
			input.checked = ! input.checked;
		} )
		.finally( () => {
			input.disabled = false;
		} );
} );

// Import tab: run one plugin's import a batch at a time, with live progress on its card.
document.addEventListener( 'click', ( event ) => {
	const button = event.target.closest( '.ddwcwr-import-run' );

	if ( ! button || ! window.ddwcwrAdminObj ) {
		return;
	}

	const card = button.closest( '.ddwcwr-import-card' );
	const status = card.querySelector( '.ddwcwr-import-status' );
	const bar = card.querySelector( '.ddwcwr-import-progress' );
	const buttons = card.querySelectorAll( '.ddwcwr-import-run' );
	const dryRun = !! button.dataset.dryRun;
	const count = Number( card.dataset.count ) || 0;
	const before = Number( card.dataset.imported ) || 0;
	const totals = { imported: 0, skipped: 0 };
	const format = ( text, ...values ) => values.reduce( ( out, value, index ) => out.replace( `%${ index + 1 }$d`, value ).replace( '%d', value ), text );
	const progress = ( value ) => {
		const percent = count ? Math.min( 100, Math.round( ( value / count ) * 100 ) ) : 100;

		bar.firstElementChild.style.width = `${ percent }%`;
		bar.setAttribute( 'aria-valuenow', percent );
	};
	const finish = () => {
		const imported = before + totals.imported;

		progress( imported );

		if ( dryRun ) {
			status.textContent = format( i18n.checked, totals.imported, totals.skipped );
			return;
		}

		status.textContent = totals.imported ? format( i18n.imported, totals.imported, totals.skipped ) : i18n.importedNone;
		status.classList.add( 'is-success' );
		card.dataset.imported = imported;
		card.classList.remove( 'is-ready', 'is-partial' );
		card.classList.add( 'is-done' );
		card.querySelector( '.ddwcwr-import-badge' ).textContent = i18n.importedBadge;
		card.querySelector( '.ddwcwr-import-imported-count' ).textContent = imported.toLocaleString();
		const go = card.querySelector( '.ddwcwr-import-go' );

		go.textContent = i18n.importNew;
		go.classList.remove( 'button-primary' );

		const total = document.querySelector( '.ddwcwr-import-total-imported .ddwcwr-import-stat-value' );
		const next = document.querySelector( '.ddwcwr-import-next' );

		if ( total ) {
			total.textContent = ( ( Number( total.textContent.replace( /\D/g, '' ) ) || 0 ) + totals.imported ).toLocaleString();
		}

		if ( next && totals.imported ) {
			next.classList.remove( 'is-hidden' );
		}
	};
	const run = ( cursor ) => {
		const body = new window.FormData();

		body.append( 'action', 'ddwcwr_import_batch' );
		body.append( 'nonce', window.ddwcwrAdminObj.importNonce );
		body.append( 'source', card.dataset.source );
		body.append( 'cursor', cursor );
		body.append( 'dry_run', dryRun ? '1' : '' );

		return window.fetch( window.ddwcwrAdminObj.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } )
			.then( ( response ) => response.json() )
			.then( ( result ) => {
				if ( ! result.success ) {
					throw new Error( typeof result.data === 'string' ? result.data : '' );
				}

				totals.imported += result.data.imported;
				totals.skipped += result.data.skipped;

				const done = totals.imported + totals.skipped;

				if ( ! result.data.done ) {
					status.textContent = format( dryRun ? i18n.checking : i18n.importing, done, count );
					progress( done );
					return run( result.data.cursor );
				}

				finish();
			} );
	};

	buttons.forEach( ( item ) => {
		item.disabled = true;
	} );
	button.classList.add( 'is-busy' );
	card.classList.add( 'is-running' );
	status.classList.remove( 'is-success', 'is-error' );
	status.textContent = format( dryRun ? i18n.checking : i18n.importing, 0, count );
	progress( 0 );

	run( '' )
		.catch( ( error ) => {
			status.textContent = error.message || i18n.importFailed;
			status.classList.add( 'is-error' );
			progress( Number( card.dataset.imported ) || 0 );
		} )
		.finally( () => {
			buttons.forEach( ( item ) => {
				item.disabled = false;
			} );
			button.classList.remove( 'is-busy' );
			card.classList.remove( 'is-running' );
		} );
} );
