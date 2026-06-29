'use strict';



/**
 * add event on element
 */

const addEventOnElem = function (elem, type, callback) {
  if (elem.length > 1) {
    for (let i = 0; i < elem.length; i++) {
      elem[i].addEventListener(type, callback);
    }
  } else {
    elem.addEventListener(type, callback);
  }
}



/**
 * navbar toggle
 */

const navbar = document.querySelector("[data-navbar]");
const navTogglers = document.querySelectorAll("[data-nav-toggler]");
const navLinks = document.querySelectorAll("[data-nav-link]");
const overlay = document.querySelector("[data-overlay]");

const toggleNavbar = function () {
  navbar.classList.toggle("active");
  overlay.classList.toggle("active");
}

addEventOnElem(navTogglers, "click", toggleNavbar);

const closeNavbar = function () {
  navbar.classList.remove("active");
  overlay.classList.remove("active");
}

addEventOnElem(navLinks, "click", closeNavbar);



/**
 * header active when scroll down to 100px
 */

const header = document.querySelector("[data-header]");
const backTopBtn = document.querySelector("[data-back-top-btn]");

const activeElem = function () {
  if (window.scrollY > 100) {
    header.classList.add("active");
    backTopBtn.classList.add("active");
  } else {
    header.classList.remove("active");
    backTopBtn.classList.remove("active");
  }
}

addEventOnElem(window, "scroll", activeElem);



/**
 * video play functionality
 */

const playBtn = document.querySelector(".play-btn");
const videoPlayer = document.querySelector(".video-player");
const videoBanner = document.querySelector(".video-banner img");

// Fonction pour réinitialiser l'état initial
const resetVideoState = function() {
  // Masquer la vidéo
  videoPlayer.style.display = "none";
  
  // Réafficher le bouton play
  playBtn.style.display = "block";
  
  // Réafficher l'image de la bannière
  if (videoBanner) {
    videoBanner.style.opacity = "1";
    videoBanner.style.visibility = "visible";
  }
  
  // Réinitialiser la vidéo (remettre à 0)
  videoPlayer.currentTime = 0;
  videoPlayer.pause();
};

if (playBtn && videoPlayer) {
  // Gestion du clic sur le bouton play
  playBtn.addEventListener("click", function() {
    // Masquer le bouton play
    playBtn.style.display = "none";
    
    // Masquer l'image de la bannière avec opacity pour garder l'espace
    if (videoBanner) {
      videoBanner.style.opacity = "0";
      videoBanner.style.visibility = "hidden";
    }
    
    // Afficher la vidéo
    videoPlayer.style.display = "block";
    
    // Lancer la lecture de la vidéo
    videoPlayer.play().catch(function(error) {
      console.log("Erreur lors de la lecture de la vidéo:", error);
    });
  });
  
  // Gestion de la fin de la vidéo
  videoPlayer.addEventListener("ended", function() {
    resetVideoState();
  });



    document.querySelectorAll('.faq-question').forEach(button => {
        button.addEventListener('click', () => {
            const faqItem = button.parentElement;
            
            // Fermer les autres questions si nécessaire (optionnel)
            // document.querySelectorAll('.faq-item').forEach(item => {
            //     if (item !== faqItem) item.classList.remove('active');
            // });

            faqItem.classList.toggle('active');
        });
    });

}


/**
 * roadmap line scroll progress
 */

const roadmapTimeline = document.querySelector(".roadmap-timeline");
const timelineLine = document.querySelector(".timeline-line");

if (roadmapTimeline && timelineLine) {
  const updateRoadmapLineProgress = function () {
    const rect = roadmapTimeline.getBoundingClientRect();
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
    const totalDistance = rect.height + viewportHeight;
    const coveredDistance = viewportHeight - rect.top;
    const progress = Math.min(Math.max(coveredDistance / totalDistance, 0), 1);

    timelineLine.style.setProperty("--line-progress", progress.toFixed(4));
  };

  let ticking = false;

  const requestProgressUpdate = function () {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(function () {
      updateRoadmapLineProgress();
      ticking = false;
    });
  };

  window.addEventListener("scroll", requestProgressUpdate, { passive: true });
  window.addEventListener("resize", requestProgressUpdate);
  window.addEventListener("load", updateRoadmapLineProgress);
  updateRoadmapLineProgress();
}
