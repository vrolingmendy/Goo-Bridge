/* ============================================================
   MAIN.JS — Goo-Bridge Interactions
   ============================================================ */

// ── Navbar scroll ─────────────────────────────────────────────
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  navbar.classList.toggle('scrolled', window.scrollY > 20);
}, { passive: true });

// ── Mobile menu ───────────────────────────────────────────────
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const mobileNav = document.getElementById('mobileNav');
mobileMenuBtn.addEventListener('click', () => {
  const open = mobileNav.classList.toggle('open');
  mobileMenuBtn.setAttribute('aria-expanded', String(open));
});
mobileNav.querySelectorAll('a').forEach(link => {
  link.addEventListener('click', () => {
    mobileNav.classList.remove('open');
    mobileMenuBtn.setAttribute('aria-expanded', 'false');
  });
});

// ── Scroll reveal ─────────────────────────────────────────────
const revealEls = document.querySelectorAll('.js-reveal');
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (!entry.isIntersecting) return;
    const delay = parseInt(entry.target.dataset.delay || '0', 10);
    setTimeout(() => entry.target.classList.add('visible'), delay);
    revealObserver.unobserve(entry.target);
  });
}, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
revealEls.forEach(el => revealObserver.observe(el));

// ── Active nav link ───────────────────────────────────────────
const sections = document.querySelectorAll('section[id]');
const navLinksAll = document.querySelectorAll('.nav-links a, .mobile-nav a');
const sectionObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const id = entry.target.getAttribute('id');
      navLinksAll.forEach(a => {
        a.classList.toggle('active', a.getAttribute('href') === `#${id}`);
      });
    }
  });
}, { threshold: 0.3 });
sections.forEach(s => sectionObserver.observe(s));

// ── Counter animation ─────────────────────────────────────────
function animateCounter(el) {
  const target = parseInt(el.dataset.count, 10);
  const duration = 1600;
  const start = performance.now();
  const update = (now) => {
    const progress = Math.min((now - start) / duration, 1);
    const ease = 1 - Math.pow(1 - progress, 3);
    el.textContent = Math.floor(ease * target);
    if (progress < 1) requestAnimationFrame(update);
    else el.textContent = target;
  };
  requestAnimationFrame(update);
}
const counterEls = document.querySelectorAll('.stat-num[data-count]');
const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      animateCounter(entry.target);
      counterObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.5 });
counterEls.forEach(el => counterObserver.observe(el));

// ── Contact form ──────────────────────────────────────────────
const form = document.getElementById('contactForm');
const submitBtn = document.getElementById('submitBtn');
const formSuccess = document.getElementById('formSuccess');
form.addEventListener('submit', (e) => {
  e.preventDefault();
  if (!form.name.value.trim() || !form.email.value.trim() || !form.message.value.trim()) return;
  const span = submitBtn.querySelector('span');
  span.textContent = 'Envoi en cours…';
  submitBtn.disabled = true;
  setTimeout(() => {
    submitBtn.hidden = true;
    formSuccess.hidden = false;
    form.reset();
  }, 1400);
});

// ── Animated Globe Background ─────────────────────────────────
const canvas = document.getElementById('globeCanvas');
if (canvas) {
  const ctx = canvas.getContext('2d');
  let width, height;
  let points = [];
  const numPointsTotal = 2400; // Increased points for density
  let rotationY = 0;

  // Use a CORS-friendly map image off Wikipedia
  const mapImg = new Image();
  mapImg.crossOrigin = "anonymous";
  // A tiny, highly contrasted map image
  mapImg.src = "https://upload.wikimedia.org/wikipedia/commons/c/c4/Earthmap1000x500compac.jpg";

  mapImg.onload = () => {
    const mapCanvas = document.createElement('canvas');
    const MW = 120, MH = 60;
    mapCanvas.width = MW;
    mapCanvas.height = MH;
    const mctx = mapCanvas.getContext('2d', { willReadFrequently: true });
    mctx.drawImage(mapImg, 0, 0, MW, MH);
    const imgData = mctx.getImageData(0, 0, MW, MH).data;

    const newPoints = [];
    const phi = Math.PI * (3 - Math.sqrt(5)); // Golden angle

    for (let i = 0; i < numPointsTotal; i++) {
      const y = 1 - (i / (numPointsTotal - 1)) * 2;
      const radius = Math.sqrt(1 - y * y);
      const theta = phi * i;

      const x = Math.cos(theta) * radius;
      const z = Math.sin(theta) * radius;

      // Map 3D coordinate to 2D image coordinate
      const latitude = Math.asin(y);
      const longitude = Math.atan2(z, x);

      // u is 0 to 1, v is 0 to 1
      const u = (longitude + Math.PI) / (2 * Math.PI);
      const v = (Math.PI / 2 - latitude) / Math.PI;

      const px = Math.floor(u * MW);
      const py = Math.floor(v * MH);

      const pixelIndex = (py * MW + px) * 4;
      // In this specific image, land is darker (green/blue usually have some brightness, but it's a topographical map).
      // Wait, let's use a black and white map:
      // "https://upload.wikimedia.org/wikipedia/commons/c/c3/World_map_blank_black.png" -> land is black, ocean is transparent/white.
    }
  };

  // Let's implement a fallback / better way using mathematical rejection
  function initGlobeFallback() {
    points = [];
    const phi = Math.PI * (3 - Math.sqrt(5));
    for (let i = 0; i < 650; i++) {
      const y = 1 - (i / (650 - 1)) * 2;
      const radius = Math.sqrt(1 - y * y);
      const theta = phi * i;
      points.push({ x: Math.cos(theta) * radius, y, z: Math.sin(theta) * radius });
    }
  }

  // Reload map using the proper black and white map
  const bwMap = new Image();
  bwMap.crossOrigin = "anonymous";
  bwMap.src = "https://upload.wikimedia.org/wikipedia/commons/c/c3/World_map_blank_black.png";

  bwMap.onload = () => {
    const mapCanvas = document.createElement('canvas');
    const MW = 150, MH = 75;
    mapCanvas.width = MW;
    mapCanvas.height = MH;
    const mctx = mapCanvas.getContext('2d', { willReadFrequently: true });

    // Fill white (ocean), then draw map (land is black)
    mctx.fillStyle = "#ffffff";
    mctx.fillRect(0, 0, MW, MH);
    mctx.drawImage(bwMap, 0, 0, MW, MH);
    const imgData = mctx.getImageData(0, 0, MW, MH).data;

    const newPoints = [];
    // We sample randomly or uniformly. Fibonacci sphere is best for uniformity.
    const samples = 3500; // We test many points and keep the ones on land
    const phi = Math.PI * (3 - Math.sqrt(5));

    for (let i = 0; i < samples; i++) {
      const y = 1 - (i / (samples - 1)) * 2;
      const radius = Math.sqrt(1 - y * y);
      const theta = phi * i;

      const x = Math.cos(theta) * radius;
      const z = Math.sin(theta) * radius;

      const latitude = Math.asin(y);
      const longitude = Math.atan2(z, x);

      const u = (longitude + Math.PI) / (2 * Math.PI);
      const v = (Math.PI / 2 - latitude) / Math.PI;

      const px = Math.floor(u * MW);
      const py = Math.floor(v * MH);

      const idx = (py * MW + px) * 4;
      const r = imgData[idx];

      // If it's dark, it's land
      if (r < 128) {
        newPoints.push({ x, y, z });
      }
    }
    // Replace points array with mapped continent points
    if (newPoints.length > 0) points = newPoints;
  };

  bwMap.onerror = () => {
    // If Wikipedia blocks CORS or fails, fallback to sphere
    initGlobeFallback();
  };

  function resize() {
    width = canvas.clientWidth;
    height = canvas.clientHeight;
    const dpr = window.devicePixelRatio || 1;
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    ctx.scale(dpr, dpr);
  }

  function render() {
    ctx.clearRect(0, 0, width, height);

    rotationY -= 0.002;
    const rotationX = 0.2;

    const cosX = Math.cos(rotationX);
    const sinX = Math.sin(rotationX);
    const cosY = Math.cos(rotationY);
    const sinY = Math.sin(rotationY);

    const globeRadius = Math.min(width, height) * 0.45;

    for (let i = 0; i < points.length; i++) {
      const p = points[i];

      // Rotate Y
      let x1 = p.x * cosY - p.z * sinY;
      let z1 = p.z * cosY + p.x * sinY;

      // Rotate X
      let y2 = p.y * cosX - z1 * sinX;
      let z2 = z1 * cosX + p.y * sinX;

      const perspective = 3 / (3 + z2);
      const xProjected = (x1 * globeRadius * perspective) + (width / 2);
      const yProjected = (y2 * globeRadius * perspective) + (height / 2);

      const opacity = Math.max(0.1, Math.min(1, (z2 + 0.8) / 1.5));
      const size = perspective * 1.5;

      ctx.beginPath();
      ctx.arc(xProjected, yProjected, size, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(22, 163, 74, ${opacity})`;
      ctx.fill();
    }
    requestAnimationFrame(render);
  }

  window.addEventListener('resize', resize, { passive: true });
  resize();
  initGlobeFallback(); // start with a simple sphere until map loads
  render();
}
