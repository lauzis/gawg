(function () {
	var giveawaySelect   = document.getElementById( 'gawg-giveaway-select' );
	var shuffleCountInput = document.getElementById( 'gawg-shuffle-count' );
	var shuffleDelayInput = document.getElementById( 'gawg-shuffle-delay' );
	var shuffleBtn        = document.getElementById( 'gawg-shuffle-btn' );
	var participantsWrap  = document.getElementById( 'gawg-participants-wrap' );
	var participantsList  = document.getElementById( 'gawg-participants-list' );
	var highlightedEl     = document.getElementById( 'gawg-highlighted-participant' );
	var winnerWrap        = document.getElementById( 'gawg-winner-wrap' );
	var winnerDisplay     = document.getElementById( 'gawg-winner-display' );
	var logWrap           = document.getElementById( 'gawg-log-wrap' );
	var logTable          = document.getElementById( 'gawg-log-table' );
	var statusEl          = document.getElementById( 'gawg-status' );

	var participants = [];

	giveawaySelect.addEventListener( 'change', function () {
		var termId = giveawaySelect.value;

		participantsWrap.style.display = 'none';
		winnerWrap.style.display       = 'none';
		logWrap.style.display          = 'none';
		logTable.innerHTML             = '';
		shuffleBtn.disabled            = true;
		participants                   = [];
		participantsList.innerHTML     = '';
		highlightedEl.textContent      = '';
		statusEl.textContent           = '';

		if ( ! termId ) {
			return;
		}

		statusEl.textContent = gawgDrawWinner.i18n.loading;

		var data = new FormData();
		data.append( 'action',  'gawg_load_participants' );
		data.append( 'nonce',   gawgDrawWinner.nonce );
		data.append( 'term_id', termId );

		fetch( gawgDrawWinner.ajaxUrl, { method: 'POST', body: data } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( response ) {
				statusEl.textContent = '';
				if ( ! response.success ) {
					statusEl.textContent = response.data || gawgDrawWinner.i18n.networkError;
					return;
				}
				participants = ( response.data && response.data.participants ) || [];
				renderLog( ( response.data && response.data.logs ) || [] );
				if ( ! participants.length ) {
					statusEl.textContent = gawgDrawWinner.i18n.noParticipants;
					return;
				}
				renderParticipantList();
				participantsWrap.style.display = '';
				shuffleBtn.disabled            = false;
			} )
			.catch( function () {
				statusEl.textContent = gawgDrawWinner.i18n.networkError;
			} );
	} );

	function renderParticipantList() {
		participantsList.innerHTML = '';
		participants.forEach( function ( p ) {
			var li = document.createElement( 'li' );
			li.textContent = p.email;
			li.dataset.id  = p.id;
			li.style.padding = '2px 4px';
			participantsList.appendChild( li );
		} );
	}

	function renderLog( logs ) {
		logTable.innerHTML = '';
		logWrap.style.display = '';

		if ( ! logs.length ) {
			var p = document.createElement( 'p' );
			p.textContent = gawgDrawWinner.i18n.noLogs;
			logTable.appendChild( p );
			return;
		}

		var table = document.createElement( 'table' );
		table.className = 'widefat striped';
		table.style.width = '100%';

		var thead = document.createElement( 'thead' );
		var headRow = document.createElement( 'tr' );
		[ gawgDrawWinner.i18n.logDatetime, gawgDrawWinner.i18n.logParticipant, gawgDrawWinner.i18n.logAction ].forEach( function ( label ) {
			var th = document.createElement( 'th' );
			th.textContent = label;
			headRow.appendChild( th );
		} );
		thead.appendChild( headRow );
		table.appendChild( thead );

		var tbody = document.createElement( 'tbody' );
		logs.forEach( function ( log ) {
			var tr = document.createElement( 'tr' );
			[ log.datetime, log.participant_email, log.action ].forEach( function ( value ) {
				var td = document.createElement( 'td' );
				td.style.fontFamily = 'monospace';
				td.textContent = value || '';
				tr.appendChild( td );
			} );
			tbody.appendChild( tr );
		} );
		table.appendChild( tbody );
		logTable.appendChild( table );
	}

	function highlightParticipant( index ) {
		var items = participantsList.querySelectorAll( 'li' );
		items.forEach( function ( li ) { li.style.background = ''; } );
		if ( items[ index ] ) {
			items[ index ].style.background = '#fffbcc';
			items[ index ].scrollIntoView( { block: 'nearest' } );
		}
		highlightedEl.textContent = participants[ index ] ? participants[ index ].email : '';
	}

	shuffleBtn.addEventListener( 'click', function () {
		var termId = giveawaySelect.value;
		if ( ! termId ) {
			statusEl.textContent = gawgDrawWinner.i18n.selectGiveaway;
			return;
		}
		if ( ! participants.length ) {
			statusEl.textContent = gawgDrawWinner.i18n.noParticipants;
			return;
		}

		var shuffleCount = parseInt( shuffleCountInput.value, 10 ) || 10;
		var shuffleDelay = parseInt( shuffleDelayInput.value, 10 ) || 100;

		shuffleBtn.disabled            = true;
		winnerWrap.style.display       = 'none';
		statusEl.textContent           = gawgDrawWinner.i18n.shuffling;

		var step     = 0;
		var interval = setInterval( function () {
			var idx = Math.floor( Math.random() * participants.length );
			highlightParticipant( idx );
			step++;
			if ( step >= shuffleCount ) {
				clearInterval( interval );
				pickWinner( termId );
			}
		}, shuffleDelay );
	} );

	function pickWinner( termId ) {
		var data = new FormData();
		data.append( 'action',  'gawg_pick_winner' );
		data.append( 'nonce',   gawgDrawWinner.nonce );
		data.append( 'term_id', termId );

		fetch( gawgDrawWinner.ajaxUrl, { method: 'POST', body: data } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( response ) {
				statusEl.textContent = '';
				if ( ! response.success ) {
					statusEl.textContent = response.data || gawgDrawWinner.i18n.networkError;
					shuffleBtn.disabled  = false;
					return;
				}
				var winnerEmail          = response.data.winner_email;
				winnerDisplay.textContent = winnerEmail;
				winnerWrap.style.display  = '';
				highlightedEl.textContent = winnerEmail;
			} )
			.catch( function () {
				statusEl.textContent = gawgDrawWinner.i18n.networkError;
				shuffleBtn.disabled  = false;
			} );
	}
} )();
