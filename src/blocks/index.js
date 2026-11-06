/**
 * Editor side of the server-rendered blocks.
 */
import { registerBlockType } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import { useBlockProps } from '@wordpress/block-editor';

const blocks = {
	'ddwcwr/returns-portal': {
		title: __( 'Returns portal', 'devdiggers-returns' ),
		description: __( 'Lets customers find an order and request a return without an account.', 'devdiggers-returns' ),
		icon: 'update',
	},
	'ddwcwr/withdrawal-form': {
		title: __( 'Withdrawal form', 'devdiggers-returns' ),
		description: __( 'The EU "Withdraw from contract here" function with the confirmation step.', 'devdiggers-returns' ),
		icon: 'undo',
	},
};

Object.keys( blocks ).forEach( ( name ) => {
	registerBlockType( name, {
		apiVersion: 3,
		title: blocks[ name ].title,
		description: blocks[ name ].description,
		icon: blocks[ name ].icon,
		category: 'woocommerce',
		supports: { html: false, multiple: false },
		edit: () => createElement( 'div', useBlockProps(), createElement( ServerSideRender, { block: name } ) ),
		save: () => null,
	} );
} );
