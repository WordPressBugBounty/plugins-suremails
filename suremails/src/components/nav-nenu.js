// src/components/NavMenu.js
import { useState, useEffect, renderToString } from '@wordpress/element';
import { useLocation, Link, useNavigate } from 'react-router-dom';
import { Topbar, HamburgerMenu, Badge, Button } from '@bsf/force-ui';
import { CircleHelp, Megaphone } from 'lucide-react';
import { SureMailIcon } from 'assets/icons';
import { cn } from '@utils/utils';
import useWhatsNewRSS from '../../lib/useWhatsNewRSS';
import { __ } from '@wordpress/i18n';
import { useQuery } from '@tanstack/react-query';
import { fetchSettings } from '@api/settings';
import SureContactUpgradeModal from '@components/surecontact-upgrade-modal';

const NavMenu = () => {
	const location = useLocation();
	const navigate = useNavigate();
	const version = window.suremails?.version || '1.0.0';

	// Fetch settings data via react-query
	const { data: settingsData } = useQuery( {
		queryKey: [ 'settings' ],
		queryFn: fetchSettings,
		select: ( response ) => response.data,
		refetchOnWindowFocus: false,
	} );

	/**
	 * Check if email simulation is active or not based on the settings data.
	 * If email_simulation is set to 'yes' then email simulation is active.
	 *
	 * @constant {boolean} emailSimulation - Email simulation status. True if active, false otherwise. Default is false.
	 * @type {boolean}
	 */
	const emailSimulation = settingsData?.email_simulation === 'yes';

	const sureContactConnection = settingsData?.connections
		? Object.values( settingsData.connections ).find(
				( connection ) => connection?.type === 'SURECONTACT'
		  )
		: undefined;

	const [ isPlansOpen, setIsPlansOpen ] = useState( false );
	// Set when the setup drawer has just provisioned a connection that the
	// cached settings don't include yet.
	const [ plansConnectionId, setPlansConnectionId ] = useState( null );

	useEffect( () => {
		const openPlans = ( event ) => {
			setPlansConnectionId( event?.detail?.connectionId ?? null );
			setIsPlansOpen( true );
		};
		window.addEventListener( 'suremails:open-plans', openPlans );
		return () =>
			window.removeEventListener( 'suremails:open-plans', openPlans );
	}, [] );

	// Add-ons: connected sites see the SureContact SMTP plans, others get the
	// existing SureContact setup drawer on Connections.
	const handleAddonsClick = () => {
		if ( sureContactConnection?.id ) {
			setPlansConnectionId( null );
			setIsPlansOpen( true );
			return;
		}
		navigate( '/connections', {
			state: {
				openDrawer: true,
				selectedProvider: 'SURECONTACT',
				from: 'addons',
			},
		} );
	};

	const addonsLabel = __( 'Add-ons', 'suremails' );
	const newBadge = (
		<Badge
			label={ __( 'New', 'suremails' ) }
			size="xxs"
			type="pill"
			variant="blue"
			disableHover
		/>
	);

	// Define navigation items
	const navItems = [
		{ name: __( 'Dashboard', 'suremails' ), path: '/dashboard' },
		{ name: __( 'Settings', 'suremails' ), path: '/settings' },
		{ name: __( 'Connections', 'suremails' ), path: '/connections' },
		{ name: __( 'Email Logs', 'suremails' ), path: '/logs' },
		{ name: __( 'Notifications', 'suremails' ), path: '/notifications' },
	];

	// Get the current active path
	const [ activePath, setActivePath ] = useState( location.pathname );

	// Update activePath when the location changes
	useEffect( () => {
		setActivePath( location.pathname );
	}, [ location.pathname ] );

	useEffect( () => {
		const updateWordPressMenuState = () => {
			// Find the SureMails main menu item
			const sureMailsMainMenu = document.querySelector(
				'#adminmenu li.toplevel_page_' +
					( window.suremails?.adminURL
						?.split( 'page=' )[ 1 ]
						?.split( '#' )[ 0 ] || 'suremail' )
			);

			if ( ! sureMailsMainMenu ) {
				return;
			}

			const wpSubmenuItems =
				sureMailsMainMenu.querySelectorAll( '.wp-submenu a' );

			// Remove all current active states from SureMails submenu items
			wpSubmenuItems.forEach( ( item ) => {
				item.classList.remove( 'current' );
				item.parentElement.classList.remove( 'current' );
			} );

			// Find and activate the corresponding WordPress menu item
			const currentPath = location.pathname;
			const currentHash = location.hash || '#' + currentPath;

			let matchingItem = null;

			matchingItem = Array.from( wpSubmenuItems ).find( ( item ) => {
				const href = item.getAttribute( 'href' );
				return href && href.includes( currentHash );
			} );

			// Apply active state to matching item
			if ( matchingItem ) {
				matchingItem.classList.add( 'current' );
				matchingItem.parentElement.classList.add( 'current' );
			}
		};

		// Update with delay to ensure DOM and React state are ready
		const timeout = setTimeout( updateWordPressMenuState, 200 );

		return () => clearTimeout( timeout );
	}, [ location.pathname ] );

	const handleIconClick = () => {
		navigate( '/dashboard' );
	};

	useWhatsNewRSS( {
		uniqueKey: 'suremails',
		rssFeedURL: 'https://suremails.com/whats-new/feed/',
		selector: '#suremails_whats_new',
		flyout: {
			title: __( "What's New?", 'suremails' ),
			closeBtnLabel: __( 'Close', 'suremails' ),
			className: 'suremails-whats-new-flyout',
		},
		triggerButton: {
			label: __( "What's New", 'suremails' ),
			icon: renderToString(
				<Megaphone
					className="size-4 m-1 text-icon-primary"
					strokeWidth={ 1.5 }
				/>
			),
		},
	} );

	return (
		<>
			<Topbar className="relative shadow-sm bg-background-primary h-16 z-[1] p-0 gap-0">
				{ /* Left Section: Logo */ }
				<Topbar.Left className="p-5 gap-5">
					<HamburgerMenu className="lg:hidden">
						<HamburgerMenu.Toggle className="size-6" />
						<HamburgerMenu.Options>
							{ navItems.map( ( option ) => (
								<HamburgerMenu.Option
									key={ option.name }
									tag={ Link }
									to={ option.path }
									active={ activePath.trim() === option.path }
								>
									{ option.name }
								</HamburgerMenu.Option>
							) ) }
							<HamburgerMenu.Option
								tag="button"
								type="button"
								onClick={ handleAddonsClick }
								className="bg-transparent border-0 cursor-pointer text-left"
							>
								{ addonsLabel }
								{ newBadge }
							</HamburgerMenu.Option>
						</HamburgerMenu.Options>
					</HamburgerMenu>
					<Topbar.Item>
						<div
							onClick={ handleIconClick }
							className="flex items-center justify-center cursor-pointer"
						>
							<SureMailIcon className="h-6 w-6" />
						</div>
					</Topbar.Item>
				</Topbar.Left>

				{ /* Middle Section: Navigation */ }
				<Topbar.Middle
					className="h-full lg:flex hidden"
					align="left"
					gap="xs"
				>
					<Topbar.Item className="h-full">
						<nav className="h-full space-x-4">
							{ navItems.map( ( item ) => (
								<Link
									key={ item.name }
									to={ item.path }
									className={ cn(
										'inline-block relative h-full content-center px-1 text-sm text-text-secondary font-medium no-underline bg-transparent focus:outline-none shadow-none border-1 hover:text-text-primary transition-colors duration-300',
										activePath.trim() === item.path
											? 'text-text-primary border-none after:content-[""] after:absolute after:bottom-0 after:inset-x-0 after:h-px after:bg-border-interactive after:transition-all after:duration-300'
											: ''
									) }
								>
									{ item.name }
								</Link>
							) ) }
							<button
								type="button"
								onClick={ handleAddonsClick }
								className="inline-flex items-center gap-1.5 relative h-full px-1 text-sm text-text-secondary font-medium bg-transparent border-0 cursor-pointer shadow-none hover:text-text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-focus transition-colors duration-300"
							>
								{ addonsLabel }
								{ newBadge }
							</button>
						</nav>
					</Topbar.Item>
				</Topbar.Middle>

				{ /* Right Section: Version Badge and Icons */ }
				<Topbar.Right className="p-5">
					{ emailSimulation && (
						<Topbar.Item className="gap-2">
							<Badge
								label={ __(
									'Email Simulation Active',
									'suremails'
								) }
								size="sm"
								type="pill"
								variant="yellow"
								disableHover={ true }
							/>
						</Topbar.Item>
					) }
					<Topbar.Item>
						<Badge
							label={ `V ${ version }` }
							size="xs"
							variant="neutral"
						/>
					</Topbar.Item>
					<Topbar.Item className="gap-2">
						<Button
							variant="ghost"
							size="xs"
							icon={ <CircleHelp /> }
							aria-label={ __( 'Documentation', 'suremails' ) }
							onClick={ () =>
								window.open( suremails?.docsURL, '_blank' )
							}
							href=""
						/>
						{ /** What's New Integration */ }
						<div
							id="suremails_whats_new"
							className="[&>a]:p-0.5 [&>a]:pl-0"
						></div>
					</Topbar.Item>
				</Topbar.Right>
			</Topbar>
			<SureContactUpgradeModal
				open={ isPlansOpen }
				setOpen={ setIsPlansOpen }
				connectionId={ plansConnectionId || sureContactConnection?.id }
			/>
		</>
	);
};

export default NavMenu;
