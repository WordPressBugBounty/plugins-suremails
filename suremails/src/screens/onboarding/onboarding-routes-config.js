const ONBOARDING_ROUTES_CONFIG = [
	{
		url: '/onboarding/welcome',
		index: true,
	},
	{
		url: '/onboarding/connection',
	},
	{
		url: '/onboarding/reputation-shield',
	},
	{
		url: '/onboarding/done',
	},
];

export const getVisibleOnboardingRoutes = ( onboardingState = {} ) => {
	void onboardingState;
	return ONBOARDING_ROUTES_CONFIG;
};

export default ONBOARDING_ROUTES_CONFIG;
