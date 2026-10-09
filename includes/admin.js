( function () {
	const manager = document.querySelector( '.versedb-block-manager' );
	if ( ! manager ) {
		return;
	}
	manager.querySelector( '.versedb-block-tools' ).hidden = false;
	manager.querySelectorAll( '[data-versedb-enable]' ).forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			manager
				.querySelectorAll( 'input[role="switch"]' )
				.forEach( ( input ) => {
					input.checked = button.dataset.versedbEnable === 'true';
				} );
		} );
	} );
	manager
		.querySelector( '#versedb-block-search' )
		.addEventListener( 'input', ( event ) => {
			const query = event.target.value.toLocaleLowerCase().trim();
			let visible = 0;
			manager
				.querySelectorAll( '.versedb-block-card' )
				.forEach( ( card ) => {
					card.hidden = ! card.textContent
						.toLocaleLowerCase()
						.includes( query );
					if ( ! card.hidden ) {
						visible++;
					}
				} );
			manager.querySelector( '.versedb-block-empty' ).hidden =
				visible > 0;
		} );
} )();
