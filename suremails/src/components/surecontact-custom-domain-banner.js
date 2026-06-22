import { useState, useMemo } from '@wordpress/element';
import { Button, Text } from '@bsf/force-ui';
import { __ } from '@wordpress/i18n';
import { useQuery } from '@tanstack/react-query';
import { ArrowRight, X } from 'lucide-react';
import { SureContactIcon } from '@assets/icons';
import { fetchSettings } from '@api/connections';
import {
	dismissSureContactCustomDomainPromo,
	getSureContactSendingDomains,
} from '@api/surecontact';

const SURECONTACT_KEY = 'SURECONTACT';

// The domain part of an email, lower-cased for comparison.
const emailDomain = ( email ) =>
	String( email || '' )
		.split( '@' )[ 1 ]
		?.toLowerCase() ?? '';

/**
 * Nudge that surfaces the paid-only "Add Sender" flow — sending from a custom
 * verified domain — which is otherwise buried inside the connections drawer.
 *
 * Visibility rules:
 *   - A paid SureContact connection exists, AND
 *   - none of the SureContact senders is already on a verified custom sending
 *     domain (matched against the account's verified domains). Once the user
 *     sends from a custom domain — even after deleting the original default
 *     sender — the nudge hides.
 *   - Dismissible for 15 days; the seed flag comes from the server and the timer
 *     resets via the disable-surecontact-custom-domain-promo endpoint (mirrors
 *     the SMTP launch promo pattern).
 *
 * @param {Object}   props             Component props.
 * @param {Function} props.onAddSender Opens the connections drawer in the
 *                                     SureContact "Add Sender" flow.
 */
const SureContactCustomDomainBanner = ( { onAddSender } ) => {
	const [ dismissed, setDismissed ] = useState(
		Boolean( window?.suremails?.surecontactCustomDomainPromoDismissed )
	);

	const { data: settings, isFetched: settingsFetched } = useQuery( {
		queryKey: [ 'settings' ],
		queryFn: fetchSettings,
		select: ( data ) => data?.data || {},
		refetchOnMount: false,
		refetchOnWindowFocus: false,
	} );

	const surecontactConnections = useMemo(
		() =>
			settings?.connections
				? Object.values( settings.connections ).filter(
						( c ) => c?.type === SURECONTACT_KEY
				  )
				: [],
		[ settings?.connections ]
	);

	const hasPaidSureContact = surecontactConnections.some( ( c ) =>
		Boolean( c?.is_paid )
	);

	// Any SureContact row works — they share one api_key, and the server
	// resolves the primary connection when listing verified domains.
	const connectionId = surecontactConnections[ 0 ]?.id ?? null;

	// Verified custom sending domains for the account. Only fetched once we know
	// a paid SureContact connection exists, so free users never hit the API.
	const {
		data: domainData,
		isFetched: domainsFetched,
		isError: domainsError,
	} = useQuery( {
		queryKey: [ 'surecontact-sending-domains', connectionId ],
		queryFn: () => getSureContactSendingDomains( connectionId ),
		enabled: hasPaidSureContact && Boolean( connectionId ),
		refetchOnWindowFocus: false,
		staleTime: 0,
	} );

	const verifiedDomains = useMemo(
		() =>
			( domainData?.domains ?? [] )
				.map( ( d ) => d?.domain?.toLowerCase() )
				.filter( Boolean ),
		[ domainData ]
	);

	// True once any sender is already on one of the account's verified custom
	// sending domains — the feature is in use, so stop nudging.
	const usesCustomDomain = useMemo(
		() =>
			surecontactConnections.some( ( c ) =>
				verifiedDomains.includes( emailDomain( c?.from_email ) )
			),
		[ surecontactConnections, verifiedDomains ]
	);

	const handleDismiss = () => {
		setDismissed( true );
		dismissSureContactCustomDomainPromo();
	};

	// Wait for both queries to settle before deciding to avoid a flicker.
	if ( ! settingsFetched ) {
		return null;
	}

	if ( dismissed || ! hasPaidSureContact ) {
		return null;
	}

	// Hold off until the verified-domains check resolves. On error we can't tell
	// whether a custom domain is in use, so stay hidden rather than risk showing
	// the nudge to someone who already set one up.
	if ( ! domainsFetched || domainsError || usesCustomDomain ) {
		return null;
	}

	return (
		<div className="flex items-center justify-between gap-4 flex-wrap p-4 border-0.5 border-solid rounded-xl shadow-sm bg-background-primary border-border-subtle">
			<div className="flex items-center gap-3">
				<SureContactIcon className="size-10 shrink-0" />
				<div className="flex flex-col gap-0.5">
					<Text as="h3" size={ 14 } weight={ 600 } color="primary">
						{ __(
							'Send from your own domain with SureContact',
							'suremails'
						) }
					</Text>
					<Text as="p" size={ 13 } weight={ 400 } color="secondary">
						{ __(
							'Your paid SureContact plan lets you send from a verified custom domain — add another sender in seconds.',
							'suremails'
						) }
					</Text>
				</div>
			</div>
			<div className="flex items-center gap-1 shrink-0">
				<Button
					variant="primary"
					size="sm"
					onClick={ onAddSender }
					icon={ <ArrowRight className="size-3" /> }
					iconPosition="right"
					type="button"
				>
					{ __( 'Add Sender', 'suremails' ) }
				</Button>
				<Button
					className="p-0.5 before:hidden"
					size="sm"
					variant="ghost"
					icon={ <X /> }
					onClick={ handleDismiss }
					aria-label={ __( 'Dismiss', 'suremails' ) }
					type="button"
				/>
			</div>
		</div>
	);
};

export default SureContactCustomDomainBanner;
