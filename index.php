<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/paths.php';
require_once __DIR__ . '/includes/auth.php';
require __DIR__ . '/contact_handler.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Goo-Bridge – Agence Web & Mobile | Sites, Applications, Hébergement</title>
  <meta name="description"
    content="Goo-Bridge est votre agence digitale spécialisée en développement web, applications mobiles et hébergement sécurisé. De l'idée au lancement, nous construisons votre présence en ligne." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet" />
  <link rel="icon" type="image/svg+xml" href="favicon.svg" />
  <link rel="stylesheet" href="style.css" />
</head>

<body>

  <!-- ===== NAVBAR ===== -->
  <?php $siteHeaderOnIndex = true; require __DIR__ . '/partials/site_header.php'; ?>

  <main>

    <!-- ===== HERO ===== -->
    <section id="accueil" class="hero">
      <div class="hero-bg-grid" aria-hidden="true"></div>
      <?php require __DIR__ . '/partials/globe.php'; ?>

      <div class="hero-content">
        <h1 class="hero-title reveal" data-delay="80">
          Nous construisons votre<br />
          <span class="gradient-text">présence digitale</span>
        </h1>
        <p class="hero-sub reveal" data-delay="160">
          De la conception à la mise en ligne, Goo-Bridge vous accompagne à chaque étape. Sites web modernes,
          applications mobiles performantes, hébergement sécurisé — tout ce qu'il faut pour que votre entreprise rayonne
          en ligne.
        </p>
        <div class="hero-actions reveal" data-delay="240">
          <a href="#contact" class="btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path
                d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.27h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.96a16 16 0 0 0 6.13 6.13l.96-.96a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
            </svg>
            Démarrer un projet
          </a>
          <a href="#realisations" class="btn-ghost">Voir nos réalisations →</a>
        </div>

        <!-- Trust row -->
        <div class="hero-trust reveal" data-delay="320">
          <div class="trust-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span>Livraison dans les délais</span>
          </div>
          <div class="trust-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span>Support 24/7 inclus</span>
          </div>
          <div class="trust-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span>Hébergement sécurisé SSL</span>
          </div>
          <div class="trust-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span>5 ans d'expérience</span>
          </div>
        </div>
      </div>

      <div class="hero-visual reveal" data-delay="100">
        <div class="hero-visual__frame">
          <div class="hero-img-wrap">
            <img src="images/hero-agency.png" alt="Goo-Bridge Agence Digitale" width="800" height="800" loading="eager" decoding="async" fetchpriority="high" />
            <div class="hero-badge-float">
              <span class="pulse-green"></span>
              Portail déployé en production
            </div>
            <div class="hero-badge-float2">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              </svg>
              SSL · 99.9% uptime
            </div>
          </div>
          <ul class="hero-service-chips" aria-label="Types de projets">
            <li class="hero-service-chip reveal" data-delay="500">🌐 Site vitrine</li>
            <li class="hero-service-chip reveal" data-delay="600">📱 App mobile</li>
            <li class="hero-service-chip reveal" data-delay="700">🛒 E-commerce</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- ===== SERVICES ===== -->
    <section id="services" class="section-services">
      <div class="section-header reveal">
        <span class="section-tag">Nos Services</span>
        <h2>Ce que nous faisons <span class="gradient-text">concrètement</span> pour vous</h2>
        <p>Chaque service est pensé pour répondre à un vrai besoin business. Pas de jargon inutile — juste des
          résultats.</p>
      </div>

      <!-- Service 1 -->
      <div class="service-row reveal" style="--st:#16a34a">
        <div class="service-row-text">
          <div class="sr-tag" style="--st:#16a34a">Développement Web</div>
          <h3>Votre site web, votre vitrine permanente</h3>
          <p>Un site web, c'est votre commercial disponible 24h/24. Nous créons des sites qui <strong>attirent,
              convainquent et convertissent</strong> vos visiteurs en clients.</p>
          <ul class="service-list">
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Sites vitrines & institutionnels</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Boutiques e-commerce (catalogue, panier, paiement)</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Plateformes web complexes & dashboards</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Sites 100% responsive (mobile, tablette, desktop)</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Optimisation SEO & performance</li>
          </ul>
          <a href="#contact" class="btn-primary" style="margin-top:28px;display:inline-flex;">Demander un devis</a>
        </div>
        <div class="service-row-img">
          <img src="images/about-ecommerce.png" alt="Développement Web & E-commerce" width="800" height="800" loading="lazy" decoding="async" />
        </div>
      </div>

      <!-- Service 2 -->
      <div class="service-row reverse reveal" style="--st:#3b82f6">
        <div class="service-row-text">
          <div class="sr-tag" style="--st:#3b82f6">Applications Mobiles</div>
          <h3>Une app dans la poche de vos clients</h3>
          <p>Vos clients passent leur journée sur leur smartphone. Une application mobile vous permet d'être
            <strong>toujours présent, fidéliser et proposer une expérience unique</strong>.
          </p>
          <ul class="service-list">
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Applications iOS (iPhone, iPad)</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Applications Android</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Apps hybrides (un seul code, deux plateformes)</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Interfaces fluides & design soigné</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Publication sur App Store & Google Play</li>
          </ul>
          <a href="#contact" class="btn-primary" style="margin-top:28px;display:inline-flex;">Discuter de votre
            projet</a>
        </div>
        <div class="service-row-img">
          <img src="images/mobile-dev.png" alt="Développement Applications Mobiles" width="800" height="800" loading="lazy" decoding="async" />
        </div>
      </div>

      <!-- Service 3 -->
      <div class="service-row reveal" style="--st:#8b5cf6">
        <div class="service-row-text">
          <div class="sr-tag" style="--st:#8b5cf6">Hébergement & Déploiement</div>
          <h3>Votre site en ligne, rapide et sécurisé</h3>
          <p>Un bon site mérite un hébergement à la hauteur. Nous gérons tout : <strong>mise en ligne, nom de domaine,
              certificat SSL, sauvegardes automatiques et mises à jour</strong>.</p>
          <ul class="service-list">
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Hébergement cloud haute performance</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Certificat SSL gratuit (cadenas HTTPS)</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Sauvegardes quotidiennes automatiques</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Surveillance 24/7 & alertes instantanées</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg> Scalabilité selon votre croissance</li>
          </ul>
          <a href="#contact" class="btn-primary" style="margin-top:28px;display:inline-flex;">Héberger mon projet</a>
        </div>
        <div class="service-row-img">
          <img src="images/hosting-cloud.png" alt="Hébergement Cloud Sécurisé" width="800" height="800" loading="lazy" decoding="async" />
        </div>
      </div>
    </section>

    <!-- ===== PROCESSUS ===== -->
    <section id="processus" class="section-process">
      <div class="section-header reveal">
        <span class="section-tag">Notre Processus</span>
        <h2>Comment nous travaillons <span class="gradient-text">avec vous</span></h2>
        <p>Un processus clair et transparent pour que vous sachiez toujours où en est votre projet.</p>
      </div>

      <div class="process-layout">
        <div class="process-steps">
          <div class="process-step reveal" data-delay="0">
            <div class="ps-num">01</div>
            <div class="ps-body">
              <h4>Découverte & Analyse</h4>
              <p>On commence par comprendre votre activité, vos objectifs et vos clients cibles. Un cahier des charges
                clair est établi avant de toucher à une seule ligne de code.</p>
            </div>
          </div>
          <div class="process-step reveal" data-delay="100">
            <div class="ps-num">02</div>
            <div class="ps-body">
              <h4>Design & Maquettes</h4>
              <p>Nous concevons les maquettes visuelles de votre projet. Vous validez l'apparence avant le
                développement. Zéro surprise en fin de projet.</p>
            </div>
          </div>
          <div class="process-step reveal" data-delay="200">
            <div class="ps-num">03</div>
            <div class="ps-body">
              <h4>Développement</h4>
              <p>Notre équipe développe votre projet côté serveur et interface avec PHP, HTML/CSS et bases de données —
                architectures claires, code propre et maintenable.</p>
            </div>
          </div>
          <div class="process-step reveal" data-delay="300">
            <div class="ps-num">04</div>
            <div class="ps-body">
              <h4>Tests & Livraison</h4>
              <p>Avant la mise en ligne, chaque fonctionnalité est testée rigoureusement. Puis nous déployons votre
                projet et vous formons à son utilisation.</p>
            </div>
          </div>
          <div class="process-step reveal" data-delay="400">
            <div class="ps-num">05</div>
            <div class="ps-body">
              <h4>Support & Évolution</h4>
              <p>Notre relation ne s'arrête pas à la livraison. Nous restons disponibles pour la maintenance, les mises
                à jour et l'évolution de votre projet.</p>
            </div>
          </div>
        </div>
        <div class="process-img reveal" data-delay="200">
          <img src="images/process-workflow.png" alt="Notre processus de travail" width="800" height="800" loading="lazy" decoding="async" />
          <div class="process-img-badge">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <div>
              <strong>Délai moyen</strong>
              <span>2 à 8 semaines selon le projet</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== POURQUOI NOUS ===== -->
    <section id="pourquoi" class="section-why">
      <div class="why-inner">
        <div class="why-text reveal">
          <span class="section-tag">Pourquoi Goo-Bridge ?</span>
          <h2>Un partenaire qui comprend <span class="gradient-text">vos enjeux</span></h2>
          <p>Nous ne sommes pas juste des développeurs. Nous sommes des partenaires qui pensent à la croissance de votre
            business à chaque décision technique.</p>
          <div class="why-quote">
            <p>"Chaque projet est unique. Nous prenons le temps de comprendre votre secteur avant d'écrire la moindre
              ligne de code."</p>
            <span>— L'équipe Goo-Bridge</span>
          </div>
          <a href="#contact" class="btn-primary" style="margin-top:32px;display:inline-flex;">Démarrer un projet</a>
        </div>
        <div class="why-features">
          <div class="feature-row reveal" data-delay="0">
            <div class="feature-icon-box" style="--fc:#16a34a">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              </svg>
            </div>
            <div>
              <h4>Expertise reconnue</h4>
              <p>5 ans d'expérience en développement web & mobile, plus de 100 projets livrés avec succès.</p>
            </div>
          </div>
          <div class="feature-row reveal" data-delay="80">
            <div class="feature-icon-box" style="--fc:#3b82f6">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
              </svg>
            </div>
            <div>
              <h4>Délais respectés</h4>
              <p>Nous livrons dans les temps convenus. Un planning détaillé est partagé dès le début du projet.</p>
            </div>
          </div>
          <div class="feature-row reveal" data-delay="160">
            <div class="feature-icon-box" style="--fc:#f59e0b">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polygon
                  points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
              </svg>
            </div>
            <div>
              <h4>Qualité irréprochable</h4>
              <p>Code propre, site performant, design soigné. Nous ne livrons que ce dont nous sommes fiers.</p>
            </div>
          </div>
          <div class="feature-row reveal" data-delay="240">
            <div class="feature-icon-box" style="--fc:#8b5cf6">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
            </div>
            <div>
              <h4>Communication transparente</h4>
              <p>Vous êtes informé à chaque étape. Accès à un espace collaboratif et réunions régulières.</p>
            </div>
          </div>
          <div class="feature-row reveal" data-delay="320">
            <div class="feature-icon-box" style="--fc:#16a34a">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
              </svg>
            </div>
            <div>
              <h4>Suivi post-livraison</h4>
              <p>Maintenance, mises à jour de sécurité, évolutions — nous restons votre partenaire dans la durée.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="why-commitments reveal">
        <div class="why-commitments__head">
          <span class="section-tag">Nos engagements</span>
          <h3>Ce sur quoi vous pouvez <span class="gradient-text">compter</span></h3>
        </div>
        <ul class="why-commitments__grid" role="list">
          <li class="why-commitment" style="--cc:#16a34a">
            <span class="why-commitment__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="9" y1="15" x2="15" y2="15" />
                <line x1="9" y1="11" x2="15" y2="11" />
              </svg>
            </span>
            <div class="why-commitment__body">
              <strong>Devis transparent</strong>
              <span>Tarification détaillée avant le moindre engagement. Aucun frais caché.</span>
            </div>
          </li>
          <li class="why-commitment" style="--cc:#3b82f6">
            <span class="why-commitment__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="16 18 22 12 16 6" />
                <polyline points="8 6 2 12 8 18" />
              </svg>
            </span>
            <div class="why-commitment__body">
              <strong>Code source remis</strong>
              <span>Vous êtes propriétaire de votre code et de vos données, sans dépendance.</span>
            </div>
          </li>
          <li class="why-commitment" style="--cc:#f59e0b">
            <span class="why-commitment__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L15.09 8.26 22 9.27l-5 4.87 1.18 6.88L12 17.77 5.82 21.02 7 14.14 2 9.27l6.91-1.01z" />
              </svg>
            </span>
            <div class="why-commitment__body">
              <strong>Qualité garantie</strong>
              <span>Code testé, documenté et conforme aux meilleurs standards web.</span>
            </div>
          </li>
          <li class="why-commitment" style="--cc:#8b5cf6">
            <span class="why-commitment__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
              </svg>
            </span>
            <div class="why-commitment__body">
              <strong>Disponibilité réelle</strong>
              <span>Une équipe joignable, réactive et à votre écoute à chaque étape.</span>
            </div>
          </li>
        </ul>
      </div>
    </section>

    <!-- ===== TECH STACK ===== -->
    <section id="stack" class="section-stack">
      <div class="section-header reveal">
        <span class="section-tag">Notre Stack Technique</span>
        <h2>Les technologies qui <span class="gradient-text">alimentent nos projets</span></h2>
        <p>Nous maîtrisons les langages et frameworks les plus performants du marché.</p>
      </div>
      <div class="marquee-wrap reveal" aria-hidden="true">
        <div class="marquee-track">
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 630 630">
              <rect width="630" height="630" fill="#f7df1e" />
              <path
                d="M423.2 492.19c12.69 20.72 29.2 35.95 58.4 35.95 24.53 0 40.2-12.26 40.2-29.2 0-20.28-16.1-27.49-43.1-39.3l-14.8-6.35c-42.72-18.2-71.1-41-71.1-89.2 0-44.4 33.83-78.2 86.7-78.2 37.64 0 64.7 13.1 84.2 47.4l-46.1 29.6c-10.15-18.2-21.1-25.37-38.1-25.37-17.34 0-28.33 11-28.33 25.37 0 17.76 11 24.95 36.4 35.95l14.8 6.34c50.3 21.57 78.7 43.56 78.7 92.91 0 53.3-41.87 82.5-98.1 82.5-54.98 0-90.5-26.2-107.88-60.54zm-209.13 5.13c9.3 16.5 17.76 30.45 37.99 30.45 19.42 0 31.66-7.61 31.66-37.2v-201.3h59.2v202.1c0 61.3-35.94 89.2-88.4 89.2-47.4 0-74.85-24.53-88.81-54.075z" />
            </svg>JavaScript</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <ellipse cx="64" cy="32" rx="64" ry="32" fill="#8892BE" />
              <path fill="#fff"
                d="M26.69 28.6h8.86l1.66-8.58H29.1L31 11.17h-8.38l-5.2 26.85H26.28zM52 11.17H43.6l-1.64 8.58h8.39L48.73 28h-8.39l-1.64 8.58h8.39l-1.64 8.58h8.38l1.64-8.58h8.39l-1.64 8.58h8.38l4.93-25.41h-8.38l-.82 4.29h-8.39z" />
            </svg>PHP</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <circle cx="64" cy="55" r="30" fill="#306998" />
              <circle cx="72" cy="73" r="30" fill="#FFD43B" />
            </svg>Python</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <g fill="#61DAFB">
                <circle cx="64" cy="64" r="11.4" />
                <ellipse cx="64" cy="64" rx="60" ry="20" fill="none" stroke="#61DAFB" stroke-width="5" />
                <ellipse cx="64" cy="64" rx="60" ry="20" fill="none" stroke="#61DAFB" stroke-width="5"
                  transform="rotate(60 64 64)" />
                <ellipse cx="64" cy="64" rx="60" ry="20" fill="none" stroke="#61DAFB" stroke-width="5"
                  transform="rotate(120 64 64)" />
              </g>
            </svg>React</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <path
                d="M64 0C28.7 0 0 28.7 0 64s28.7 64 64 64c11.2 0 21.7-2.9 30.8-7.9L48.4 55.3v36.6H35.5V40.7h22.7l40.5 62.5c12.4-9.9 20.3-25.1 20.3-42.2 0-35.3-28.7-64-64-64z" />
            </svg>Next.js</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <path fill="#42b883" d="M78.8 10L64 35.6 49.2 10H0l64 110 64-110z" />
              <path fill="#35495e" d="M78.8 10L64 35.6 49.2 10H25.6L64 76 102.4 10z" />
            </svg>Vue.js</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <rect width="128" height="128" rx="20" fill="#FF2D20" />
              <path fill="#fff" d="M64 14L20 40v48l44 26 44-26V40z" opacity=".3" />
              <path fill="#fff" d="M64 24L28 46v36l36 21 36-21V46z" />
            </svg>Laravel</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <rect width="128" height="128" rx="20" fill="#0C4B33" />
              <path fill="#fff" d="M44 44h12v40H44zm16 0h12l12 20-12 20H60l12-20z" />
            </svg>Django</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <rect width="128" height="128" rx="20" fill="#059669" />
              <path fill="#fff" d="M70 16L30 72h28l-8 40 48-60H68z" />
            </svg>FastAPI</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <rect width="128" height="128" rx="20" fill="#83CD29" />
              <path fill="#333" d="M64 10L10 42v44l54 32 54-32V42z" opacity=".2" />
              <path fill="#fff" d="M64 20L18 47v34l46 27 46-27V47z" />
            </svg>Node.js</div>
          <!-- duplicate for loop -->
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 630 630">
              <rect width="630" height="630" fill="#f7df1e" />
              <path
                d="M423.2 492.19c12.69 20.72 29.2 35.95 58.4 35.95 24.53 0 40.2-12.26 40.2-29.2 0-20.28-16.1-27.49-43.1-39.3l-14.8-6.35c-42.72-18.2-71.1-41-71.1-89.2 0-44.4 33.83-78.2 86.7-78.2 37.64 0 64.7 13.1 84.2 47.4l-46.1 29.6c-10.15-18.2-21.1-25.37-38.1-25.37-17.34 0-28.33 11-28.33 25.37 0 17.76 11 24.95 36.4 35.95l14.8 6.34c50.3 21.57 78.7 43.56 78.7 92.91 0 53.3-41.87 82.5-98.1 82.5-54.98 0-90.5-26.2-107.88-60.54zm-209.13 5.13c9.3 16.5 17.76 30.45 37.99 30.45 19.42 0 31.66-7.61 31.66-37.2v-201.3h59.2v202.1c0 61.3-35.94 89.2-88.4 89.2-47.4 0-74.85-24.53-88.81-54.075z" />
            </svg>JavaScript</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <ellipse cx="64" cy="32" rx="64" ry="32" fill="#8892BE" />
              <path fill="#fff"
                d="M26.69 28.6h8.86l1.66-8.58H29.1L31 11.17h-8.38l-5.2 26.85H26.28zM52 11.17H43.6l-1.64 8.58h8.39L48.73 28h-8.39l-1.64 8.58h8.39l-1.64 8.58h8.38l1.64-8.58h8.39l-1.64 8.58h8.38l4.93-25.41h-8.38l-.82 4.29h-8.39z" />
            </svg>PHP</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <circle cx="64" cy="55" r="30" fill="#306998" />
              <circle cx="72" cy="73" r="30" fill="#FFD43B" />
            </svg>Python</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <g fill="#61DAFB">
                <circle cx="64" cy="64" r="11.4" />
                <ellipse cx="64" cy="64" rx="60" ry="20" fill="none" stroke="#61DAFB" stroke-width="5" />
                <ellipse cx="64" cy="64" rx="60" ry="20" fill="none" stroke="#61DAFB" stroke-width="5"
                  transform="rotate(60 64 64)" />
                <ellipse cx="64" cy="64" rx="60" ry="20" fill="none" stroke="#61DAFB" stroke-width="5"
                  transform="rotate(120 64 64)" />
              </g>
            </svg>React</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <path
                d="M64 0C28.7 0 0 28.7 0 64s28.7 64 64 64c11.2 0 21.7-2.9 30.8-7.9L48.4 55.3v36.6H35.5V40.7h22.7l40.5 62.5c12.4-9.9 20.3-25.1 20.3-42.2 0-35.3-28.7-64-64-64z" />
            </svg>Next.js</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <path fill="#42b883" d="M78.8 10L64 35.6 49.2 10H0l64 110 64-110z" />
              <path fill="#35495e" d="M78.8 10L64 35.6 49.2 10H25.6L64 76 102.4 10z" />
            </svg>Vue.js</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <rect width="128" height="128" rx="20" fill="#FF2D20" />
              <path fill="#fff" d="M64 14L20 40v48l44 26 44-26V40z" opacity=".3" />
              <path fill="#fff" d="M64 24L28 46v36l36 21 36-21V46z" />
            </svg>Laravel</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <rect width="128" height="128" rx="20" fill="#0C4B33" />
              <path fill="#fff" d="M44 44h12v40H44zm16 0h12l12 20-12 20H60l12-20z" />
            </svg>Django</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <rect width="128" height="128" rx="20" fill="#059669" />
              <path fill="#fff" d="M70 16L30 72h28l-8 40 48-60H68z" />
            </svg>FastAPI</div>
          <div class="marquee-item"><svg width="36" height="36" viewBox="0 0 128 128">
              <rect width="128" height="128" rx="20" fill="#83CD29" />
              <path fill="#fff" d="M64 20L18 47v34l46 27 46-27V47z" />
            </svg>Node.js</div>
        </div>
      </div>
      <div class="stack-cards">
        <div class="stack-card reveal" data-delay="0" style="--lc:#f7df1e">
          <div class="stack-card-header">
            <div class="stack-lang-icon" style="background:rgba(247,223,30,.12);border-color:rgba(247,223,30,.3)"><svg
                width="28" height="28" viewBox="0 0 630 630">
                <rect width="630" height="630" fill="#f7df1e" rx="40" />
                <path
                  d="M423.2 492.19c12.69 20.72 29.2 35.95 58.4 35.95 24.53 0 40.2-12.26 40.2-29.2 0-20.28-16.1-27.49-43.1-39.3l-14.8-6.35c-42.72-18.2-71.1-41-71.1-89.2 0-44.4 33.83-78.2 86.7-78.2 37.64 0 64.7 13.1 84.2 47.4l-46.1 29.6c-10.15-18.2-21.1-25.37-38.1-25.37-17.34 0-28.33 11-28.33 25.37 0 17.76 11 24.95 36.4 35.95l14.8 6.34c50.3 21.57 78.7 43.56 78.7 92.91 0 53.3-41.87 82.5-98.1 82.5-54.98 0-90.5-26.2-107.88-60.54zm-209.13 5.13c9.3 16.5 17.76 30.45 37.99 30.45 19.42 0 31.66-7.61 31.66-37.2v-201.3h59.2v202.1c0 61.3-35.94 89.2-88.4 89.2-47.4 0-74.85-24.53-88.81-54.075z" />
              </svg></div>
            <div>
              <h3>JavaScript</h3>
              <p>Langage front &amp; back-end</p>
            </div>
          </div>
          <div class="stack-frameworks">
            <span class="fw-chip" style="--fw:#61DAFB">React</span>
            <span class="fw-chip" style="--fw:#42b883">Vue.js</span>
            <span class="fw-chip" style="--fw:#000">Next.js</span>
            <span class="fw-chip" style="--fw:#83CD29">Node.js</span>
          </div>
        </div>
        <div class="stack-card reveal" data-delay="120" style="--lc:#8892BE">
          <div class="stack-card-header">
            <div class="stack-lang-icon" style="background:rgba(136,146,190,.12);border-color:rgba(136,146,190,.3)"><svg
                width="28" height="28" viewBox="0 0 128 128">
                <ellipse cx="64" cy="40" rx="64" ry="40" fill="#8892BE" />
                <path fill="#fff"
                  d="M26 35h9l2-9H29l2-9h-8l-5 27h8zm25-18h-8l-2 9h8l-2 8h-8l-2 9h8l-2 8h8l2-9h8l-2 9h8l5-26h-8l-1 4h-8zm34 9h8l2-9h-8l2-9h-8l-5 27h8z" />
              </svg></div>
            <div>
              <h3>PHP</h3>
              <p>Langage serveur web</p>
            </div>
          </div>
          <div class="stack-frameworks">
            <span class="fw-chip" style="--fw:#FF2D20">Laravel</span>
            <span class="fw-chip" style="--fw:#6C78AF">Symfony</span>
          </div>
        </div>
        <div class="stack-card reveal" data-delay="240" style="--lc:#3776AB">
          <div class="stack-card-header">
            <div class="stack-lang-icon" style="background:rgba(55,118,171,.12);border-color:rgba(55,118,171,.3)"><svg
                width="28" height="28" viewBox="0 0 128 128">
                <circle cx="55" cy="50" r="32" fill="#306998" />
                <circle cx="73" cy="78" r="32" fill="#FFD43B" />
              </svg></div>
            <div>
              <h3>Python</h3>
              <p>Langage polyvalent &amp; IA</p>
            </div>
          </div>
          <div class="stack-frameworks">
            <span class="fw-chip" style="--fw:#0C4B33">Django</span>
            <span class="fw-chip" style="--fw:#059669">FastAPI</span>
            <span class="fw-chip" style="--fw:#555">Flask</span>
          </div>
        </div>
      </div>

      <!-- Bases de données -->
      <div class="db-section reveal" data-delay="100">
        <div class="db-header">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2">
            <ellipse cx="12" cy="5" rx="9" ry="3" />
            <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
            <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" />
          </svg>
          <span>Bases de données maîtrisées</span>
        </div>
        <div class="db-cards">

          <div class="db-card reveal" data-delay="0" style="--dc:#336791">
            <div class="db-logo" style="background:rgba(51,103,145,.1);border-color:rgba(51,103,145,.3)">
              <svg width="30" height="30" viewBox="0 0 128 128">
                <circle cx="64" cy="64" r="60" fill="#336791" />
                <path fill="#fff" d="M44 44h8v24h-8zm28 0h8v24h-8zM52 56h24v6H52z" />
                <ellipse cx="64" cy="40" rx="20" ry="6" fill="#fff" opacity=".5" />
                <path fill="#fff" d="M44 84h40v6H44z" opacity=".4" />
              </svg>
            </div>
            <div class="db-info">
              <strong>PostgreSQL</strong>
              <span>Base relationnelle avancée</span>
            </div>
            <div class="db-chips"><span>ACID</span><span>JSON</span><span>Open Source</span></div>
          </div>

          <div class="db-card reveal" data-delay="80" style="--dc:#00758F">
            <div class="db-logo" style="background:rgba(0,117,143,.1);border-color:rgba(0,117,143,.3)">
              <svg width="30" height="30" viewBox="0 0 128 128">
                <circle cx="64" cy="64" r="60" fill="#00758F" />
                <path fill="#fff" d="M30 45h10v25h16V45h10v38H30zm42 0h10l8 16 8-16h10v38H98V65l-8 16-8-16v18H72z" />
              </svg>
            </div>
            <div class="db-info">
              <strong>MySQL</strong>
              <span>Base relationnelle populaire</span>
            </div>
            <div class="db-chips"><span>Rapide</span><span>Fiable</span><span>Scalable</span></div>
          </div>

          <div class="db-card reveal" data-delay="160" style="--dc:#4DB33D">
            <div class="db-logo" style="background:rgba(77,179,61,.1);border-color:rgba(77,179,61,.3)">
              <svg width="30" height="30" viewBox="0 0 128 128">
                <path fill="#4DB33D"
                  d="M64 8C38 8 17 29 17 55c0 20 12 37.5 30 45v8l4 12 4-12V100c18-7.5 30-25 30-45C85 29 90 8 64 8z" />
                <path fill="#fff" d="M64 20v68c-14-7-23-21-23-37 0-18 10.4-32.3 23-31z" />
              </svg>
            </div>
            <div class="db-info">
              <strong>MongoDB</strong>
              <span>Base NoSQL orientée documents</span>
            </div>
            <div class="db-chips"><span>NoSQL</span><span>Flexible</span><span>Documents</span></div>
          </div>

          <div class="db-card reveal" data-delay="240" style="--dc:#e47911">
            <div class="db-logo" style="background:rgba(228,121,17,.1);border-color:rgba(228,121,17,.3)">
              <svg width="30" height="30" viewBox="0 0 128 128">
                <rect width="128" height="128" rx="20" fill="#e47911" />
                <ellipse cx="64" cy="36" rx="36" ry="14" fill="#fff" opacity=".9" />
                <path fill="none" stroke="#fff" stroke-width="5" opacity=".9"
                  d="M28 36v20c0 7.7 16.1 14 36 14s36-6.3 36-14V36" />
                <path fill="none" stroke="#fff" stroke-width="5" opacity=".7"
                  d="M28 56v20c0 7.7 16.1 14 36 14s36-6.3 36-14V56" />
                <path fill="none" stroke="#fff" stroke-width="5" opacity=".5"
                  d="M28 76v16c0 7.7 16.1 14 36 14s36-6.3 36-14V76" />
              </svg>
            </div>
            <div class="db-info">
              <strong>SQL Standard</strong>
              <span>Requêtes relationnelles</span>
            </div>
            <div class="db-chips"><span>Jointures</span><span>Transactions</span><span>Vues</span></div>
          </div>

        </div>
      </div>
    </section>


    <section id="realisations" class="section-realisations">
      <div class="section-header reveal">
        <span class="section-tag">Nos Réalisations</span>
        <h2>Des projets livrés, <span class="gradient-text">des clients satisfaits</span></h2>
        <p>Ces plateformes tournent en production aujourd'hui. Chacune a été conçue, développée et hébergée par
          Goo-Bridge.</p>
      </div>
      <div class="portfolio-grid">
        <article class="portfolio-card reveal" data-delay="0">
          <div class="pc-img-wrap"><img src="images/realisation-sugar-paper.jpg" alt="Sugar Paper" width="1100" height="613" loading="lazy" decoding="async" />
            <div class="pc-overlay"><a href="https://sugar-paper.com/" target="_blank" rel="noopener"
                class="pc-visit-btn">↗ Visiter le site</a></div>
          </div>
          <div class="pc-body">
            <div class="pc-tags"><span class="pc-tag">E-commerce</span><span class="pc-tag">Pâtisserie</span></div>
            <h3>Sugar Paper</h3>
            <p>Boutique en ligne spécialisée en décoration de gâteaux et fournitures pâtissières.</p><a
              href="https://sugar-paper.com/" target="_blank" rel="noopener" class="pc-link">sugar-paper.com ↗</a>
          </div>
        </article>
        <article class="portfolio-card reveal" data-delay="100">
          <div class="pc-img-wrap"><img src="images/realisation-aria-edu.jpg" alt="Aria Édu" width="1100" height="613" loading="lazy" decoding="async" />
            <div class="pc-overlay"><a href="https://aria-edu.com/" target="_blank" rel="noopener"
                class="pc-visit-btn">↗ Visiter le site</a></div>
          </div>
          <div class="pc-body">
            <div class="pc-tags"><span class="pc-tag">App Web</span><span class="pc-tag">Éducation</span></div>
            <h3>Aria Édu</h3>
            <p>Plateforme éducative avec QR Code, espace personnel élève, parent et enseignant.</p><a
              href="https://aria-edu.com/" target="_blank" rel="noopener" class="pc-link">aria-edu.com ↗</a>
          </div>
        </article>
        <article class="portfolio-card reveal" data-delay="200">
          <div class="pc-img-wrap"><img src="images/realisation-fouta-poids.jpg" alt="Fouta Poids Lourds" width="1100" height="613" loading="lazy" decoding="async" />
            <div class="pc-overlay"><a href="https://e.foutapoidslourds.com/" target="_blank" rel="noopener"
                class="pc-visit-btn">↗ Visiter le site</a></div>
          </div>
          <div class="pc-body">
            <div class="pc-tags"><span class="pc-tag">E-commerce</span><span class="pc-tag">Poids Lourds</span></div>
            <h3>Fouta Poids Lourds</h3>
            <p>Boutique de pièces détachées pour véhicules poids lourds avec catalogue complet.</p><a
              href="https://e.foutapoidslourds.com/" target="_blank" rel="noopener" class="pc-link">foutapoidslourds.com
              ↗</a>
          </div>
        </article>
        <article class="portfolio-card reveal" data-delay="300">
          <div class="pc-img-wrap"><img src="images/realisation-aria-education.jpg" alt="Aria Education" width="1100" height="613" loading="lazy" decoding="async" />
            <div class="pc-overlay"><a href="https://aria-education.com/" target="_blank" rel="noopener"
                class="pc-visit-btn">↗ Visiter le site</a></div>
          </div>
          <div class="pc-body">
            <div class="pc-tags"><span class="pc-tag">Plateforme</span><span class="pc-tag">Gestion Scolaire</span>
            </div>
            <h3>Aria Education</h3>
            <p>Gestion scolaire complète : présences, bulletins, tableau de bord, notifications.</p><a
              href="https://aria-education.com/" target="_blank" rel="noopener" class="pc-link">aria-education.com ↗</a>
          </div>
        </article>
        <article class="portfolio-card reveal" data-delay="400">
          <div class="pc-img-wrap"><img src="images/realisation-colobanes.png" alt="Colobanes — marché en ligne" width="1024" height="495" loading="lazy" decoding="async" />
            <div class="pc-overlay"><a href="https://colobanes.com/" target="_blank" rel="noopener"
                class="pc-visit-btn">↗ Visiter le site</a></div>
          </div>
          <div class="pc-body">
            <div class="pc-tags"><span class="pc-tag">E-commerce</span><span class="pc-tag">Marketplace</span></div>
            <h3>Colobanes</h3>
            <p>Marché en ligne multi-catégories : recherche, vitrines produits, compte client et parcours d’achat.</p><a href="https://colobanes.com/"
              target="_blank" rel="noopener" class="pc-link">colobanes.com ↗</a>
          </div>
        </article>
        <article class="portfolio-card reveal" data-delay="500">
          <div class="pc-img-wrap"><img src="images/realisation-tresorafricain.png" alt="Trésor Africain — boutique en ligne" width="1024" height="502" loading="lazy" decoding="async" />
            <div class="pc-overlay"><a href="https://tresorafricain.com/" target="_blank" rel="noopener"
                class="pc-visit-btn">↗ Visiter le site</a></div>
          </div>
          <div class="pc-body">
            <div class="pc-tags"><span class="pc-tag">E-commerce</span><span class="pc-tag">Épicerie africaine</span></div>
            <h3>Trésor Africain</h3>
            <p>Boutique en ligne : catalogue produits, recherche, compte client et parcours d’achat.</p><a
              href="https://tresorafricain.com/" target="_blank" rel="noopener" class="pc-link">tresorafricain.com ↗</a>
          </div>
        </article>
      </div>
    </section>

    <!-- ===== CONTACT ===== -->
    <section id="contact" class="section-contact">
      <div class="contact-card reveal">
        <div class="contact-left">
          <span class="section-tag">Contact</span>
          <h2>Parlons de votre projet</h2>
          <p class="contact-lead">Une idée, un besoin ? Décrivez votre projet : nous revenons vers vous sous <strong>24 à 72&nbsp;h</strong> (jours ouvrés) avec des précisions et la suite des étapes.</p>
          <div class="contact-email-highlight">
            <span class="contact-email-highlight__label">Écrivez-nous</span>
            <a href="mailto:contact@goo-bridge.com" class="contact-email-highlight__link">contact@goo-bridge.com</a>
          </div>
          <div class="contact-guarantees contact-guarantees--chips">
            <span class="contact-chip"><span class="contact-chip__dot" aria-hidden="true"></span> Réponse 24–72&nbsp;h</span>
            <span class="contact-chip"><span class="contact-chip__dot" aria-hidden="true"></span> Devis gratuit</span>
            <span class="contact-chip"><span class="contact-chip__dot" aria-hidden="true"></span> Données confidentielles</span>
          </div>
        </div>
        <div class="contact-form-panel">
          <div class="contact-form-panel__head">
            <h3 class="contact-form-panel__title">Envoyer une demande</h3>
            <p class="contact-form-panel__lead">Remplissez le formulaire — vous recevrez un accusé de réception par email.</p>
          </div>
          <form class="contact-form" method="post" action="" accept-charset="UTF-8">
            <?php if ($form_error !== ''): ?>
            <p class="form-error"><?= htmlspecialchars($form_error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if ($form_success): ?>
            <p class="form-success" id="contact-form-success" role="status">✓ Message bien envoyé. Vérifiez votre boîte mail : nous vous avons envoyé une confirmation (vérifiez aussi les courriers indésirables).</p>
            <?php endif; ?>
            <div class="form-row">
              <div class="form-field">
                <label for="name">Nom complet</label>
                <input type="text" id="name" name="name" placeholder="Votre nom" autocomplete="name" required />
              </div>
              <div class="form-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="vous@exemple.com" autocomplete="email" required />
              </div>
            </div>
            <div class="form-row">
              <div class="form-field">
                <label for="phone">Téléphone <span class="form-field__optional">(optionnel)</span></label>
                <input type="tel" id="phone" name="phone" placeholder="+33 6 12 34 56 78" autocomplete="tel" inputmode="tel" maxlength="32" />
              </div>
              <div class="form-field">
                <label for="service">Type de projet</label>
                <select id="service" name="service">
                  <option value="">Sélectionnez une option…</option>
                  <option>Site vitrine</option>
                  <option>E-commerce</option>
                  <option>Application mobile</option>
                  <option>Plateforme web</option>
                  <option>Hébergement</option>
                  <option>Hébergement Android</option>
                  <option>Hébergement Apple</option>
                  <option>ERP (gestionnaire de stock)</option>
                  <option>Autre</option>
                </select>
              </div>
            </div>
            <div class="form-field">
              <label for="message">Votre message</label>
              <textarea id="message" name="message" placeholder="Contexte, objectifs, délais souhaités, budget approximatif…" required></textarea>
            </div>
            <button type="submit" class="btn-primary btn-submit contact-submit-btn">
              <span>Envoyer ma demande</span>
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <line x1="22" y1="2" x2="11" y2="13" />
                <polygon points="22 2 15 22 11 13 2 9 22 2" />
              </svg>
            </button>
          </form>
        </div>
      </div>
    </section>

  </main>

  <footer>
    <div class="footer-inner">
      <div class="footer-brand">
        <a href="#accueil" class="nav-logo">
          <svg width="20" height="20" viewBox="0 0 28 28" fill="none">
            <path d="M14 2L3 8V20L14 26L25 20V8L14 2Z" stroke="#16a34a" stroke-width="2" stroke-linejoin="round" />
            <path d="M3 8L14 14L25 8" stroke="#16a34a" stroke-width="2" stroke-linejoin="round" />
            <path d="M14 14V26" stroke="#16a34a" stroke-width="2" />
          </svg>
          <span>Goo<span class="logo-accent">Bridge</span></span>
        </a>
        <p>Connecter vos idées au monde digital.</p>
        <p style="font-size:0.8rem;margin-top:4px;"> </p>
      </div>
      <nav class="footer-links" aria-label="Navigation pied de page">
        <a href="#services">Services</a>
        <a href="#processus">Processus</a>
        <a href="#realisations">Réalisations</a>
        <a href="#stack">Technologies</a>
        <a href="#contact">Contact</a>
      </nav>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2026 Goo-Bridge. Tous droits réservés. · <a href="#contact"
          style="color:var(--green);">contact@goo-bridge.com</a></p>
    </div>
  </footer>

  <script>
  (function () {
    var el = document.getElementById('contact-form-success');
    if (!el) return;

    var hideDelayMs = 30000;
    var fadeMs = 380;

    window.setTimeout(function () {
      el.style.transition = 'opacity ' + (fadeMs / 1000) + 's ease';
      el.style.opacity = '0';
      window.setTimeout(function () {
        el.setAttribute('hidden', '');
        el.style.display = 'none';
        el.removeAttribute('role');
        try {
          var url = new URL(window.location.href);
          url.searchParams.delete('sent');
          var next = url.pathname + url.search + (url.hash || '#contact');
          window.history.replaceState(null, '', next);
        } catch (e) {}
      }, fadeMs);
    }, hideDelayMs);
  })();
  </script>
</body>

</html>