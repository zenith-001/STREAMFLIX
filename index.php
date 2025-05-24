<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Watch movies on a clean, modern dark-themed streaming site. Search and browse your favorite titles with a Netflix-like experience." />
  <title>StreamFlix - Watch Movies</title>
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500&family=Roboto+Mono&display=swap"
    rel="stylesheet"
  />
  
  <!-- Premium FontAwesome CDN -->
  <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v6.7.2/css/all.css">
  <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v6.7.2/css/sharp-duotone-thin.css">
  <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v6.7.2/css/sharp-duotone-solid.css">
  <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v6.7.2/css/sharp-duotone-regular.css">
  <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v6.7.2/css/sharp-duotone-light.css">
  <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v6.7.2/css/sharp-thin.css">
  <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v6.7.2/css/sharp-solid.css">
  <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v6.7.2/css/sharp-regular.css">
  <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v6.7.2/css/sharp-light.css">
  <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v6.7.2/css/duotone-thin.css">
  <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v6.7.2/css/duotone-regular.css">
  <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v6.7.2/css/duotone-light.css">

  <style>
    /* Reset and base */
    *, *::before, *::after {
      box-sizing: border-box;
    }
    body {
      margin: 0;
      font-family: 'Poppins', sans-serif;
      background: #121212;
      color: #e0e0e0;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      font-weight: 300;
    }
    a {
      color: inherit;
      text-decoration: none;
    }
    
    /* Header */
    header {
      background-color: #181818;
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      justify-content: center; /* center the logo */
      border-bottom: 1px solid #282828;
      width: 100%;
    }
    .logo {
      font-weight: 500;
      font-size: 1.6rem;
      color: #e50914;
      letter-spacing: 0.1em;
      user-select: none;
    }

    /* Filter section */
    .filter-container {
      display: flex;
      justify-content: center;
      margin: 1rem 0;
      gap: 1rem;
    }
    .filter-container select {
      padding: 0.5rem;
      border-radius: 5px;
      border: 1px solid #444;
      background-color: #2a2a2a;
      color: #e0e0e0;
      font-family: 'Poppins', sans-serif;
      font-size: 1rem;
      transition: background-color 0.3s ease;
    }
    .filter-container select:hover {
      background-color: #3d3d3d;
    }

    /* Search container */
    .search-container {
      position: relative;
      flex: 1 1 360px;
      max-width: 600px;
      min-width: 100%;
      text-align: center;
    }
    .search-container input[type="search"] {
      width: 70%;
      padding: 0.75rem 3.8rem 0.75rem 1rem;
      border-radius: 28px;
      border: none;
      background-color: #2a2a2a;
      color: #e0e0e0;
      font-size: 1.125rem;
      font-weight: 400;
      font-family: 'Roboto Mono', monospace;
      letter-spacing: 0.03em;
      transition: background-color 0.3s ease;
      line-height: 1.4;
    }
    .search-container input[type="search"]::placeholder {
      color: #888;
      font-weight: 300;
    }
    .search-container input[type="search"]:focus {
      outline: none;
      background-color: #3d3d3d;
      box-shadow: 0 0 8px #e50914aa;
    }
    .search-button {
      position: absolute;
      right: 16.5%;
      top: 50%;
      transform: translateY(-50%);
      background-color: transparent;
      border: none;
      color: #888;
      font-size: 1.4rem;
      padding: 0.5rem 0.9rem;
      border-radius: 22px;
      cursor: pointer;
      transition: color 0.3s ease, background-color 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .search-button:hover {
      color: #e50914;
      background-color: #3d3d3d;
    }
    .search-button:focus {
      outline: 2px solid #e50914;
      outline-offset: 2px;
    }

    /* Main content */
    main {
      flex-grow: 1;
      padding: 2rem;
      max-width: 1200px;
      margin: 0 auto;
      width: 100%;
    }
    h1.page-title {
      font-weight: 500;
      font-size: 2rem;
      margin-bottom: 1.5rem;
      user-select: none;
      color: #fff;
    }
    .movies-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 1.4rem;
    }

    /* Movie card */
    .movie-card {
      background-color: #1f1f1f;
      border-radius: 12px;
      padding: 1rem 1rem 1.3rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 0 0 8px #00000080;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      cursor: pointer;
    }
    .movie-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 4px 12px #e5091420;
    }
    .movie-placeholder {
      background: linear-gradient(45deg, #e5091415, #72111115);
      height: 240px;
      border-radius: 8px;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 4rem;
      color: #e50914aa;
      user-select: none;
    }
    .movie-info {
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .movie-title {
      font-weight: 500;
      font-size: 1.05rem;
      margin: 0 0 0.2rem 0;
      color: #fff;
      user-select: text;
    }
    .movie-genre {
      font-size: 0.82rem;
      font-weight: 300;
      color: #bbb;
      margin-bottom: 0.6rem;
      user-select: text;
    }

    /* Responsive tweaks */
    @media (max-width: 720px) {
      header {
        padding: 1rem;
        text-align: center;
      justify-content: center; /* center the logo */

      }
      .logo {
        font-size: 1.25rem;
        min-width: 100%;
      }
      main {
        padding: 1rem;
      }
      h1.page-title {
        text-align: center;
        font-size: 1.7rem;
      }
      .search-container input[type="search"]{
        width: 100%;
        padding: 0.75rem 1rem;
      }
      .search-button {
        right: 1.2%;
      }
    }
  </style>
</head>
<body>
  <header>
    <div class="logo" aria-label="StreamFlix logo">StreamFlix</div>
  </header>
  <main>
    <div class="filter-container">
      <select id="genreFilter">
        <option value="">All Genres</option>
        <option value="Sci-Fi">Sci-Fi</option>
        <option value="Thriller">Thriller</option>
        <option value="Romance">Romance</option>
        <option value="Adventure">Adventure</option>
        <option value="Mystery">Mystery</option>
        <option value="Musical">Musical</option>
        <option value="Drama">Drama</option>
        <option value="Action">Action</option>
        <option value="Fantasy">Fantasy</option>
        <option value="Western">Western</option>
        <option value="Horror">Horror</option>
      </select>
    </div>
    <div class="search-container">
      <input
        type="search"
        placeholder="Search movies..."
        aria-label="Search movies"
        id="searchInput"
        name="search"
        autocomplete="off"
      />
      <button type="submit" class="search-button" aria-label="Submit search">
        <i class="fa-solid fa-magnifying-glass"></i>
      </button>
    </div>
    <h1 class="page-title">Popular Movies</h1>
    <section class="movies-grid" id="moviesGrid" aria-live="polite" aria-label="Movies list">
      <!-- Movie cards will be inserted here by JavaScript -->
    </section>
  </main>
<?php include 'db.php'; ?>
<script>
  const movies = <?php
    $sql = "SELECT id, title, genre FROM movies";
    $result = $conn->query($sql);
    $movies = [];

    while ($row = $result->fetch_assoc()) {
      $movies[] = $row;
    }
    echo json_encode($movies);
  ?>;
</script>

  <script>
    // // Mock movie data for demonstration
    // const movies = [
    //   { id: 1, title: "Galaxy Raiders", genre: "Sci-Fi" },
    //   { id: 2, title: "Midnight Escape", genre: "Thriller" },
    //   { id: 3, title: "Love & Code", genre: "Romance" },
    //   { id: 4, title: "Knights of Valor", genre: "Adventure" },
    //   { id: 5, title: "City of Shadows", genre: "Mystery" },
    //   { id: 6, title: "Jazz & Blues", genre: "Musical" },
    //   { id: 7, title: "Echoes of Time", genre: "Drama" },
    //   { id: 8, title: "Cybercore", genre: "Action" },
    //   { id: 9, title: "Frozen Dawn", genre: "Fantasy" },
    //   { id: 10, title: "Shadow Frontier", genre: "Western" },
    //   { id: 11, title: "Neon Lights", genre: "Sci-Fi" },
    //   { id: 12, title: "Silent Whisper", genre: "Horror" }
    // ];

    const moviesGrid = document.getElementById('moviesGrid');
    const genreFilter = document.getElementById('genreFilter');
    const searchInput = document.getElementById('searchInput');

    function createMovieCard(movie) {
      const card = document.createElement('a');
      card.className = 'movie-card';
      card.href = `watch.php?id=${movie.id}`;
      card.setAttribute('tabindex', '0');
      card.setAttribute('aria-label', `${movie.title}, ${movie.genre} movie`);

      const placeholder = document.createElement('div');
      placeholder.className = 'movie-placeholder';
      placeholder.textContent = movie.title.charAt(0).toUpperCase();

      const info = document.createElement('div');
      info.className = 'movie-info';

      const title = document.createElement('h2');
      title.className = 'movie-title';
      title.textContent = movie.title;

      const genre = document.createElement('p');
      genre.className = 'movie-genre';
      genre.textContent = movie.genre;

      info.appendChild(title);
      info.appendChild(genre);

      card.appendChild(placeholder);
      card.appendChild(info);

      return card;
    }

    function renderMovies(filteredMovies) {
      moviesGrid.innerHTML = '';
      if (filteredMovies.length === 0) {
        const noResults = document.createElement('p');
        noResults.textContent = 'No movies found.';
        noResults.style.color = '#bbb';
        noResults.style.fontStyle = 'italic';
        moviesGrid.appendChild(noResults);
        return;
      }
      filteredMovies.forEach(movie => {
        moviesGrid.appendChild(createMovieCard(movie));
      });
    }

    function filterMovies() {
      const selectedGenre = genreFilter.value;

      const filtered = movies.filter(movie => {
        const matchesGenre = selectedGenre === '' || movie.genre === selectedGenre;
        return matchesGenre;
      });

      renderMovies(filtered);
    }

    // Initial render
    renderMovies(movies);

    // Event listeners for filters
    genreFilter.addEventListener('change', filterMovies);
    searchInput.addEventListener('input', () => {
      const query = searchInput.value.trim().toLowerCase();
      const filtered = movies.filter(movie => movie.title.toLowerCase().includes(query));
      renderMovies(filtered);
    });
  </script>
</body>
</html>
