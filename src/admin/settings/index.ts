/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

interface ModelTypeMap {
	[ key: string ]: string;
}

interface Config {
	modelTypes: ModelTypeMap;
	nextIndex: number;
}

declare global {
	interface Window {
		wpAiClientAzureOpenAISettings: Config;
	}
}

const OPTION_NAME = 'wp_ai_client_azure_openai_settings';

/**
 * Creates a type <select> element for a new deployment row.
 *
 * @param modelTypes Map of type key to display label.
 * @param index      Row index used for the input name.
 * @since 1.0.0
 */
function createTypeSelect(
	modelTypes: ModelTypeMap,
	index: number
): HTMLSelectElement {
	const select = document.createElement( 'select' );
	select.name = `${ OPTION_NAME }[deployments][${ index }][type]`;

	for ( const [ value, label ] of Object.entries( modelTypes ) ) {
		const option = document.createElement( 'option' );
		option.value = value;
		option.textContent = label;
		select.appendChild( option );
	}

	return select;
}

/**
 * Creates a new empty deployment table row.
 *
 * @param modelTypes Map of type key to display label.
 * @param index      Row index used for input names.
 * @since 1.0.0
 */
function createRow(
	modelTypes: ModelTypeMap,
	index: number
): HTMLTableRowElement {
	const tr = document.createElement( 'tr' );

	// Deployment name cell.
	const nameTd = document.createElement( 'td' );
	const nameInput = document.createElement( 'input' );
	nameInput.type = 'text';
	nameInput.name = `${ OPTION_NAME }[deployments][${ index }][name]`;
	nameInput.className = 'regular-text';
	nameInput.placeholder = __(
		'e.g. my-gpt4o',
		'ai-provider-for-azure-openai'
	);
	nameTd.appendChild( nameInput );
	tr.appendChild( nameTd );

	// Model type cell.
	const typeTd = document.createElement( 'td' );
	typeTd.appendChild( createTypeSelect( modelTypes, index ) );
	tr.appendChild( typeTd );

	// Remove button cell.
	const removeTd = document.createElement( 'td' );
	const removeBtn = document.createElement( 'button' );
	removeBtn.type = 'button';
	removeBtn.className = 'button azure-openai-remove-deployment';
	removeBtn.textContent = __( 'Remove', 'ai-provider-for-azure-openai' );
	removeTd.appendChild( removeBtn );
	tr.appendChild( removeTd );

	return tr;
}

/**
 * Initializes the settings page interactivity.
 *
 * The table and existing rows are rendered by PHP. This function attaches
 * the Add Deployment and Remove button handlers.
 *
 * @since 1.0.0
 */
document.addEventListener( 'DOMContentLoaded', () => {
	const config = window.wpAiClientAzureOpenAISettings;
	if ( ! config ) {
		return;
	}

	const tbody = document.getElementById(
		'azure-openai-deployments-tbody'
	) as HTMLTableSectionElement | null;
	const addBtn = document.getElementById(
		'azure-openai-add-deployment'
	) as HTMLButtonElement | null;

	if ( ! tbody || ! addBtn ) {
		return;
	}

	// Start the index counter after PHP-rendered rows to avoid name conflicts.
	let rowIndex = config.nextIndex;

	const table = tbody.closest( 'table' ) as HTMLElement | null;

	// Event delegation: handle Remove button clicks anywhere in the tbody.
	tbody.addEventListener( 'click', ( event ) => {
		const target = event.target as HTMLElement;
		if ( target.classList.contains( 'azure-openai-remove-deployment' ) ) {
			const row = target.closest( 'tr' );
			if ( row ) {
				row.remove();
				// Hide the table again once the last row is removed.
				if ( table && tbody.rows.length === 0 ) {
					table.style.display = 'none';
				}
			}
		}
	} );

	// Add Deployment button appends a new empty row.
	addBtn.addEventListener( 'click', () => {
		// Reveal the table when adding the first row.
		if ( table && table.style.display === 'none' ) {
			table.style.display = '';
		}
		tbody.appendChild( createRow( config.modelTypes, rowIndex++ ) );
	} );
} );
