/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Internal dependencies
 */
const { visitSettingsPage } = require( '../../utils/helpers' );

test.describe( 'Plugin settings', () => {
	test( 'Can visit the settings page and see error message', async ( {
		admin,
		page,
	} ) => {
		// Visit the settings page.
		await visitSettingsPage( admin );

		// Ensure the page title is correct.
		await expect(
			page.locator( '.wrap h1', { hasText: 'Azure OpenAI Settings' } )
		).toHaveCount( 1 );
	} );
} );
