/* Gabarit établissement — interactions */
(function () {
  document.body.classList.add('js');

  // Révélation au scroll
  function setupReveal() {
    var groups = [
      ['.section-head'],
      ['.filiere'],
      ['.video-stage'],
      ['.about-visual', '.about-body'],
      ['.post'],
      ['.tarif-card'],
      ['.contact-grid'],
      ['.foot-grid']
    ];
    groups.forEach(function (sel) {
      document.querySelectorAll(sel.join(',')).forEach(function (el) {
        el.classList.add('reveal');
      });
    });
    document.querySelectorAll('.filiere-grid .filiere').forEach(function (el, i) {
      el.style.setProperty('--rd', (i % 3) * 90 + 'ms');
    });
    document.querySelectorAll('.blog-grid .post').forEach(function (el, i) {
      el.style.setProperty('--rd', (i % 3) * 90 + 'ms');
    });

    if (!('IntersectionObserver' in window)) {
      document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });
  }
  if (document.readyState !== 'loading') setupReveal();
  else document.addEventListener('DOMContentLoaded', setupReveal);

  // Ombre du header au scroll
  var header = document.querySelector('.site-header .nav');
  function onScroll() {
    if (!header) return;
    if (window.scrollY > 8) header.style.boxShadow = '0 10px 34px rgba(54,44,32,0.13)';
    else header.style.boxShadow = '';
  }
  window.addEventListener('scroll', onScroll, { passive: true });

  // Lecture vidéo
  var stage = document.getElementById('videoStage');
  if (stage) {
    stage.addEventListener('click', function () {
      var play = stage.querySelector('.video-play span');
      if (play) {
        play.style.transform = 'scale(0.86)';
        setTimeout(function () { play.style.transform = ''; }, 160);
      }
    });
  }

  // Menu mobile
  var burger = document.querySelector('.burger');
  var menu = document.querySelector('.nav-menu');
  if (burger && menu) {
    burger.addEventListener('click', function () {
      menu.classList.toggle('open');
    });
    // Ferme menu au clic sur lien
    menu.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        menu.classList.remove('open');
      });
    });
    // Ferme au clic dehors
    document.addEventListener('click', function (e) {
      if (!burger.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove('open');
      }
    });
  }
})();
