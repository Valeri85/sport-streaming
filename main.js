let favoriteGames = JSON.parse(localStorage.getItem('favoriteGames') || '[]');
let favoriteLeagues = JSON.parse(localStorage.getItem('favoriteLeagues') || '[]');
const isViewingFavorites = document.body.dataset.viewingFavorites === 'true';

let currentOffset = 15;
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
	}
}

async function fetchLinkCount(gameId) {
	try {
		const response = await fetch(`/api/get-links.php?game_id=${gameId}`);
		const data = await response.json();
		return data.count || 0;
	} catch (error) {
		console.error('Error fetching link count:', error);
		return 0;
	}
}

async function loadAllLinkCounts() {
	const badges = document.querySelectorAll('.link-count-badge[data-game-id]');
	badges.forEach(async badge => {
		const gameId = badge.getAttribute('data-game-id');
		const count = await fetchLinkCount(gameId);
		badge.textContent = count;
		if (count === 0) {
			badge.style.display = 'none';
		}
	});
}

function createSkeletonLoader() {
	return `
		<div class="skeleton-loader">
			<div class="skeleton-item"></div>
			<div class="skeleton-item"></div>
			<div class="skeleton-item"></div>
		</div>
	`;
}

function filterFavoritesView() {
	if (!isViewingFavorites) return;

	const container = document.getElementById('favoritesContainer');
	const templateData = document.getElementById('templateData');

	if (!templateData) return;

	let hasAnyFavorites = false;
	let favoritesHTML = '';
	const allSportsData = {};

	templateData.content.querySelectorAll('.sport-category').forEach(category => {
		const sportName = category.getAttribute('data-sport');
		const sportIcon = category.querySelector('.sport-title span:first-child')?.textContent || '⚽';

		const competitions = category.querySelectorAll('.competition-group');
		competitions.forEach(compGroup => {
			const leagueId = compGroup.getAttribute('data-league-id');
			const competition = compGroup.getAttribute('data-competition');
			const country = compGroup.getAttribute('data-country');

			const games = compGroup.querySelectorAll('.game-item-details');
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
                <section class="competition-group" data-league-id="${comp.leagueId}">
                    <div class="competition-header">
                        <span class="competition-name">
                            ${comp.countryDisplay}
                        </span>
                        <span class="league-favorite ${isLeagueFavorited ? 'favorited' : ''}" data-league-id="${
				comp.leagueId
			}" role="button" aria-label="Favorite league">${isLeagueFavorited ? '★' : '☆'}</span>
                    </div>
                    ${comp.games.join('')}
                </section>
            `;
		}

		if (sportGameCount > 0) {
			hasAnyFavorites = true;

			const sportCountBadgeColor = document.body.dataset.primaryColor || '#FFA500';

			favoritesHTML += `
                <article class="sport-category">
                    <details open>
                        <summary class="sport-header">
                            <span class="sport-title">
                                <span>${sport.icon}</span>
                                <span>${sportName}</span>
                                <span class="sport-count-badge" style="background-color: ${sportCountBadgeColor};">${sportGameCount}</span>
                            </span>
                        </summary>
                        ${competitionsHTML}
                    </details>
                </article>
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

		container.querySelectorAll('.game-item-details').forEach(details => {
			details.addEventListener('toggle', handleGameToggle);
		});

		loadAllLinkCounts();
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
	e.preventDefault();
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

async function handleGameToggle(e) {
	const details = e.target;
	const gameId = details.getAttribute('data-game-id');
	const linksContainer = details.querySelector('.game-links-container');

	if (details.open) {
		if (linksContainer.children.length > 0) {
			return;
		}

		linksContainer.innerHTML = createSkeletonLoader();

		try {
			const response = await fetch(`/api/get-links.php?game_id=${gameId}`);
			const data = await response.json();

			setTimeout(() => {
				if (!data.success || !data.links || data.links.length === 0) {
					linksContainer.innerHTML = '<div class="no-games"><p>No streaming links available</p></div>';
					return;
				}

				let linksHTML = '<div class="game-links-title">Available Streams:</div>';
				linksHTML += '<div class="game-links-grid">';

				data.links.forEach(link => {
					linksHTML += `
                        <a href="${link.link}" target="_blank" rel="noopener noreferrer" class="link-item">
                            <span class="link-item-content">
                                <span class="link-badge">${link.type}</span>
                                <span>Watch Stream</span>
                            </span>
                            <span class="external-icon">↗</span>
                        </a>
                    `;
				});

				linksHTML += '</div>';
				linksContainer.innerHTML = linksHTML;
			}, 800);
		} catch (error) {
			console.error('Error loading links:', error);
			linksContainer.innerHTML = '<div class="no-games"><p>Error loading links</p></div>';
		}
	}
}

function loadMoreGames() {
	if (isLoading || !hasMoreGames) {
		return;
	}

	isLoading = true;
	const loadingIndicator = document.getElementById('loadingIndicator');
	if (loadingIndicator) {
		loadingIndicator.style.display = 'block';
	}

	const sportParam = activeSport ? `&sport=${activeSport}` : '';
	const tabParam = `&tab=${activeTab}`;

	fetch(`/api/load-games.php?offset=${currentOffset}&limit=15${sportParam}${tabParam}`)
		.then(response => response.json())
		.then(data => {
			if (data.success && data.html) {
				const mainContent = document.getElementById('mainContent');
				const loadingIndicator = document.getElementById('loadingIndicator');
				const trigger = document.getElementById('loadMoreTrigger');

				const tempDiv = document.createElement('div');
				tempDiv.innerHTML = data.html;

				while (tempDiv.firstChild) {
					if (loadingIndicator) {
						mainContent.insertBefore(tempDiv.firstChild, loadingIndicator);
					} else {
						mainContent.appendChild(tempDiv.firstChild);
					}
				}

				attachEventListeners();

				currentOffset += 15;
				hasMoreGames = data.hasMore;

				console.log('Loaded games:', data.loaded, '/', data.total, 'Has more:', hasMoreGames);

				if (!hasMoreGames) {
					if (trigger) trigger.remove();
					if (loadingIndicator) loadingIndicator.remove();
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

	document.querySelectorAll('.game-item-details').forEach(details => {
		if (!details.dataset.listenerAttached) {
			details.addEventListener('toggle', handleGameToggle);
			details.dataset.listenerAttached = 'true';
		}
	});

	loadFavorites();
	loadAllLinkCounts();
}

let observer = null;

function setupIntersectionObserver() {
	const trigger = document.getElementById('loadMoreTrigger');
	if (!trigger) {
		console.log('No trigger found');
		return;
	}

	if (observer) {
		observer.disconnect();
	}

	observer = new IntersectionObserver(
		entries => {
			entries.forEach(entry => {
				if (entry.isIntersecting && !isLoading && hasMoreGames) {
					console.log('Trigger visible, loading more games');
					loadMoreGames();
				}
			});
		},
		{
			root: null,
			rootMargin: '100px',
			threshold: 0.1,
		}
	);

	observer.observe(trigger);
	console.log('Observer attached to trigger');
}

document.addEventListener('DOMContentLoaded', function () {
	initDarkMode();

	attachEventListeners();

	if (isViewingFavorites) {
		filterFavoritesView();
	} else {
		restoreScrollPosition();
	}

	updateFavoritesCount();
	setTimeout(updateFavoritesCount, 100);

	console.log('Page initialization complete');
});
