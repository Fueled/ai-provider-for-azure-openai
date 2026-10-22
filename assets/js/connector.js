/**
 * The Azure OpenAI card on Settings > Connectors.
 *
 * Core's card for an AI provider only collects an API key; Azure also needs the resource
 * endpoint and the deployments. Registering a `render` for the provider's ID before core
 * registers its defaults makes core keep this card and fill in name, logo and connection state.
 *
 * A plain ES module against the screen's `wp.*` globals, so it needs no build step.
 *
 * @since 1.1.0
 */
/* eslint-disable @wordpress/no-unsafe-wp-apis, import/no-unresolved -- A script module WordPress serves on the Connectors screen, not an npm package; these exports are its only card API. */
import {
	__experimentalRegisterConnector as registerConnector,
	__experimentalConnectorItem as ConnectorItem,
} from '@wordpress/connectors';
/* eslint-enable @wordpress/no-unsafe-wp-apis, import/no-unresolved */

const { createElement: el, useState } = window.wp.element;
const {
	Button,
	Notice,
	SelectControl,
	TextControl,
	__experimentalVStack: VStack,
	__experimentalHStack: HStack,
} = window.wp.components;
const apiFetch = window.wp.apiFetch;
const { __ } = window.wp.i18n;

const PROVIDER_ID = 'azure_openai';

/**
 * Reads the saved settings the server prints for this module.
 *
 * @since 1.1.0
 *
 * @return {Object} Endpoint, deployments, deployment types and the option name.
 */
function getData() {
	try {
		return JSON.parse(
			document.getElementById(
				'wp-script-module-data-ai-provider-for-azure-openai/connector'
			)?.textContent ?? '{}'
		);
	} catch {
		return {};
	}
}

/**
 * The green "Connected" label core's own cards show.
 *
 * @since 1.1.0
 */
function ConnectedBadge() {
	return el(
		'span',
		{
			style: {
				color: '#345b37',
				backgroundColor: '#eff8f0',
				padding: '4px 12px',
				borderRadius: '2px',
				fontSize: '13px',
				fontWeight: 'var(--wpds-typography-font-weight-emphasis)',
				whiteSpace: 'nowrap',
			},
		},
		__( 'Connected', 'ai-provider-for-azure-openai' )
	);
}

/**
 * Edits the list of deployments: a name and a model type per row.
 *
 * @since 1.1.0
 *
 * @param {Object}   props             Component props.
 * @param {Array}    props.deployments Rows of `{ name, type }`.
 * @param {Object}   props.modelTypes  Map of type key to label.
 * @param {Function} props.onChange    Receives the new rows.
 */
function Deployments( { deployments, modelTypes, onChange } ) {
	const typeOptions = Object.entries( modelTypes ).map(
		( [ value, label ] ) => ( { value, label } )
	);
	const update = ( index, changes ) =>
		onChange(
			deployments.map( ( row, i ) =>
				i === index ? { ...row, ...changes } : row
			)
		);

	return el(
		VStack,
		{ spacing: 2 },
		el(
			'span',
			{ className: 'components-base-control__label' },
			__( 'Deployments', 'ai-provider-for-azure-openai' )
		),
		...deployments.map( ( row, index ) =>
			el(
				HStack,
				{ key: index, alignment: 'bottom', spacing: 2 },
				el( TextControl, {
					__next40pxDefaultSize: true,
					label: __(
						'Deployment name',
						'ai-provider-for-azure-openai'
					),
					hideLabelFromVision: true,
					placeholder: __(
						'e.g. my-gpt4o',
						'ai-provider-for-azure-openai'
					),
					value: row.name,
					onChange: ( name ) => update( index, { name } ),
				} ),
				el( SelectControl, {
					__next40pxDefaultSize: true,
					label: __( 'Model type', 'ai-provider-for-azure-openai' ),
					hideLabelFromVision: true,
					options: typeOptions,
					value: row.type,
					onChange: ( type ) => update( index, { type } ),
				} ),
				el(
					Button,
					{
						__next40pxDefaultSize: true,
						variant: 'tertiary',
						isDestructive: true,
						onClick: () =>
							onChange(
								deployments.filter( ( _, i ) => i !== index )
							),
					},
					__( 'Remove', 'ai-provider-for-azure-openai' )
				)
			)
		),
		el(
			HStack,
			{ justify: 'flex-start' },
			el(
				Button,
				{
					variant: 'secondary',
					size: 'compact',
					onClick: () =>
						onChange( [
							...deployments,
							{ name: '', type: 'chat' },
						] ),
				},
				__( 'Add deployment', 'ai-provider-for-azure-openai' )
			)
		),
		el(
			'p',
			{
				className: 'components-base-control__help',
				style: { margin: 0 },
			},
			__(
				'The name you chose for each deployment when you deployed a model, and what that model does.',
				'ai-provider-for-azure-openai'
			)
		)
	);
}

/**
 * The Azure OpenAI connector card.
 *
 * @since 1.1.0
 *
 * @param {Object} props                Props core passes to a connector's render.
 * @param {string} props.name           Connector name.
 * @param {string} props.description    Connector description.
 * @param {Object} props.logo           Connector logo.
 * @param {Object} props.authentication Key setting name, key source and connection state.
 */
function AzureOpenAIConnector( { name, description, logo, authentication } ) {
	const initial = getData();
	const keySetting =
		authentication?.settingName ?? 'connectors_ai_azure_openai_api_key';
	const keyLocked = [ 'env', 'constant' ].includes(
		authentication?.keySource
	);

	const [ isConnected, setIsConnected ] = useState(
		!! authentication?.isConnected
	);
	const [ isExpanded, setIsExpanded ] = useState( false );
	const [ endpoint, setEndpoint ] = useState( initial.endpoint ?? '' );
	const [ deployments, setDeployments ] = useState(
		initial.deployments?.length
			? initial.deployments
			: [ { name: '', type: 'chat' } ]
	);
	const [ apiKey, setApiKey ] = useState( '' );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	const named = deployments.filter( ( row ) => row.name.trim() );
	const canSave =
		( initial.endpointLocked || !! endpoint.trim() ) &&
		named.length > 0 &&
		( keyLocked || isConnected || !! apiKey.trim() );

	/**
	 * Saves the settings and key, then asks the server whether Azure accepts them.
	 */
	const save = async () => {
		setError( null );
		setIsBusy( true );
		try {
			const data = {
				[ initial.optionName ]: {
					// The environment variable wins, so its value is not copied into the setting.
					endpoint: initial.endpointLocked ? '' : endpoint.trim(),
					deployments: named.map( ( row ) => ( {
						name: row.name.trim(),
						type: row.type,
					} ) ),
				},
			};
			if ( ! keyLocked && apiKey.trim() ) {
				data[ keySetting ] = apiKey.trim();
			}
			await apiFetch( { path: '/wp/v2/settings', method: 'POST', data } );

			const status = await apiFetch( {
				path: '/ai-provider-for-azure-openai/v1/status',
			} );
			if ( ! status.connected ) {
				setIsConnected( false );
				setError(
					__(
						'Azure did not accept this endpoint, key and deployment. Check them in the Azure portal: Keys and Endpoint for the first two, Deployments for the names.',
						'ai-provider-for-azure-openai'
					)
				);
				return;
			}
			setIsConnected( true );
			setIsExpanded( false );
			setApiKey( '' );
		} catch ( e ) {
			setError(
				e?.message ??
					__( 'Saving failed.', 'ai-provider-for-azure-openai' )
			);
		} finally {
			setIsBusy( false );
		}
	};

	/**
	 * Removes the saved key, which disconnects the provider.
	 */
	const disconnect = async () => {
		setIsBusy( true );
		try {
			await apiFetch( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: { [ keySetting ]: '' },
			} );
			setIsConnected( false );
		} finally {
			setIsBusy( false );
		}
	};

	let toggleLabel = __( 'Set up', 'ai-provider-for-azure-openai' );
	if ( isExpanded ) {
		toggleLabel = __( 'Cancel', 'ai-provider-for-azure-openai' );
	} else if ( isConnected ) {
		toggleLabel = __( 'Edit', 'ai-provider-for-azure-openai' );
	}

	const actionArea = el(
		HStack,
		{ spacing: 3, expanded: false },
		isConnected && el( ConnectedBadge ),
		el(
			Button,
			{
				variant: isExpanded || isConnected ? 'tertiary' : 'secondary',
				size: 'compact',
				onClick: () => setIsExpanded( ! isExpanded ),
				'aria-expanded': isExpanded,
			},
			toggleLabel
		)
	);

	const form = el(
		VStack,
		{ spacing: 4, className: 'connector-settings' },
		el( TextControl, {
			__next40pxDefaultSize: true,
			label: __( 'Endpoint URL', 'ai-provider-for-azure-openai' ),
			placeholder: 'https://myresource.openai.azure.com',
			help: initial.endpointLocked
				? __(
						'Set by the AZURE_OPENAI_ENDPOINT environment variable.',
						'ai-provider-for-azure-openai'
				  )
				: __(
						'The base URL of your Azure OpenAI resource, from Keys and Endpoint in the Azure portal.',
						'ai-provider-for-azure-openai'
				  ),
			value: endpoint,
			onChange: setEndpoint,
			disabled: initial.endpointLocked,
		} ),
		el( TextControl, {
			__next40pxDefaultSize: true,
			type: 'password',
			autoComplete: 'off',
			label: __( 'API key', 'ai-provider-for-azure-openai' ),
			help: keyLocked
				? __(
						'Set outside this screen.',
						'ai-provider-for-azure-openai'
				  )
				: isConnected &&
				  __(
						'Leave empty to keep the saved key.',
						'ai-provider-for-azure-openai'
				  ),
			placeholder: keyLocked || isConnected ? '••••••••••••••••' : '',
			value: apiKey,
			onChange: setApiKey,
			disabled: keyLocked,
		} ),
		el( Deployments, {
			deployments,
			modelTypes: initial.modelTypes ?? { chat: 'Chat' },
			onChange: setDeployments,
		} ),
		error && el( Notice, { status: 'error', isDismissible: false }, error ),
		el(
			HStack,
			{ justify: 'flex-start' },
			el(
				Button,
				{
					__next40pxDefaultSize: true,
					variant: 'primary',
					disabled: ! canSave || isBusy,
					accessibleWhenDisabled: true,
					isBusy,
					onClick: save,
				},
				__( 'Save', 'ai-provider-for-azure-openai' )
			),
			isConnected &&
				! keyLocked &&
				el(
					Button,
					{
						variant: 'link',
						isDestructive: true,
						onClick: disconnect,
						disabled: isBusy,
					},
					__( 'Disconnect', 'ai-provider-for-azure-openai' )
				)
		)
	);

	return el(
		ConnectorItem,
		{ logo, name, description, actionArea },
		isExpanded ? form : null
	);
}

registerConnector( PROVIDER_ID, { render: AzureOpenAIConnector } );
