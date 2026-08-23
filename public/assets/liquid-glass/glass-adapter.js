/**
 * Liquid Glass Adapter — Connect'Academia (espace gestionnaire)
 *
 * Progressive enhancement : monte un canvas WebGL (réfraction liquid-glass-js)
 * DERRIÈRE les boutons .g-btn existants, sans jamais remplacer l'élément.
 * Les <a href>, <button type="submit">, onclick, formulaires restent intacts.
 *
 * Garde-fous :
 *  - budget MAX_GLASS contextes WebGL par page (limite navigateur ~8-16)
 *  - jamais les boutons de tableaux (répétés) ni de modales (cachées à l'init)
 *  - si WebGL ou html2canvas indisponible → aucun changement, style CSS conservé
 */
(function () {
  'use strict';

  var MAX_GLASS = 8;

  // Préréglages verre — fond crème clair : verre affirmé, réfraction bien lisible
  window.glassControls = window.glassControls || {
    edgeIntensity: 0.022,
    rimIntensity: 0.095,
    baseIntensity: 0.01,
    edgeDistance: 0.14,
    rimDistance: 0.7,
    baseDistance: 0.1,
    cornerBoost: 0.035,
    rippleEffect: 0.14,
    blurRadius: 6.5,
    tintOpacity: 0.14,
    warp: false
  };

  function webglAvailable() {
    try {
      var c = document.createElement('canvas');
      return !!(c.getContext('webgl') || c.getContext('experimental-webgl'));
    } catch (e) {
      return false;
    }
  }

  /**
   * Verre monté sur un élément DOM existant.
   * Hand-off statique : super() appelle init() -> createElement() de manière
   * synchrone, donc _pendingEl est consommé avant toute autre instanciation.
   */
  var GlassSurface = null;

  function defineGlassSurface() {
    GlassSurface = class extends Container {
      constructor(el, options) {
        GlassSurface._pendingEl = el;
        super(options);
      }

      createElement() {
        this.element = GlassSurface._pendingEl;
        GlassSurface._pendingEl = null;

        // .glass-button => html2canvas ignore l'élément dans le snapshot
        this.element.classList.add('glass-button', 'lg-glassified');

        this.canvas = document.createElement('canvas');
        this.canvas.className = 'lg-glass-canvas';
        this.canvas.style.position = 'absolute';
        this.canvas.style.top = '0';
        this.canvas.style.left = '0';
        this.canvas.style.width = '100%';
        this.canvas.style.height = '100%';
        this.canvas.style.borderRadius = 'inherit';
        this.canvas.style.zIndex = '-2'; // sous l'overlay ::before (-1) et le texte
        this.canvas.style.pointerEvents = 'none';

        this.element.appendChild(this.canvas);
      }
    };
  }

  function collectCandidates() {
    var scope = document.querySelectorAll('.g-topbar .g-btn, .g-content .g-btn');
    var out = [];
    for (var i = 0; i < scope.length; i++) {
      var el = scope[i];
      if (el.classList.contains('no-glass')) continue;        // opt-out manuel
      if (el.closest('.g-modal-overlay')) continue;            // modales cachées à l'init
      if (el.closest('.g-table')) continue;                    // boutons répétés de tableaux
      if (el.offsetParent === null) continue;                  // invisibles
      var r = el.getBoundingClientRect();
      if (r.width < 24 || r.height < 20) continue;             // trop petits pour le shader
      out.push(el);
      if (out.length >= MAX_GLASS) break;
    }
    return out;
  }

  var instances = [];

  function glassify() {
    var candidates = collectCandidates();
    if (!candidates.length) return;

    defineGlassSurface();

    // Classes AVANT toute instanciation : le 1er GlassSurface déclenche le
    // snapshot html2canvas, qui doit déjà ignorer TOUS les futurs boutons verre
    // (sinon leur style d'origine est capturé et réfracté par le shader).
    candidates.forEach(function (el) {
      el.classList.add('glass-button', 'lg-glassified');
    });

    candidates.forEach(function (el) {
      try {
        var r = el.getBoundingClientRect();
        // Hiérarchie sans couleur : le bouton primaire = givre plus dense
        var isPrimary = el.classList.contains('g-btn-primary');
        instances.push(new GlassSurface(el, {
          type: 'pill',
          borderRadius: Math.round(r.height / 2),
          tintOpacity: isPrimary ? 0.38 : 0.14
        }));
      } catch (e) {
        el.classList.remove('glass-button', 'lg-glassified');
      }
    });
  }

  // Recapture snapshot au resize (débouncé) — sinon verre périmé
  function setupResizeRecapture() {
    var t;
    window.addEventListener('resize', function () {
      clearTimeout(t);
      t = setTimeout(function () {
        if (!Container.instances.length || Container.isCapturing) return;
        Container.pageSnapshot = null;
        Container.isCapturing = true;
        Container.waitingForSnapshot = [];

        html2canvas(document.body, {
          scale: 1,
          useCORS: true,
          allowTaint: true,
          backgroundColor: null,
          ignoreElements: function (element) {
            return (
              element.classList.contains('glass-container') ||
              element.classList.contains('glass-button') ||
              element.classList.contains('glass-button-text')
            );
          }
        }).then(function (snapshot) {
          Container.pageSnapshot = snapshot;
          Container.isCapturing = false;
          var img = new Image();
          img.src = snapshot.toDataURL();
          img.onload = function () {
            Container.instances.forEach(function (inst) {
              if (!inst.gl_refs || !inst.gl_refs.gl) return;
              var gl = inst.gl_refs.gl;
              inst.updateSizeFromDOM();
              gl.bindTexture(gl.TEXTURE_2D, inst.gl_refs.texture);
              gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, img);
              gl.uniform2f(inst.gl_refs.textureSizeLoc, img.width, img.height);
              if (inst.render) inst.render();
            });
          };
        }).catch(function () {
          Container.isCapturing = false;
        });
      }, 350);
    });
  }

  function boot() {
    if (typeof Container === 'undefined' || typeof html2canvas === 'undefined') return;
    if (!webglAvailable()) return; // fallback : boutons CSS actuels, rien ne change

    // Attendre les webfonts pour un snapshot fidèle
    var ready = (document.fonts && document.fonts.ready)
      ? document.fonts.ready
      : Promise.resolve();

    ready.then(function () {
      // laisser le layout se stabiliser un cycle
      requestAnimationFrame(function () {
        try {
          glassify();
          setupResizeRecapture();
        } catch (e) {
          /* échec silencieux : l'UI CSS existante reste pleinement fonctionnelle */
        }
      });
    });
  }

  if (document.readyState === 'complete') {
    boot();
  } else {
    window.addEventListener('load', boot);
  }
})();
