<?php

declare(strict_types=1);

/**
 * Globe « pointilliste » en arrière-plan : points océan discrets,
 * continents renforcés par une couche dense et lumineuse de points.
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
  }

  function lngDiff(lng, center) {
    return wrapLng(lng - center);
  }

  function inEllipse(lat, lng, shape) {
    var dx = lngDiff(lng, shape.lng) * Math.cos(shape.lat * Math.PI / 180);
    var dy = lat - shape.lat;
    var a = (shape.rotate || 0) * Math.PI / 180;
    var ca = Math.cos(a);
    var sa = Math.sin(a);
    var x = dx * ca + dy * sa;
    var y = -dx * sa + dy * ca;
    return (x * x) / (shape.rx * shape.rx) + (y * y) / (shape.ry * shape.ry) <= 1;
  }

  var landShapes = [
    { lat: 50, lng: -103, rx: 46, ry: 24, rotate: -14 },
    { lat: 39, lng: -78, rx: 27, ry: 18, rotate: 5 },
    { lat: 62, lng: -150, rx: 21, ry: 10, rotate: -18 },
    { lat: 17, lng: -91, rx: 21, ry: 7, rotate: -22 },
    { lat: 73, lng: -42, rx: 18, ry: 11, rotate: -8 },
    { lat: -14, lng: -60, rx: 19, ry: 35, rotate: -10 },
    { lat: -42, lng: -69, rx: 10, ry: 18, rotate: 3 },
    { lat: 51, lng: 8, rx: 25, ry: 13, rotate: 6 },
    { lat: 63, lng: 18, rx: 15, ry: 12, rotate: -12 },
    { lat: 2, lng: 19, rx: 23, ry: 34, rotate: -5 },
    { lat: 12, lng: -6, rx: 15, ry: 18, rotate: -20 },
    { lat: 27, lng: 45, rx: 16, ry: 12, rotate: 12 },
    { lat: 49, lng: 83, rx: 58, ry: 25, rotate: 2 },
    { lat: 31, lng: 76, rx: 22, ry: 16, rotate: -8 },
    { lat: 20, lng: 101, rx: 18, ry: 17, rotate: -18 },
    { lat: 2, lng: 113, rx: 18, ry: 9, rotate: 3 },
    { lat: 37, lng: 138, rx: 8, ry: 18, rotate: -16 },
    { lat: -25, lng: 134, rx: 24, ry: 16, rotate: 2 },
    { lat: -42, lng: 172, rx: 6, ry: 11, rotate: -30 },
    { lat: -20, lng: 47, rx: 5, ry: 12, rotate: -14 }
  ];

  var seaCutouts = [
    { lat: 23, lng: -83, rx: 19, ry: 9, rotate: -10 },
    { lat: 66, lng: -84, rx: 17, ry: 10, rotate: 5 },
    { lat: 43, lng: 18, rx: 18, ry: 6, rotate: 3 },
    { lat: 57, lng: 35, rx: 24, ry: 10, rotate: 2 },
    { lat: 8, lng: 78, rx: 12, ry: 8, rotate: 8 },
    { lat: 12, lng: 122, rx: 16, ry: 8, rotate: -10 },
    { lat: -7, lng: 142, rx: 12, ry: 7, rotate: 0 }
  ];

  function isLand(lat, lng) {
    lng = wrapLng(lng);
    var land = false;
    for (var i = 0; i < landShapes.length; i++) {
      if (inEllipse(lat, lng, landShapes[i])) {
        land = true;
        break;
      }
    }
    if (!land) return false;
    for (var j = 0; j < seaCutouts.length; j++) {
      if (inEllipse(lat, lng, seaCutouts[j])) return false;
    }
    return true;
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

  function latLngToUnit(lat, lng) {
    var lr = lat * Math.PI / 180;
    var gr = lng * Math.PI / 180;
    return {
      x: Math.cos(lr) * Math.cos(gr),
      y: Math.sin(lr),
      z: Math.cos(lr) * Math.sin(gr)
    };
  }

  function projectLatLng(lat, lng) {
    var p = latLngToUnit(lat, lng);
    var q = rotateY(p, angle);
    if (q.z < -0.86) return null;
    var perspective = 3 / (3 + q.z);
    return {
      x: cx + q.x * perspective * R,
      y: cy + q.y * perspective * R,
      z: q.z,
      alpha: Math.max(0.04, Math.min(1, (q.z + 0.85) / 1.42))
    };
  }

  function drawGeoLine(points) {
    var drawing = false;
    ctx.beginPath();
    for (var i = 0; i < points.length; i++) {
      var pt = projectLatLng(points[i].lat, points[i].lng);
      if (!pt) {
        drawing = false;
        continue;
      }
      if (!drawing) {
        ctx.moveTo(pt.x, pt.y);
        drawing = true;
      } else {
        ctx.lineTo(pt.x, pt.y);
      }
    }
    ctx.stroke();
  }

  function isCoast(lat, lng) {
    if (!isLand(lat, lng)) return false;
    var d = 2.4;
    return !isLand(lat + d, lng) || !isLand(lat - d, lng) || !isLand(lat, lng + d) || !isLand(lat, lng - d);
  }

  function seededRandom() {
    seededRandom.seed = (seededRandom.seed * 1664525 + 1013904223) >>> 0;
    return seededRandom.seed / 4294967296;
  }
  seededRandom.seed = 0x47b1d9;

  var n = 520;
  var dots = [];
  for (var i = 0; i < n; i++) {
    var p = spherePoint(i, n);
    var ll = latLngFromUnit(p);
    dots.push({
      bx: p.x,
      by: p.y,
      bz: p.z,
      land: isLand(ll.lat, ll.lng),
      ocean: !isLand(ll.lat, ll.lng)
    });
  }

  for (var latG = -55; latG <= 78; latG += 2.15) {
    var lngStep = Math.max(1.65, 2.15 / Math.max(0.36, Math.cos(latG * Math.PI / 180)));
    for (var lngG = -180; lngG <= 180; lngG += lngStep) {
      var jitterLat = (seededRandom() - 0.5) * 0.85;
      var jitterLng = (seededRandom() - 0.5) * lngStep * 0.72;
      var latE = latG + jitterLat;
      var lngE = lngG + jitterLng;
      if (!isLand(latE, lngE)) continue;
      var coast = isCoast(latE, lngE);
      var u = latLngToUnit(latE, lngE);
      dots.push({
        bx: u.x,
        by: u.y,
        bz: u.z,
        land: true,
        continent: true,
        coast: coast,
        accent: coast || seededRandom() > 0.9
      });
    }
  }

  var angle = 0.35;

  function paint() {
    ctx.clearRect(0, 0, cssSize, cssSize);

    ctx.fillStyle = 'rgba(22, 163, 74, 0.06)';
    ctx.beginPath();
    ctx.arc(cx, cy, R + 3, 0, Math.PI * 2);
    ctx.fill();

    ctx.strokeStyle = 'rgba(34, 197, 94, 0.105)';
    ctx.lineWidth = 0.9;
    for (var latLine = -60; latLine <= 60; latLine += 20) {
      var parallel = [];
      for (var lngP = -180; lngP <= 180; lngP += 4) {
        parallel.push({ lat: latLine, lng: lngP });
      }
      drawGeoLine(parallel);
    }
    for (var lngLine = -180; lngLine < 180; lngLine += 30) {
      var meridian = [];
      for (var latP = -76; latP <= 76; latP += 4) {
        meridian.push({ lat: latP, lng: lngLine });
      }
      drawGeoLine(meridian);
    }

    var sorted = [];
    for (var j = 0; j < dots.length; j++) {
      var d = dots[j];
      var q = rotateY({ x: d.bx, y: d.by, z: d.bz }, angle);
      var perspective = 3 / (3 + q.z);
      var sx = cx + q.x * perspective * R;
      var sy = cy + q.y * perspective * R;
      var alpha = Math.max(0.06, Math.min(1, (q.z + 0.85) / 1.42));
      var land = d.land;
      var continent = !!d.continent;
      var accent = !!d.accent;
      var coast = !!d.coast;
      var radiusMul;
      if (!land) {
        radiusMul = 0.36 + Math.sin(j * 12.9898) * 0.04;
      } else if (coast) {
        radiusMul = 2.28 + (j % 5) * 0.075;
      } else if (accent) {
        radiusMul = 1.88 + (j % 5) * 0.07;
      } else if (continent) {
        radiusMul = 1.24 + (j % 7) * 0.032;
      } else {
        radiusMul = 1.32;
      }
      sorted.push({
        z: q.z,
        sx: sx,
        sy: sy,
        r: radiusMul * perspective,
        land: land,
        continent: continent,
        accent: accent,
        coast: coast,
        alpha: alpha
      });
    }
    sorted.sort(function (a, b) {
      return a.z - b.z;
    });

    for (var h = 0; h < sorted.length; h++) {
      var glow = sorted[h];
      if (!glow.continent || glow.z < -0.25) continue;
      ctx.fillStyle = 'rgba(34, 197, 94,' + (glow.alpha * (glow.coast ? 0.22 : (glow.accent ? 0.14 : 0.07))) + ')';
      ctx.beginPath();
      ctx.arc(glow.sx, glow.sy, glow.r * (glow.coast ? 4.7 : (glow.accent ? 3.8 : 2.7)), 0, Math.PI * 2);
      ctx.fill();
    }

    for (var k = 0; k < sorted.length; k++) {
      var o = sorted[k];
      if (o.z < -0.92) continue;
      var land = o.land;
      var continent = o.continent;
      var aOcean = o.alpha * 0.055;
      var aLand = o.alpha * (o.coast ? 1 : (o.accent ? 0.98 : (continent ? 0.82 : 0.62)));
      var base = land
        ? (o.coast ? 'rgba(187, 247, 208,' : (o.accent ? 'rgba(34, 197, 94,' : (continent ? 'rgba(12, 112, 43,' : 'rgba(18, 122, 52,')))
        : 'rgba(156, 198, 168,';
      ctx.fillStyle = base + (land ? aLand : aOcean) + ')';
      ctx.beginPath();
      ctx.arc(o.sx, o.sy, o.r, 0, Math.PI * 2);
      ctx.fill();
    }

    ctx.strokeStyle = 'rgba(22, 163, 74, 0.2)';
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
