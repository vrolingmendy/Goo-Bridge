<?php

declare(strict_types=1);

/**
 * Globe « pointilliste » en arrière-plan : points océan / terres,
 * rotation continue (canvas + JS). Continents approximatifs par régions.
 */
?>
<div class="hero-globe-canvas-wrap" aria-hidden="true">
  <canvas id="heroGlobeCanvas" class="hero-globe-canvas" width="800" height="800"></canvas>
</div>
<script>
(function () {
  var canvas = document.getElementById('heroGlobeCanvas');
  if (!canvas || !canvas.getContext) return;

  var ctx = canvas.getContext('2d');
  var dpr = Math.min(window.devicePixelRatio || 1, 2);
  var wrap = canvas.parentElement;
  var cssSize = 800;
  var cx;
  var cy;
  var R;

  function resize() {
    if (!wrap) {
      cssSize = 800;
    } else {
      cssSize = Math.max(280, Math.floor(wrap.clientWidth));
    }
    canvas.style.width = cssSize + 'px';
    canvas.style.height = cssSize + 'px';
    canvas.width = Math.round(cssSize * dpr);
    canvas.height = Math.round(cssSize * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    cx = cssSize / 2;
    cy = cssSize / 2;
    R = cssSize * 0.42;
  }

  resize();

  function isLand(lat, lng) {
    lng = ((((lng + 180) % 360) + 360) % 360) - 180;
    function box(latMin, latMax, lngMin, lngMax) {
      return lat >= latMin && lat <= latMax && lng >= lngMin && lng <= lngMax;
    }
    if (box(-55, 72, -169, -28)) {
      if (box(7, 23, -98, -58)) return false;
      if (box(48, 61, -139, -122)) return true;
      return true;
    }
    if (box(-56, -17, -82, -34)) return true;
    if (box(-35, 38, -25, 62)) {
      if (box(35, 38, -10, 36)) return false;
      if (box(36, 39, -6, 10)) return false;
      return true;
    }
    if (box(35, 72, -28, 48)) {
      if (box(36, 47, -8, 38)) return true;
      if (box(41, 82, -180, 180)) return false;
      return true;
    }
    if (box(15, 82, 40, 190)) return true;
    if (box(-12, 25, 95, 155)) return true;
    if (box(-48, -8, 112, 155)) return true;
    if (box(-52, -24, 165, 180)) return true;
    if (box(-52, -24, -180, -175)) return true;
    if (box(-50, -30, 165, 172)) return true;
    if (box(20, 55, -180, -128)) return true;
    return false;
  }

  function spherePoint(i, n) {
    var phi = Math.PI * (3 - Math.sqrt(5));
    var y = 1 - (i / Math.max(1, n - 1)) * 2;
    var r = Math.sqrt(Math.max(0, 1 - y * y));
    var theta = phi * i;
    return { x: Math.cos(theta) * r, y: y, z: Math.sin(theta) * r };
  }

  function rotateY(p, angle) {
    var c = Math.cos(angle);
    var s = Math.sin(angle);
    return {
      x: p.x * c + p.z * s,
      y: p.y,
      z: -p.x * s + p.z * c
    };
  }

  function latLngFromUnit(p) {
    var lat = Math.asin(Math.max(-1, Math.min(1, p.y))) * (180 / Math.PI);
    var lng = Math.atan2(p.z, p.x) * (180 / Math.PI);
    return { lat: lat, lng: lng };
  }

  var n = 680;
  var dots = [];
  for (var i = 0; i < n; i++) {
    var p = spherePoint(i, n);
    var ll = latLngFromUnit(p);
    dots.push({
      bx: p.x,
      by: p.y,
      bz: p.z,
      land: isLand(ll.lat, ll.lng)
    });
  }

  var angle = 0.35;

  function paint() {
    ctx.clearRect(0, 0, cssSize, cssSize);

    ctx.fillStyle = 'rgba(22, 163, 74, 0.09)';
    ctx.beginPath();
    ctx.arc(cx, cy, R + 2, 0, Math.PI * 2);
    ctx.fill();

    var sorted = [];
    for (var j = 0; j < dots.length; j++) {
      var d = dots[j];
      var q = rotateY({ x: d.bx, y: d.by, z: d.bz }, angle);
      var perspective = 3 / (3 + q.z);
      var sx = cx + q.x * perspective * R;
      var sy = cy + q.y * perspective * R;
      var alpha = Math.max(0.08, Math.min(1, (q.z + 0.85) / 1.45));
      sorted.push({
        z: q.z,
        sx: sx,
        sy: sy,
        r: (d.land ? 1.35 : 0.85) * perspective,
        land: d.land,
        alpha: alpha
      });
    }
    sorted.sort(function (a, b) {
      return a.z - b.z;
    });

    for (var k = 0; k < sorted.length; k++) {
      var o = sorted[k];
      if (o.z < -0.92) continue;
      var land = o.land;
      var base = land ? 'rgba(21, 128, 61,' : 'rgba(134, 180, 145,';
      ctx.fillStyle = base + (land ? o.alpha * 0.98 : o.alpha * 0.18) + ')';
      ctx.beginPath();
      ctx.arc(o.sx, o.sy, o.r, 0, Math.PI * 2);
      ctx.fill();
    }

    ctx.strokeStyle = 'rgba(22, 163, 74, 0.14)';
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.arc(cx, cy, R, 0, Math.PI * 2);
    ctx.stroke();
  }

  function loop() {
    angle += 0.0022;
    paint();
    requestAnimationFrame(loop);
  }

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    angle = 0.85;
    paint();
  } else {
    requestAnimationFrame(loop);
  }

  window.addEventListener('resize', function () {
    clearTimeout(window._heroGlobeRt);
    window._heroGlobeRt = setTimeout(function () {
      resize();
      paint();
    }, 100);
  });
})();
</script>
