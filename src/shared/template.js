import { __ } from '@wordpress/i18n';

export const fieldLabels = {
	cover: __( 'Cover', 'versedb' ),
	name: __( 'Full item name', 'versedb' ),
	series_name: __( 'Series name and year', 'versedb' ),
	issue_number: __( 'Issue number', 'versedb' ),
	issue_title: __( 'Issue title', 'versedb' ),
	publisher: __( 'Publisher', 'versedb' ),
	date: __( 'Release date', 'versedb' ),
	percent: __( 'Reading percentage', 'versedb' ),
	progress: __( 'Progress bar', 'versedb' ),
	avatar: __( 'Avatar', 'versedb' ),
	username: __( 'Username', 'versedb' ),
	bio: __( 'Biography', 'versedb' ),
	banner: __( 'Banner', 'versedb' ),
	'profile-stats': __( 'Profile statistics', 'versedb' ),
	'goal-summary': __( 'Reading goal summary', 'versedb' ),
	read_count: __( 'Comics read', 'versedb' ),
	active_days: __( 'Active days', 'versedb' ),
	current_streak: __( 'Current streak', 'versedb' ),
	longest_streak: __( 'Longest streak', 'versedb' ),
	this_week: __( 'This week', 'versedb' ),
	this_month: __( 'This month', 'versedb' ),
	year: __( 'Year', 'versedb' ),
	calendar: __( 'Reading calendar', 'versedb' ),
};

export function fieldsForType( type ) {
	if ( type === 'profile' ) {
		return [ 'avatar', 'username', 'bio', 'banner', 'profile-stats' ];
	}
	if ( type === 'reading-goal' ) {
		return [ 'goal-summary', 'progress' ];
	}
	if ( type === 'reading-stats' ) {
		return [
			'read_count',
			'active_days',
			'current_streak',
			'longest_streak',
			'this_week',
			'this_month',
		];
	}
	if ( type === 'reading-calendar' ) {
		return [ 'year', 'calendar' ];
	}
	return [
		'cover',
		'name',
		...( type === 'lists'
			? []
			: [
					'series_name',
					'issue_number',
					'issue_title',
					'publisher',
					'date',
				] ),
		...( type === 'currently-reading' ? [ 'percent', 'progress' ] : [] ),
	];
}

export function defaultTemplate( type, attributes ) {
	const field = ( name, extra = {} ) => [
		'versedb/field',
		{ field: name, metadata: { name: fieldLabels[ name ] }, ...extra },
	];
	if ( type === 'profile' ) {
		return [
			...( attributes.showBanner ? [ field( 'banner' ) ] : [] ),
			[
				'core/group',
				{
					layout: {
						type: 'flex',
						flexWrap: 'wrap',
						verticalAlignment: 'top',
					},
				},
				[
					...( attributes.showAvatar
						? [
								field( 'avatar', {
									imageWidth: attributes.avatarSize,
									imageHeight: attributes.avatarSize,
									style: {
										border: {
											radius:
												attributes.avatarShape ===
												'square'
													? `${ attributes.avatarRadius }px`
													: '50%',
										},
									},
								} ),
							]
						: [] ),
					[
						'core/group',
						{ layout: { type: 'default' } },
						[
							field( 'username', {
								style: { typography: { fontWeight: '700' } },
							} ),
							...( attributes.showBio ? [ field( 'bio' ) ] : [] ),
							...( attributes.showStats
								? [ field( 'profile-stats' ) ]
								: [] ),
						],
					],
				],
			],
		];
	}
	if ( type === 'reading-goal' ) {
		return [
			field( 'goal-summary' ),
			...( attributes.showProgress ? [ field( 'progress' ) ] : [] ),
		];
	}
	if ( type === 'reading-stats' ) {
		const toggles = {
			read_count: 'showReadCount',
			active_days: 'showActiveDays',
			current_streak: 'showCurrentStreak',
			longest_streak: 'showLongestStreak',
			this_week: 'showThisWeek',
			this_month: 'showThisMonth',
		};
		return [
			[
				'core/group',
				{ layout: { type: 'grid', columnCount: attributes.columns } },
				fieldsForType( type )
					.filter( ( name ) => attributes[ toggles[ name ] ] )
					.map( ( name ) => field( name ) ),
			],
		];
	}
	if ( type === 'reading-calendar' ) {
		return [
			...( attributes.showYear ? [ field( 'year' ) ] : [] ),
			field( 'calendar' ),
		];
	}
	const details = [
		field( 'name' ),
		...( attributes.showPublisher ? [ field( 'publisher' ) ] : [] ),
		...( attributes.showDates ? [ field( 'date' ) ] : [] ),
		...( type === 'currently-reading'
			? [
					field( 'percent' ),
					...( attributes.showProgress
						? [ field( 'progress' ) ]
						: [] ),
				]
			: [] ),
	];
	if ( attributes.layout === 'list' ) {
		return [
			[
				'core/group',
				{
					layout: {
						type: 'flex',
						flexWrap: 'nowrap',
						verticalAlignment: 'top',
					},
				},
				[
					...( attributes.showCovers
						? [
								field( 'cover', {
									imageWidth: attributes.coverSize,
								} ),
							]
						: [] ),
					[ 'core/group', { layout: { type: 'default' } }, details ],
				],
			],
		];
	}
	return [
		...( attributes.showCovers ? [ field( 'cover' ) ] : [] ),
		...details,
	];
}
