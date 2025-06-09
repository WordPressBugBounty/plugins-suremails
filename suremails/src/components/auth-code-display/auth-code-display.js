import { useLayoutEffect } from '@wordpress/element';
import { useNavigate } from 'react-router-dom';
import { toast } from '@bsf/force-ui';
import { __ } from '@wordpress/i18n';
import { ONBOARDING_SESSION_STORAGE_KEY } from '@screens/onboarding/onboarding-state';

/* global sessionStorage */

const AuthCodeDisplay = () => {
	const navigate = useNavigate();
	const storedConnectionState = localStorage.getItem( 'formStateValues' );
	const onboardingSavedState = sessionStorage.getItem(
		ONBOARDING_SESSION_STORAGE_KEY
	);

	const redirectToGmailConnectionDrawer = () => {
		setTimeout( () => {
			navigate( '/connections', {
				state: {
					openDrawer: true,
					selectedProvider: 'GMAIL',
				},
			} );
		}, 300 );
	};

	const cleanUrlToDashboard = () => {
		window.history.replaceState(
			{},
			'',
			suremails.adminURL + '#/dashboard'
		);
	};

	useLayoutEffect( () => {
		if ( onboardingSavedState ) {
			return;
		}

		if ( ! storedConnectionState ) {
			return;
		}
		const storedFormStateTimeStamp = parseInt( storedConnectionState, 10 );

		const currentTime = Date.now();
		if ( currentTime > storedFormStateTimeStamp ) {
			localStorage.removeItem( 'formStateValues' );
			localStorage.removeItem( 'formStateValuesTimestamp' );
			return;
		}

		const urlParams = new URLSearchParams( window.location.search );
		const state = urlParams.get( 'state' );

		if ( ! state || state !== 'gmail' ) {
			cleanUrlToDashboard();

			toast.error( __( 'Authorization Failed', 'suremails' ), {
				description: __(
					'Invalid state parameter. Please try again.',
					'suremails'
				),
				autoDismiss: false,
			} );

			redirectToGmailConnectionDrawer();
			return;
		}

		const code = urlParams.get( 'code' );

		if ( code ) {
			const storedFormState =
				JSON.parse( localStorage.getItem( 'formStateValues' ) ) || {};

			cleanUrlToDashboard();

			const updatedFormState = {
				...storedFormState,
				auth_code: code,
				type: 'GMAIL',
				refresh_token: '',
				force_save: true,
			};

			localStorage.setItem(
				'formStateValues',
				JSON.stringify( updatedFormState )
			);

			redirectToGmailConnectionDrawer();
			return;
		}

		toast.error( __( 'Authorization Failed', 'suremails' ), {
			description: __(
				'We could not receive the auth code. Please try again.',
				'suremails'
			),
			autoDismiss: false,
		} );

		const storedFormState =
			JSON.parse( localStorage.getItem( 'formStateValues' ) ) || {};
		const updatedFormState = {
			...storedFormState,
			type: 'GMAIL',
			refresh_token: '',
			force_save: true,
		};

		localStorage.setItem(
			'formStateValues',
			JSON.stringify( updatedFormState )
		);

		cleanUrlToDashboard();
		redirectToGmailConnectionDrawer();
	}, [ navigate ] );

	// For onboarding flow
	useLayoutEffect( () => {
		if ( ! onboardingSavedState || storedConnectionState ) {
			return;
		}

		// Get the code and state from the URL
		const urlParams = new URLSearchParams( window.location.search );
		const code = urlParams.get( 'code' );
		const state = urlParams.get( 'state' );

		if ( ! code || state !== 'gmail' ) {
			return;
		}

		cleanUrlToDashboard();

		navigate( '/onboarding/connection', {
			state: {
				connection: 'GMAIL',
				auth_code: code,
			},
		} );
	}, [ navigate ] );

	return null;
};

export default AuthCodeDisplay;
