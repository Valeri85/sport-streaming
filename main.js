let favoriteGames = JSON.parse(localStorage.getItem('favoriteGames') || '[]');
let favoriteLeagues = JSON.parse(localStorage.getItem('favoriteLeagues') || '[]');
const isViewingFavorites = document.body.dataset.viewingFavorites === 'true';

// Cache for links data
let linksDataCache = null;
let linksDataPromise = null;

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
			// Use requestAnimationFrame to batch DOM operations and prevent forced reflow
			requestAnimationFrame(() => {
				menu.scrollTop = parseInt(scrollPos);
			});
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

// Fetch links data from external source
async function fetchLinksData() {
	// Return cached data if available
	if (linksDataCache !== null) {
		return linksDataCache;
	}

	// Return existing promise if fetch is already in progress
	if (linksDataPromise !== null) {
		return linksDataPromise;
	}

	// Start new fetch
	linksDataPromise = fetch('https://watchlivesport.online/data.php')
		.then(response => {
			if (!response.ok) {
				throw new Error('Failed to fetch links data');
			}
			return response.json();
		})
		.then(data => {
			linksDataCache = data.links || {};
			linksDataPromise = null;
			return linksDataCache;
		})
		.catch(error => {
			console.error('Error fetching links data:', error);
			linksDataPromise = null;
			return {};
		});

	return linksDataPromise;
}

async function fetchLinkCount(gameId) {
	try {
		const linksData = await fetchLinksData();
		const links = linksData[gameId] || [];
		return links.length;
	} catch (error) {
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

			favoritesHTML += `
                <article class="sport-category">
                    <details open>
                        <summary class="sport-header">
                            <span class="sport-title">
                                <span>${sport.icon}</span>
                                <span>${sportName}</span>
                                <span class="sport-count-badge">${sportGameCount}</span>
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
		// Batch DOM operations using requestAnimationFrame to prevent forced reflows
		requestAnimationFrame(() => {
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
			// Fetch links data from external source
			const linksData = await fetchLinksData();
			const links = linksData[gameId] || [];

			setTimeout(() => {
				if (!links || links.length === 0) {
					linksContainer.innerHTML = '<div class="no-games"><p>No streaming links available</p></div>';
					return;
				}

				let linksHTML = '<div class="game-links-title">Available Streams:</div>';
				linksHTML += '<div class="game-links-grid">';

				links.forEach(link => {
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
			linksContainer.innerHTML = '<div class="no-games"><p>Error loading links</p></div>';
		}
	}
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

document.addEventListener('DOMContentLoaded', function () {
	initDarkMode();
	attachEventListeners();
	initBurgerMenu();

	if (isViewingFavorites) {
		filterFavoritesView();
	} else {
		restoreScrollPosition();
	}

	updateFavoritesCount();
	setTimeout(updateFavoritesCount, 100);
});

function initBurgerMenu() {
	const burgerMenu = document.getElementById('burgerMenu');
	const sidebar = document.getElementById('sidebar');
	const overlay = document.getElementById('overlay');

	if (!burgerMenu || !sidebar || !overlay) return;

	burgerMenu.addEventListener('click', function () {
		burgerMenu.classList.toggle('open');
		sidebar.classList.toggle('open');
		overlay.classList.toggle('active');
		document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
	});

	overlay.addEventListener('click', function () {
		burgerMenu.classList.remove('open');
		sidebar.classList.remove('open');
		overlay.classList.remove('active');
		document.body.style.overflow = '';
	});

	// Close menu when clicking on a menu item
	const menuItems = sidebar.querySelectorAll('.menu-item, .favorites-link');
	menuItems.forEach(item => {
		item.addEventListener('click', function () {
			if (window.innerWidth <= 1200) {
				burgerMenu.classList.remove('open');
				sidebar.classList.remove('open');
				overlay.classList.remove('active');
				document.body.style.overflow = '';
			}
		});
	});
}
