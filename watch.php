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
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
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

    /* Main content */
    main {
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      max-width: 800px;
      width: 100%;
    }
    video {
      width: 100%;
      height: auto;
      border-radius: 12px;
      margin-bottom: 1.5rem;
    }
    .movie-details {
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      gap: 1rem;
    }
    .movie-title {
      font-size: 1.5rem;
      font-weight: 500;
      margin: 0.5rem 0;
    }
    .movie-genre, .movie-id {
      font-size: 1rem;
      margin: 0.2rem 0;
      color: #bbb;
    }
    .favorite-icon {
      color: #e50914;
      font-size: 1.5rem;
    }
  </style>
</head>
<body>
  <header>
    <a href="index.php" class="logo" aria-label="StreamFlix logo">StreamFlix</a>
  </header>
  <main>
    <video id="moviePlayer" controls>
      <source id="movieSource" src="" type="video/mp4">
      Your browser does not support the video tag.
    </video>
    <div class="movie-details">
      <h2 class="movie-title" id="movieTitle"></h2>
      <p class="movie-genre" id="movieGenre"></p>
      <p class="movie-id" id="movieId"></p>
      <i class="favorite-icon fa-heart" id="favoriteIcon"></i>
    </div>
  </main>

  <script>
    // Mock movie data
    const movies = [
      { id: 1, title: "Galaxy Raiders", genre: "Sci-Fi", video: "path/to/galaxy_raiders.mp4" },
      { id: 2, title: "Midnight Escape", genre: "Thriller", video: "path/to/midnight_escape.mp4" },
      { id: 3, title: "Love & Code", genre: "Romance", video: "path/to/love_code.mp4" },
      { id: 4, title: "Knights of Valor", genre: "Adventure", video: "path/to/knights_valor.mp4" },
      { id: 5, title: "City of Shadows", genre: "Mystery", video: "path/to/city_shadows.mp4" },
      { id: 6, title: "Jazz & Blues", genre: "Musical", video: "path/to/jazz_blues.mp4" },
      { id: 7, title: "Echoes of Time", genre: "Drama", video: "path/to/echoes_time.mp4" },
      { id: 8, title: "Cybercore", genre: "Action", video: "path/to/cybercore.mp4" },
      { id: 9, title: "Frozen Dawn", genre: "Fantasy", video: "path/to/frozen_dawn.mp4" },
      { id: 10, title: "Shadow Frontier", genre: "Western", video: "path/to/shadow_frontier.mp4" },
      { id: 11, title: "Neon Lights", genre: "Sci-Fi", video: "path/to/neon_lights.mp4" },
      { id: 12, title: "Silent Whisper", genre: "Horror", video: "path/to/silent_whisper.mp4" }
    ];

    // Get the movie ID from the URL
    const urlParams = new URLSearchParams(window.location.search);
    const movieId = parseInt(urlParams.get('id'));

    // Find the movie in the array
    const movie = movies.find(m => m.id === movieId);

    if (movie) {
      // Set the video source
      const movieSource = document.getElementById('movieSource');
      movieSource.src = movie.video;
      const moviePlayer = document.getElementById('moviePlayer');
      moviePlayer.load();

      // Set movie details
      document.getElementById('movieTitle').textContent = movie.title;
      document.getElementById('movieGenre').textContent = `Genre: ${movie.genre}`;
      document.getElementById('movieId').textContent = `ID: ${movie.id}`;
      
      // Check if the movie is favorited
      const favorites = JSON.parse(localStorage.getItem('streamflix_favorites')) || [];
      const favoriteIcon = document.getElementById('favoriteIcon');
      favoriteIcon.classList.add(favorites.includes(movie.id) ? 'fa-solid' : 'fa-regular');

      // Toggle favorite status on icon click
      favoriteIcon.onclick = () => {
        if (favorites.includes(movie.id)) {
          favorites.splice(favorites.indexOf(movie.id), 1);
          favoriteIcon.classList.remove('fa-solid');
          favoriteIcon.classList.add('fa-regular');
        } else {
          favorites.push(movie.id);
          favoriteIcon.classList.remove('fa-regular');
          favoriteIcon.classList.add('fa-solid');
        }
        localStorage.setItem('streamflix_favorites', JSON.stringify(favorites));
      };
    } else {
      document.body.innerHTML = '<h2 style="color: #fff;">Movie not found.</h2>';
    }
  </script>
</body>
</html>
