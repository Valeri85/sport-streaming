let favoriteGames = JSON.parse(localStorage.getItem('favoriteGames') || '[]');
let favoriteLeagues = JSON.parse(localStorage.getItem('favoriteLeagues') || '[]');
const isViewingFavorites = document.body.dataset.viewingFavorites === 'true';

console.log('Favorites loaded:', {
	games: favoriteGames.length,
	leagues: favoriteLeagues.length,
	total: favoriteGames.length + favoriteLeagues.length,
});

function saveScrollPosition(event) {
	const scrollPos = document.querySelector('.sports-menu').scrollTop;
	localStorage.setItem('menuScrollPosition', scrollPos);
}

function restoreScrollPosition() {
	const scrollPos = localStorage.getItem('menuScrollPosition');
	if (scrollPos) {
		const menu = document.querySelector('.sports-menu');
		if (menu) {
			setTimeout(() => {
				menu.scrollTop = parseInt(scrollPos);
			}, 50);
		}
	}
}

function navigateToFavorites(event) {
	event.preventDefault();
	const totalFavorites = favoriteGames.length + favoriteLeagues.length;
	window.location.href = '/?favorites=' + totalFavorites;
}

function updateFavoritesCount() {
	const totalFavorites = favoriteGames.length + favoriteLeagues.length;
	const countElement = document.getElementById('favoritesCount');

	if (countElement) {
		countElement.textContent = totalFavorites;
		console.log('Count updated to:', totalFavorites);
	}
}

function updateFavoritesURL() {
	if (!isViewingFavorites) return;

	const totalFavorites = favoriteGames.length + favoriteLeagues.length;
	const newURL = '/?favorites=' + totalFavorites;
	window.history.replaceState(null, '', newURL);
	console.log('URL updated to:', newURL);
}

function filterFavoritesView() {
	if (!isViewingFavorites) return;

	const container = document.getElementById('favoritesContainer');
	const templateData = document.getElementById('templateData');

	if (!templateData) return;

	let hasAnyFavorites = false;
	let favoritesHTML = '';
	const allSportsData = {};

	templateData.querySelectorAll('.sport-category').forEach(category => {
		const sportName = category.getAttribute('data-sport');
		const sportIcon = category.querySelector('.sport-title span:first-child')?.textContent || '⚽';

		const competitions = category.querySelectorAll('.competition-group');
		competitions.forEach(compGroup => {
			const leagueId = compGroup.getAttribute('data-league-id');
			const competition = compGroup.getAttribute('data-competition');

			const games = compGroup.querySelectorAll('.game-item');
			games.forEach(game => {
				const gameId = game.getAttribute('data-game-id');
				const gameLeagueId = game.getAttribute('data-league-id');

				if (favoriteGames.includes(gameId) || favoriteLeagues.includes(gameLeagueId)) {
					if (!allSportsData[sportName]) {
						allSportsData[sportName] = {
							icon: sportIcon,
							competitions: {},
						};
					}

					if (!allSportsData[sportName].competitions[competition]) {
						allSportsData[sportName].competitions[competition] = {
							leagueId: leagueId,
							games: [],
						};
					}

					allSportsData[sportName].competitions[competition].games.push(game.outerHTML);
				}
			});
		});
	});

	for (const sportName in allSportsData) {
		const sport = allSportsData[sportName];
		let sportGameCount = 0;
		let competitionsHTML = '';

		for (const compName in sport.competitions) {
			const comp = sport.competitions[compName];
			sportGameCount += comp.games.length;

			const isLeagueFavorited = favoriteLeagues.includes(comp.leagueId);

			competitionsHTML += `
                <div class="competition-group" data-league-id="${comp.leagueId}">
                    <div class="competition-header">
                        <div class="competition-name">
                            <span>📋</span>
                            <span>${compName}</span>
                        </div>
                        <span class="league-favorite ${isLeagueFavorited ? 'favorited' : ''}" data-league-id="${
				comp.leagueId
			}">${isLeagueFavorited ? '★' : '☆'}</span>
                    </div>
                    ${comp.games.join('')}
                </div>
            `;
		}

		if (sportGameCount > 0) {
			hasAnyFavorites = true;

			const sportCountBadgeColor = document.body.dataset.primaryColor || '#FFA500';

			favoritesHTML += `
                <div class="sport-category">
                    <div class="sport-header">
                        <div class="sport-title">
                            <span>${sport.icon}</span>
                            <span>${sportName}</span>
                            <span class="sport-count-badge" style="background-color: ${sportCountBadgeColor};">${sportGameCount}</span>
                        </div>
                        <span class="accordion-arrow">▼</span>
                    </div>
                    ${competitionsHTML}
                </div>
            `;
		}
	}

	container.innerHTML = hasAnyFavorites
		? favoritesHTML
		: '<div class="no-games"><p>No favorite games yet. Click ⭐ to add favorites!</p></div>';

	if (hasAnyFavorites) {
		container.querySelectorAll('.sport-header').forEach(header => {
			header.addEventListener('click', toggleSportCategory);
		});

		container.querySelectorAll('.favorite-star').forEach(star => {
			const gameId = star.getAttribute('data-game-id');
			if (favoriteGames.includes(gameId)) {
				star.textContent = '★';
				star.classList.add('favorited');
			}
			star.addEventListener('click', handleGameFavorite);
		});

		container.querySelectorAll('.league-favorite').forEach(star => {
			star.addEventListener('click', handleLeagueFavorite);
		});
	}

	updateFavoritesCount();
	updateFavoritesURL();
}

function loadFavorites() {
	document.querySelectorAll('.favorite-star').forEach(star => {
		const gameId = star.getAttribute('data-game-id');
		if (favoriteGames.includes(gameId)) {
			star.textContent = '★';
			star.classList.add('favorited');
		}
	});

	document.querySelectorAll('.league-favorite').forEach(star => {
		const leagueId = star.getAttribute('data-league-id');
		if (favoriteLeagues.includes(leagueId)) {
			star.textContent = '★';
			star.classList.add('favorited');
		}
	});

	updateFavoritesCount();
}

function toggleSportCategory() {
	const category = this.closest('.sport-category');
	const competitions = category.querySelectorAll('.competition-group');
	const arrow = this.querySelector('.accordion-arrow');

	competitions.forEach(comp => {
		if (comp.style.display === 'none') {
			comp.style.display = 'block';
			arrow.classList.remove('collapsed');
		} else {
			comp.style.display = 'none';
			arrow.classList.add('collapsed');
		}
	});
}

function handleGameFavorite(e) {
	e.stopPropagation();
	const gameId = this.getAttribute('data-game-id');

	if (favoriteGames.includes(gameId)) {
		favoriteGames = favoriteGames.filter(id => id !== gameId);
		this.textContent = '☆';
		this.classList.remove('favorited');
	} else {
		favoriteGames.push(gameId);
		this.textContent = '★';
		this.classList.add('favorited');
	}

	localStorage.setItem('favoriteGames', JSON.stringify(favoriteGames));
	updateFavoritesCount();

	if (isViewingFavorites) {
		setTimeout(filterFavoritesView, 100);
	}
}

function handleLeagueFavorite(e) {
	e.stopPropagation();
	const leagueId = this.getAttribute('data-league-id');

	if (favoriteLeagues.includes(leagueId)) {
		favoriteLeagues = favoriteLeagues.filter(id => id !== leagueId);
		this.textContent = '☆';
		this.classList.remove('favorited');
	} else {
		favoriteLeagues.push(leagueId);
		this.textContent = '★';
		this.classList.add('favorited');
	}

	localStorage.setItem('favoriteLeagues', JSON.stringify(favoriteLeagues));
	updateFavoritesCount();

	if (isViewingFavorites) {
		setTimeout(filterFavoritesView, 100);
	}
}

document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.sport-header').forEach(header => {
		header.addEventListener('click', toggleSportCategory);
	});

	document.querySelectorAll('.favorite-star').forEach(star => {
		star.addEventListener('click', handleGameFavorite);
	});

	document.querySelectorAll('.league-favorite').forEach(star => {
		star.addEventListener('click', handleLeagueFavorite);
	});

	if (isViewingFavorites) {
		filterFavoritesView();
	} else {
		loadFavorites();
		restoreScrollPosition();
	}

	updateFavoritesCount();
	setTimeout(updateFavoritesCount, 100);
	setTimeout(updateFavoritesCount, 500);

	console.log('Page initialization complete');
});
