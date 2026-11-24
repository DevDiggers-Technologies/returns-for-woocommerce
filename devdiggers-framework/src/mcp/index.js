/**
 * DevDiggers AI & MCP hub page.
 */

import './mcp.less';

( () => {
	const data = window.ddfwMcpObject;

	if ( ! data ) {
		return;
	}

	const i18n = data.i18n;
	const $    = selector => document.querySelector( selector );

	/**
	 * POST an admin-ajax action.
	 *
	 * @param {string} action Action without the ddfw_mcp_ prefix.
	 * @param {Object} fields Fields.
	 * @return {Promise<Object>} The response's data. Rejects with a message.
	 */
	const post = ( action, fields = {} ) => fetch( data.ajaxUrl, {
		method     : 'POST',
		credentials: 'same-origin',
		body       : new URLSearchParams( { action: `ddfw_mcp_${ action }`, nonce: data.nonce, ...fields } ),
	} )
		.then( response => response.json() )
		.then( response => {
			if ( ! response || ! response.success ) {
				throw new Error( ( response && response.data && response.data.message ) || i18n.error );
			}

			return response.data;
		} );

	/**
	 * Small toast at the bottom of the screen.
	 *
	 * @param {string}  message Message.
	 * @param {boolean} isError Error style.
	 */
	const toast = ( message, isError = false ) => {
		const element = document.createElement( 'div' );

		element.className   = `ddfw-mcp-toast${ isError ? ' is-error' : '' }`;
		element.textContent = message;
		document.body.appendChild( element );
		setTimeout( () => element.classList.add( 'is-visible' ), 10 );
		setTimeout( () => {
			element.classList.remove( 'is-visible' );
			setTimeout( () => element.remove(), 300 );
		}, 2200 );
	};

	/**
	 * Copy text and say so on the button for a moment.
	 *
	 * @param {HTMLElement} button Button.
	 * @param {string}      text   Text.
	 */
	const copyText = ( button, text ) => navigator.clipboard.writeText( text ).then( () => {
		const label    = button.querySelector( 'span' ) || button;
		const original = label.textContent;

		button.classList.add( 'is-copied' );
		label.textContent = i18n.copied;
		setTimeout( () => {
			button.classList.remove( 'is-copied' );
			label.textContent = original;
		}, 1500 );
	} );

	/**
	 * Show one step of the Connect dialog.
	 *
	 * @param {string} step 'pick' or an app.
	 */
	const showStep = step => document.querySelectorAll( '#ddfw-mcp-connect [data-step]' ).forEach( element => {
		element.hidden = element.dataset.step !== step;
	} );

	/*
	 * Dialogs: open, close, steps, copy.
	 */
	document.addEventListener( 'click', event => {
		const open   = event.target.closest( '[data-open]' );
		const close  = event.target.closest( '[data-close]' );
		const client = event.target.closest( '[data-client]' );
		const copy   = event.target.closest( '[data-copy], [data-copy-text]' );

		if ( open ) {
			$( open.dataset.open ).showModal();
		}

		if ( close ) {
			close.closest( 'dialog' ).close();
		}

		if ( client ) {
			showStep( client.dataset.client );
		}

		if ( event.target.closest( '[data-back]' ) ) {
			showStep( 'pick' );
		}

		if ( copy ) {
			copyText( copy, undefined !== copy.dataset.copyText ? copy.dataset.copyText : $( copy.dataset.copy ).textContent );
		}
	} );

	// The Connect dialog opens on the app list again.
	const connect = $( '#ddfw-mcp-connect' );

	if ( connect ) {
		connect.addEventListener( 'close', () => showStep( 'pick' ) );
	}

	// A click on the backdrop closes a dialog.
	document.querySelectorAll( 'dialog.ddfw-mcp-dialog' ).forEach( dialog => {
		dialog.addEventListener( 'click', event => {
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );
	} );

	/*
	 * Switches: a plugin, a write tool.
	 */
	document.querySelectorAll( 'input[data-key]' ).forEach( input => {
		input.addEventListener( 'change', () => {
			input.disabled = true;

			post( 'save', { key: input.dataset.key, id: input.dataset.id, value: input.checked ? '1' : '' } )
				.then( () => {
					if ( 'plugin' === input.dataset.key ) {
						input.closest( '.ddfw-mcp-plugin' ).classList.toggle( 'is-off', ! input.checked );
					}

					toast( i18n.saved );
				} )
				.catch( error => {
					input.checked = ! input.checked;
					toast( error.message, true );
				} )
				.finally( () => {
					input.disabled = false;
				} );
		} );
	} );

	document.querySelectorAll( '[data-revoke]' ).forEach( button => {
		button.addEventListener( 'click', () => {
			if ( window.confirm( i18n.revokeAsk ) ) {
				post( 'revoke', { id: button.dataset.revoke, user: button.dataset.user } )
					.then( () => {
						const list = button.closest( 'ul' );

						button.closest( 'li' ).remove();

						if ( ! list.children.length ) {
							list.parentElement.remove();
						}
					} )
					.catch( error => toast( error.message, true ) );
			}
		} );
	} );

	/*
	 * Try: the tool's inputs, built from its input schema, and the response.
	 */
	const tryDialog = $( '#ddfw-mcp-try' );
	const inputsBox = $( '#ddfw-mcp-inputs' );
	const runButton = $( '#ddfw-mcp-run-button' );
	const output    = $( '#ddfw-mcp-output' );
	const status    = $( '#ddfw-mcp-output-status' );
	let current     = '';

	/**
	 * One input for a schema property.
	 *
	 * @param {string}  name     Property name.
	 * @param {Object}  prop     Property schema.
	 * @param {boolean} required Required.
	 * @return {HTMLElement} Field wrapper.
	 */
	const buildField = ( name, prop, required ) => {
		const wrap  = document.createElement( 'div' );
		const label = document.createElement( 'label' );
		const id    = `ddfw-mcp-in-${ name }`;
		let control;

		wrap.className    = 'ddfw-mcp-input';
		wrap.dataset.name = name;
		wrap.dataset.type = prop.type || 'string';
		label.htmlFor     = id;
		label.textContent = name.replace( /_/g, ' ' ) + ( required ? ' *' : '' );
		wrap.appendChild( label );

		const option = ( value, text ) => {
			const element = document.createElement( 'option' );

			element.value       = value;
			element.textContent = text;
			return element;
		};

		if ( 'array' === prop.type && prop.items && prop.items.enum ) {
			control = document.createElement( 'div' );
			control.className = 'ddfw-mcp-checks';

			prop.items.enum.forEach( value => {
				const check = document.createElement( 'label' );
				const box   = document.createElement( 'input' );

				box.type  = 'checkbox';
				box.value = value;
				check.append( box, value.replace( /_/g, ' ' ) );
				control.appendChild( check );
			} );
		} else if ( prop.enum || 'boolean' === prop.type ) {
			control = document.createElement( 'select' );
			control.appendChild( option( '', required ? '' : i18n.any ) );

			( prop.enum || [ 'true', 'false' ] ).forEach( value => control.appendChild( option( String( value ), String( value ) ) ) );

			if ( undefined !== prop.default ) {
				control.value = String( prop.default );
			}
		} else {
			control = document.createElement( 'input' );

			if ( 'integer' === prop.type || 'number' === prop.type ) {
				control.type = 'number';
				control.step = 'integer' === prop.type ? '1' : 'any';
			} else {
				control.type = prop.pattern && prop.pattern.indexOf( '\\d{4}-\\d{2}-\\d{2}' ) > -1 ? 'date' : 'text';
			}

			control.required = required;

			if ( undefined !== prop.default ) {
				control.placeholder = String( prop.default );
			}
		}

		control.id = id;
		wrap.appendChild( control );

		if ( prop.description ) {
			const help = document.createElement( 'small' );

			help.textContent = prop.description;
			wrap.appendChild( help );
		}

		return wrap;
	};

	const collectInput = () => {
		const input = {};

		inputsBox.querySelectorAll( '.ddfw-mcp-input' ).forEach( wrap => {
			const name = wrap.dataset.name;
			const type = wrap.dataset.type;

			if ( 'array' === type ) {
				const values = [ ...wrap.querySelectorAll( 'input:checked' ) ].map( box => box.value );

				if ( values.length ) {
					input[ name ] = values;
				}

				return;
			}

			const value = wrap.querySelector( 'input, select' ).value;

			if ( '' === value ) {
				return;
			}

			if ( 'integer' === type ) {
				input[ name ] = parseInt( value, 10 );
			} else if ( 'number' === type ) {
				input[ name ] = parseFloat( value );
			} else if ( 'boolean' === type ) {
				input[ name ] = 'true' === value;
			} else {
				input[ name ] = value;
			}
		} );

		return input;
	};

	document.querySelectorAll( '[data-try]' ).forEach( button => {
		button.addEventListener( 'click', () => {
			const tool       = data.tools[ button.dataset.try ];
			const schema     = tool.schema || {};
			const properties = schema.properties || {};
			const required   = schema.required || [];
			const kind       = $( '#ddfw-mcp-try-kind' );

			current = button.dataset.try;

			kind.className   = `ddfw-mcp-chip ddfw-mcp-chip-${ tool.kind }`;
			kind.textContent = i18n.kinds[ tool.kind ];
			$( '#ddfw-mcp-try-title' ).textContent       = tool.label;
			$( '#ddfw-mcp-try-description' ).textContent = tool.description;
			inputsBox.innerHTML                         = '';
			output.parentElement.parentElement.hidden    = true;

			if ( ! Object.keys( properties ).length ) {
				const note = document.createElement( 'p' );

				note.className   = 'ddfw-mcp-muted';
				note.textContent = i18n.noInputs;
				inputsBox.appendChild( note );
			}

			Object.keys( properties ).forEach( name => inputsBox.appendChild( buildField( name, properties[ name ], required.includes( name ) ) ) );
			tryDialog.showModal();
		} );
	} );

	const runForm = $( '#ddfw-mcp-run' );

	if ( runForm ) {
		runForm.addEventListener( 'submit', event => {
			event.preventDefault();

			const tool = data.tools[ current ];

			if ( 'write' === tool.kind && ! window.confirm( i18n.writeAsk ) ) {
				return;
			}

			runButton.disabled    = true;
			runButton.textContent = i18n.running;

			post( 'run', { ability: current, input: JSON.stringify( collectInput() ) } )
				.then( response => {
					status.className   = response.ok ? 'is-success' : 'is-error';
					status.textContent = `${ response.ok ? i18n.success : i18n.failed } · ${ response.ms } ms`;
					output.textContent = 'string' === typeof response.result ? response.result : JSON.stringify( response.result, null, 2 );
				} )
				.catch( error => {
					status.className   = 'is-error';
					status.textContent = i18n.failed;
					output.textContent = error.message;
				} )
				.finally( () => {
					output.parentElement.parentElement.hidden = false;
					runButton.disabled    = false;
					runButton.textContent = i18n.run;
					output.parentElement.parentElement.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
				} );
		} );
	}

	/*
	 * Activity log.
	 */
	const clear = $( '#ddfw-mcp-clear' );

	if ( clear ) {
		clear.addEventListener( 'click', () => {
			if ( window.confirm( i18n.clearAsk ) ) {
				post( 'clear_log' )
					.then( () => {
						window.location.href = `${ window.location.pathname }?page=${ new URLSearchParams( window.location.search ).get( 'page' ) }`;
					} )
					.catch( error => toast( error.message, true ) );
			}
		} );
	}

	// Filters apply as soon as one changes ( plain selects: the framework leaves .ddfw-dash-filter-select alone ).
	document.querySelectorAll( '.ddfw-mcp-filters select' ).forEach( select => {
		select.addEventListener( 'change', () => {
			// Empty filters stay out of the URL.
			select.form.querySelectorAll( 'select' ).forEach( other => {
				other.disabled = '' === other.value;
			} );
			select.form.submit();
		} );
	} );
} )();
