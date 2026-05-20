// Nav.js - Ya no es necesario para la navegación SPA
// Se mantiene vacío para evitar errores de carga o para futuras micro-interacciones del menú
console.log("Sistema de navegación tradicional activo.");

(function () {
  const canvas = document.getElementById("sidebarCanvas");
  if (!canvas) return;
  const ctx = canvas.getContext("2d");

  function resize() {
    canvas.width = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;
  }
  resize();
  window.addEventListener("resize", resize);

  const PARTICLE_COUNT = 28;
  const particles = [];

  function randomBetween(a, b) {
    return a + Math.random() * (b - a);
  }

  // Colores que armonizan con el sidebar verde petróleo + acento vino
  const colors = [
    "rgba(255,255,255,",
    "rgba(181,176,140,",   // beige dorado (tu .nombre-secundario)
    "rgba(180,40,60,",     // vino suave
    "rgba(100,200,180,",   // verde agua
  ];

  for (let i = 0; i < PARTICLE_COUNT; i++) {
    const color = colors[Math.floor(Math.random() * colors.length)];
    particles.push({
      x: randomBetween(0, canvas.width),
      y: randomBetween(0, canvas.height),
      r: randomBetween(1, 3.5),
      alpha: randomBetween(0.08, 0.35),
      alphaDir: Math.random() > 0.5 ? 1 : -1,
      alphaSpeed: randomBetween(0.003, 0.008),
      vx: randomBetween(-0.25, 0.25),
      vy: randomBetween(-0.4, -0.1),   // suben lentamente
      color,
    });
  }

  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    particles.forEach((p) => {
      // Pulso de opacidad (fade in/out)
      p.alpha += p.alphaSpeed * p.alphaDir;
      if (p.alpha >= 0.38 || p.alpha <= 0.05) p.alphaDir *= -1;

      // Movimiento
      p.x += p.vx;
      p.y += p.vy;

      // Rebote suave en bordes
      if (p.x < 0) p.x = canvas.width;
      if (p.x > canvas.width) p.x = 0;
      if (p.y < 0) p.y = canvas.height;
      if (p.y > canvas.height) p.y = 0;

      // Dibujar
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = p.color + p.alpha + ")";
      ctx.fill();
    });

    requestAnimationFrame(draw);
  }

  draw();
})();