  <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
  <header class="site-header visitor-header" id="header-visitor">
    <div class="header-inner">

      <!-- Logo -->
      <a href="#" class="logo" aria-label="FreelanceUPC accueil">
        <div class="logo-mark">
          <span class="lm-r">F</span><span class="lm-b">U</span>
        </div>
        <div class="logo-text">
          <span class="logo-name">Freelance<em>UPC</em></span>
          <span class="logo-tag">Étudiants · Talents · Projets</span>
        </div>
      </a>

      <!-- Nav principale -->
      <nav class="main-nav" aria-label="Navigation principale">
        <div class="nav-item has-dropdown">
          <button class="nav-link" aria-haspopup="true" aria-expanded="false">
            Trouver un talent
            <svg class="chevron" viewBox="0 0 16 16" fill="none">
              <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <div class="dropdown mega-dropdown">
            <div class="dropdown-section">
              <p class="dropdown-heading">Par compétence</p>
              <a href="#" class="dropdown-item">Développement Web</a>
              <a href="#" class="dropdown-item">Design graphique</a>
              <a href="#" class="dropdown-item">Marketing digital</a>
              <a href="#" class="dropdown-item">Rédaction & Contenu</a>
            </div>
            <div class="dropdown-section">
              <p class="dropdown-heading">Par catégorie</p>
              <a href="#" class="dropdown-item">Applications mobiles</a>
              <a href="#" class="dropdown-item">Intelligence artificielle</a>
              <a href="#" class="dropdown-item">Base de données</a>
              <a href="#" class="dropdown-item">Cybersécurité</a>
            </div>
            <div class="dropdown-section dropdown-featured">
              <p class="dropdown-heading">Populaires</p>
              <a href="#" class="dropdown-item chip">Développeurs web</a>
              <a href="#" class="dropdown-item chip">Designers UI</a>
              <a href="#" class="dropdown-item chip">Data analysts</a>
            </div>
          </div>
        </div>

        <div class="nav-item has-dropdown">
          <button class="nav-link" aria-haspopup="true" aria-expanded="false">
            Trouver du travail
            <svg class="chevron" viewBox="0 0 16 16" fill="none">
              <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <div class="dropdown">
            <div class="dropdown-section">
              <a href="#" class="dropdown-item">Voir tous les projets</a>
              <a href="#" class="dropdown-item">Projets récents</a>
              <a href="#" class="dropdown-item">Projets urgents</a>
              <a href="#" class="dropdown-item">Concours & Défis</a>
            </div>
          </div>
        </div>

        <a href="#" class="nav-link simple">Comment ça marche</a>
      </nav>

      <!-- Actions visiteur -->
      <div class="header-actions">
        <a href="#" class="btn-ghost">Se connecter</a>
        <a href="#" class="btn-outline">S'inscrire</a>
        <a href="#" class="btn-primary">
          <svg viewBox="0 0 16 16" fill="none" width="14" height="14">
            <path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Publier un projet
        </a>
      </div>

      <!-- Burger mobile -->
      <button class="burger" id="burger-visitor" aria-label="Menu mobile" onclick="toggleMobileMenu('visitor')">
        <span></span><span></span><span></span>
      </button>
    </div>

    <!-- Mobile menu visiteur -->
    <div class="mobile-menu" id="mobile-visitor">
      <nav class="mobile-nav">
        <a href="#" class="mobile-nav-link">Trouver un talent</a>
        <a href="#" class="mobile-nav-link">Trouver du travail</a>
        <a href="#" class="mobile-nav-link">Comment ça marche</a>
      </nav>
      <div class="mobile-actions">
        <a href="#" class="btn-ghost full">Se connecter</a>
        <a href="#" class="btn-outline full">S'inscrire</a>
        <a href="#" class="btn-primary full">Publier un projet</a>
      </div>
    </div>
  </header>
