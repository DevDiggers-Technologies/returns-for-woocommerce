/**
 * WPReturns customer screens: request form and uploads.
 */
import './style.less';

const settings = window.ddwcwrFrontObj || { i18n: {} };
const i18n = settings.i18n || {};

const showError = ( form, message ) => {
	const box = form.querySelector( '.ddwcwr-form-error' );

	if ( box ) {
		box.textContent = message;
		box.hidden = ! message;

		if ( message ) {
			box.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
	} else if ( message ) {
		window.alert( message ); // eslint-disable-line no-alert
	}
};

const updatePhotoHint = ( form ) => {
	const hint = form.querySelector( '.ddwcwr-photo-required' );

	if ( ! hint ) {
		return false;
	}

	const needed = Array.from( form.querySelectorAll( '.ddwcwr-item.is-selected .ddwcwr-reason' ) ).some(
		( select ) => select.selectedOptions[ 0 ] && 'yes' === select.selectedOptions[ 0 ].dataset.photo
	);

	hint.hidden = ! needed;

	return ! hint.hidden;
};

const initItems = ( form ) => {
	form.querySelectorAll( '.ddwcwr-item-toggle' ).forEach( ( toggle ) => {
		const item = toggle.closest( '.ddwcwr-item' );
		const details = item.querySelector( '.ddwcwr-item-details' );

		const sync = () => {
			item.classList.toggle( 'is-selected', toggle.checked );

			if ( details ) {
				details.hidden = ! toggle.checked;
				details.querySelectorAll( '.ddwcwr-reason' ).forEach( ( field ) => {
					field.required = toggle.checked;
				} );
			}

			updatePhotoHint( form );
		};

		toggle.addEventListener( 'change', sync );
		sync();
	} );

	form.querySelectorAll( '.ddwcwr-reason' ).forEach( ( select ) => {
		select.addEventListener( 'change', () => updatePhotoHint( form ) );
	} );
};

const initFiles = ( form ) => {
	form.querySelectorAll( 'input[type="file"]' ).forEach( ( input ) => {
		const list = input.parentElement.querySelector( '.ddwcwr-file-list' );

		input.addEventListener( 'change', () => {
			const files = Array.from( input.files || [] );
			let error = '';

			if ( settings.maxFiles && files.length > settings.maxFiles ) {
				error = i18n.tooMany;
			}

			files.forEach( ( file ) => {
				if ( settings.maxSize && file.size > settings.maxSize * 1024 * 1024 ) {
					error = ( i18n.tooBig || '%s' ).replace( '%s', file.name );
				}
			} );

			if ( list ) {
				list.textContent = files.map( ( file ) => file.name ).join( ', ' );
			}

			if ( error ) {
				input.value = '';
				showError( form, error );
			} else {
				showError( form, '' );
			}
		} );
	} );
};

const initSubmit = ( form ) => {
	form.addEventListener( 'submit', ( event ) => {
		if ( form.classList.contains( 'ddwcwr-request-form' ) ) {
			const selected = form.querySelectorAll( '.ddwcwr-item-toggle:checked' );

			if ( ! selected.length ) {
				event.preventDefault();
				showError( form, i18n.selectItem );
				return;
			}
		}

		const button = form.querySelector( '[type="submit"]' );

		if ( form.dataset.submitting ) {
			event.preventDefault();
			return;
		}

		form.dataset.submitting = '1';

		if ( button ) {
			button.setAttribute( 'aria-disabled', 'true' );
			button.classList.add( 'is-busy' );
		}
	} );
};

document.addEventListener( 'DOMContentLoaded', () => {
	document.querySelectorAll( '.ddwcwr-form' ).forEach( ( form ) => {
		initItems( form );
		initFiles( form );
		initSubmit( form );
	} );

	document.querySelectorAll( '[data-confirm]' ).forEach( ( button ) => {
		button.addEventListener( 'click', ( event ) => {
			if ( ! window.confirm( i18n.confirmLeave ) ) { // eslint-disable-line no-alert
				event.preventDefault();
			}
		} );
	} );
} );

// Quantity steppers on the request form.
const syncStepper = ( stepper ) => {
	const input = stepper.querySelector( 'input' );
	const min = parseInt( input.min, 10 ) || 1;
	const max = parseInt( input.max, 10 ) || min;
	const value = Math.min( max, Math.max( min, parseInt( input.value, 10 ) || min ) );

	input.value = value;
	stepper.querySelector( '[data-step="-1"]' ).disabled = value <= min;
	stepper.querySelector( '[data-step="1"]' ).disabled = value >= max;
};

document.addEventListener( 'click', ( event ) => {
	const button = event.target.closest( '.ddwcwr-stepper button' );

	if ( ! button ) {
		return;
	}

	const stepper = button.closest( '.ddwcwr-stepper' );
	const input = stepper.querySelector( 'input' );

	input.value = ( parseInt( input.value, 10 ) || 0 ) + parseInt( button.dataset.step, 10 );
	syncStepper( stepper );
} );

document.addEventListener( 'change', ( event ) => {
	const stepper = event.target.closest( '.ddwcwr-stepper' );

	if ( stepper ) {
		syncStepper( stepper );
	}
} );
