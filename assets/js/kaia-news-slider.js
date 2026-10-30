(function () {
  function initSlider(slider) {
    var track = slider.querySelector("[data-kaia-news-slider-track]");
    var slides = Array.prototype.slice.call(slider.querySelectorAll("[data-kaia-news-slider-slide]"));
    var prev = slider.querySelector("[data-kaia-news-slider-prev]");
    var next = slider.querySelector("[data-kaia-news-slider-next]");
    var current = slider.querySelector("[data-kaia-news-slider-current]");
    var dots = Array.prototype.slice.call(slider.querySelectorAll("[data-kaia-news-slider-dot]"));
    var modal = slider.querySelector("[data-kaia-news-slider-modal]");
    var openModal = slider.querySelector("[data-kaia-news-slider-open]");
    var closeModal = slider.querySelector("[data-kaia-news-slider-close]");
    var modalImage = slider.querySelector("[data-kaia-news-slider-modal-image]");
    var modalPrev = slider.querySelector("[data-kaia-news-slider-modal-prev]");
    var modalNext = slider.querySelector("[data-kaia-news-slider-modal-next]");
    var index = 0;
    var autoplayDelay = 3000;
    var autoplayTimer = null;
    var isDragging = false;
    var dragStartX = 0;
    var dragStartY = 0;
    var dragDeltaX = 0;
    var suppressClick = false;

    if (!track || !slides.length) {
      return;
    }

    function update() {
      track.style.transform = "translateX(-" + index * 100 + "%)";

      if (current) {
        current.textContent = String(index + 1);
      }

      dots.forEach(function (dot, dotIndex) {
        var isActive = dotIndex === index;

        dot.classList.toggle("is-active", isActive);
        dot.setAttribute("aria-current", isActive ? "true" : "false");
      });

      updateModalImage();
    }

    function stopAutoplay() {
      if (autoplayTimer) {
        window.clearInterval(autoplayTimer);
        autoplayTimer = null;
      }
    }

    function startAutoplay() {
      stopAutoplay();

      if (slides.length < 2 || window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        return;
      }

      autoplayTimer = window.setInterval(function () {
        if (!document.hidden && !(modal && modal.classList.contains("is-open"))) {
          goTo(index + 1);
        }
      }, autoplayDelay);
    }

    function updateModalImage() {
      var image = slides[index].querySelector("img");

      if (!modalImage || !image) {
        return;
      }

      modalImage.src = image.currentSrc || image.src;
      modalImage.alt = image.alt || "";
    }

    function goTo(nextIndex) {
      index = (nextIndex + slides.length) % slides.length;
      update();
      startAutoplay();
    }

    function setModalState(isOpen) {
      if (!modal) {
        return;
      }

      modal.classList.toggle("is-open", isOpen);
      modal.setAttribute("aria-hidden", isOpen ? "false" : "true");
      document.documentElement.classList.toggle("kaia-news-slider-modal-open", isOpen);

      if (isOpen) {
        stopAutoplay();
        updateModalImage();
        modal.focus({ preventScroll: true });
      } else {
        startAutoplay();
      }
    }

    function onPointerDown(event) {
      if (slides.length < 2 || event.pointerType === "mouse" && event.button !== 0) {
        return;
      }

      isDragging = true;
      suppressClick = false;
      dragStartX = event.clientX;
      dragStartY = event.clientY;
      dragDeltaX = 0;
      track.classList.add("is-dragging");
      track.setPointerCapture(event.pointerId);
      stopAutoplay();
    }

    function onPointerMove(event) {
      if (!isDragging) {
        return;
      }

      dragDeltaX = event.clientX - dragStartX;
      var dragDeltaY = event.clientY - dragStartY;

      if (Math.abs(dragDeltaX) > Math.abs(dragDeltaY)) {
        event.preventDefault();
        track.style.transform = "translateX(calc(-" + (index * 100) + "% + " + dragDeltaX + "px))";
      }
    }

    function onPointerUp(event) {
      if (!isDragging) {
        return;
      }

      isDragging = false;
      track.classList.remove("is-dragging");

      if (track.hasPointerCapture(event.pointerId)) {
        track.releasePointerCapture(event.pointerId);
      }

      if (Math.abs(dragDeltaX) > Math.max(50, slider.clientWidth * 0.12)) {
        suppressClick = true;
        goTo(index + (dragDeltaX < 0 ? 1 : -1));
      } else {
        update();
        startAutoplay();
      }
    }

    if (prev) {
      prev.addEventListener("click", function () {
        goTo(index - 1);
      });
    }

    if (next) {
      next.addEventListener("click", function () {
        goTo(index + 1);
      });
    }

    dots.forEach(function (dot) {
      dot.addEventListener("click", function () {
        goTo(Number(dot.getAttribute("data-kaia-news-slider-dot")));
      });
    });

    if (openModal) {
      openModal.addEventListener("click", function () {
        setModalState(true);
      });
    }

    if (closeModal) {
      closeModal.addEventListener("click", function () {
        setModalState(false);
      });
    }

    if (modal) {
      modal.setAttribute("tabindex", "-1");
      modal.addEventListener("click", function (event) {
        if (event.target === modal) {
          setModalState(false);
        }
      });
    }

    if (modalPrev) {
      modalPrev.addEventListener("click", function () {
        goTo(index - 1);
      });
    }

    if (modalNext) {
      modalNext.addEventListener("click", function () {
        goTo(index + 1);
      });
    }

    track.addEventListener("pointerdown", onPointerDown);
    track.addEventListener("pointermove", onPointerMove);
    track.addEventListener("pointerup", onPointerUp);
    track.addEventListener("pointercancel", onPointerUp);
    track.addEventListener("click", function (event) {
      if (suppressClick) {
        event.preventDefault();
        event.stopPropagation();
        suppressClick = false;
      }
    }, true);

    slider.addEventListener("mouseenter", stopAutoplay);
    slider.addEventListener("mouseleave", startAutoplay);
    slider.addEventListener("focusin", stopAutoplay);
    slider.addEventListener("focusout", function (event) {
      if (!slider.contains(event.relatedTarget)) {
        startAutoplay();
      }
    });

    document.addEventListener("visibilitychange", function () {
      if (document.hidden) {
        stopAutoplay();
      } else {
        startAutoplay();
      }
    });

    document.addEventListener("keydown", function (event) {
      var modalIsOpen = modal && modal.classList.contains("is-open");

      if (!modalIsOpen) {
        return;
      }

      if (event.key === "Escape") {
        setModalState(false);
      } else if (event.key === "ArrowLeft") {
        goTo(index - 1);
      } else if (event.key === "ArrowRight") {
        goTo(index + 1);
      }
    });

    update();
    startAutoplay();
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-kaia-news-slider]").forEach(initSlider);
  });
})();
