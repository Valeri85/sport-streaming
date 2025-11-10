let favoriteGames = JSON.parse(localStorage.getItem('favoriteGames') || '[]');
let favoriteLeagues = JSON.parse(localStorage.getItem('favoriteLeagues') || '[]');
const isViewingFavorites = document.body.dataset.viewingFavorites === 'true';
const linksData = JSON.parse(document.body.dataset.links || '{}');

let currentOffset = 30;
let isLoading = false;
let hasMoreGames = true;

const activeSport = document.body.dataset.activeSport || '';
const activeTab = document.body.dataset.activeTab || 'all';

console.log('Favorites loaded:', {
	games: favoriteGames.length,
	leagues: favoriteLeagues.length,
	total: favoriteGames.length + favoriteLeagues.length,
});

function initDarkMode() {
	const darkMode = localStorage.getItem('darkMode') === 'true';
	if (darkMode) {
		document.body.classList.add('dark-mode');
		updateThemeIcon();
	}

	const themeToggle = document.getElementById('themeToggle');
	if (themeToggle) {
		themeToggle.addEventListener('click', toggleDarkMode);
	}
}

function toggleDarkMode() {
	document.body.classList.toggle('dark-mode');
	const isDark = document.body.classList.contains('dark-mode');
	localStorage.setItem('darkMode', isDark);
	updateThemeIcon();
}

function updateThemeIcon() {
	const themeIcon = document.querySelector('.theme-icon');
	if (themeIcon) {
		themeIcon.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
	}
}

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

function updateFavoritesCount() {
	const totalFavorites = favoriteGames.length + favoriteLeagues.length;
	const countElement = document.getElementById('favoritesCount');

	if (countElement) {
		countElement.textContent = totalFavorites;
		console.log('Count updated to:', totalFavorites);
	}
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
			const country = compGroup.getAttribute('data-country');

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

					const compKey = country + '|||' + competition;
					if (!allSportsData[sportName].competitions[compKey]) {
						allSportsData[sportName].competitions[compKey] = {
							leagueId: leagueId,
							country: country,
							competition: competition,
							countryDisplay: compGroup.querySelector('.competition-name').innerHTML,
							games: [],
						};
					}

					allSportsData[sportName].competitions[compKey].games.push(game.outerHTML);
				}
			});
		});
	});

	for (const sportName in allSportsData) {
		const sport = allSportsData[sportName];
		let sportGameCount = 0;
		let competitionsHTML = '';

		for (const compKey in sport.competitions) {
			const comp = sport.competitions[compKey];
			sportGameCount += comp.games.length;

			const isLeagueFavorited = favoriteLeagues.includes(comp.leagueId);

			competitionsHTML += `
                <div class="competition-group" data-league-id="${comp.leagueId}">
                    <div class="competition-header">
                        <div class="competition-name">
                            ${comp.countryDisplay}
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
                    <details open>
                        <summary class="sport-header">
                            <div class="sport-title">
                                <span>${sport.icon}</span>
                                <span>${sportName}</span>
                                <span class="sport-count-badge" style="background-color: ${sportCountBadgeColor};">${sportGameCount}</span>
                            </div>
                        </summary>
                        ${competitionsHTML}
                    </details>
                </div>
            `;
		}
	}

	container.innerHTML = hasAnyFavorites
		? favoritesHTML
		: '<div class="no-games"><p>No favorite games yet. Click ⭐ to add favorites!</p></div>';

	if (hasAnyFavorites) {
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

		container.querySelectorAll('.game-item').forEach(item => {
			item.addEventListener('click', handleGameClick);
		});
	}

	updateFavoritesCount();
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

function handleGameClick(e) {
	if (e.target.classList.contains('favorite-star') || e.target.classList.contains('league-favorite')) {
		return;
	}

	const gameItem = this;
	const gameId = gameItem.getAttribute('data-game-id');

	let linksContainer = gameItem.querySelector('.game-links');
	if (linksContainer) {
		linksContainer.classList.toggle('visible');
		return;
	}

	const links = linksData[gameId];
	if (!links || links.length === 0) {
		alert('No streaming links available for this game');
		return;
	}

	gameItem.classList.add('loading');

	setTimeout(() => {
		gameItem.classList.remove('loading');

		linksContainer = document.createElement('div');
		linksContainer.className = 'game-links visible';

		let linksHTML = '<div class="game-links-title">Available Streams:</div>';

		links.forEach(link => {
			linksHTML += `
                <a href="${link.link}" target="_blank" class="link-item">
                    <span class="link-badge">${link.type}</span>
                    <span>Watch Stream</span>
                </a>
            `;
		});

		linksContainer.innerHTML = linksHTML;
		gameItem.querySelector('.game-teams').appendChild(linksContainer);
	}, 300);
}

function loadMoreGames() {
	if (isLoading || !hasMoreGames) return;

	isLoading = true;
	const loadingIndicator = document.getElementById('loadingIndicator');
	if (loadingIndicator) {
		loadingIndicator.style.display = 'block';
	}

	const sportParam = activeSport ? `&sport=${activeSport}` : '';
	const tabParam = `&tab=${activeTab}`;

	fetch(`/api/load-games.php?offset=${currentOffset}&limit=30${sportParam}${tabParam}`)
		.then(response => response.json())
		.then(data => {
			if (data.success && data.html) {
				const mainContent = document.getElementById('mainContent');
				const loadingIndicator = document.getElementById('loadingIndicator');

				const tempDiv = document.createElement('div');
				tempDiv.innerHTML = data.html;

				if (loadingIndicator) {
					mainContent.insertBefore(tempDiv.firstChild, loadingIndicator);
				} else {
					mainContent.appendChild(tempDiv.firstChild);
				}

				attachEventListeners();

				currentOffset += 30;
				hasMoreGames = data.hasMore;

				console.log('Loaded games:', data.loaded, '/', data.total);

				if (!hasMoreGames) {
					const trigger = document.getElementById('loadMoreTrigger');
					if (trigger && observer) {
						observer.unobserve(trigger);
					}
					if (loadingIndicator) {
						loadingIndicator.style.display = 'none';
					}
				}
			}

			isLoading = false;
			if (loadingIndicator) {
				loadingIndicator.style.display = 'none';
			}
		})
		.catch(error => {
			console.error('Error loading more games:', error);
			isLoading = false;
			if (loadingIndicator) {
				loadingIndicator.style.display = 'none';
			}
		});
}

function attachEventListeners() {
	document.querySelectorAll('.favorite-star').forEach(star => {
		if (!star.dataset.listenerAttached) {
			star.addEventListener('click', handleGameFavorite);
			star.dataset.listenerAttached = 'true';
		}
	});

	document.querySelectorAll('.league-favorite').forEach(star => {
		if (!star.dataset.listenerAttached) {
			star.addEventListener('click', handleLeagueFavorite);
			star.dataset.listenerAttached = 'true';
		}
	});

	document.querySelectorAll('.game-item').forEach(item => {
		if (!item.dataset.listenerAttached) {
			item.addEventListener('click', handleGameClick);
			item.dataset.listenerAttached = 'true';
		}
	});

	loadFavorites();
}

let observer = null;

function setupIntersectionObserver() {
	const trigger = document.getElementById('loadMoreTrigger');
	if (!trigger) return;

	observer = new IntersectionObserver(
		entries => {
			entries.forEach(entry => {
				if (entry.isIntersecting) {
					loadMoreGames();
				}
			});
		},
		{
			rootMargin: '200px',
		}
	);

	observer.observe(trigger);
}

document.addEventListener('DOMContentLoaded', function () {
	initDarkMode();

	attachEventListeners();

	if (isViewingFavorites) {
		filterFavoritesView();
	} else {
		restoreScrollPosition();
		setupIntersectionObserver();
	}

	updateFavoritesCount();
	setTimeout(updateFavoritesCount, 100);
	setTimeout(updateFavoritesCount, 500);

	console.log('Page initialization complete');
});
