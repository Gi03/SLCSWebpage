// Replace your old tour JavaScript with this (inside your existing <script> tag).

var tourPhotos = [];
var modal     = document.getElementById('tourModal');
var tourImg   = document.getElementById('tourImg');
var tourPano  = document.getElementById('tourPano');
var caption   = document.getElementById('tourCaption');
var current   = 0;
var panoViewer = null;

function clearPano() {
  if (panoViewer) { panoViewer.destroy(); panoViewer = null; }
}

function showPhoto(i) {
  current = (i + tourPhotos.length) % tourPhotos.length;
  var p = tourPhotos[current];
  clearPano();

  if (p.type === 'pano' && window.pannellum) {
    tourImg.style.display = 'none';
    tourPano.style.display = 'block';
    panoViewer = pannellum.viewer('tourPano', {
      type: 'equirectangular',
      panorama: p.file,
      autoLoad: true,
      autoRotate: -2,        // slow spin; set to 0 to turn off
      showControls: true
    });
  } else {
    tourPano.style.display = 'none';
    tourImg.style.display = 'block';
    tourImg.src = p.file;
    tourImg.alt = p.caption;
  }

  var tag = p.type === 'pano' ? ' [360\u00B0 drag to look around]' : '';
  caption.textContent = p.caption + '  (' + (current + 1) + ' / ' + tourPhotos.length + ')' + tag;
}

function openTour(e) {
  e.preventDefault();
  fetch('photos.php', { cache: 'no-store' })
    .then(function (r) { return r.json(); })
    .then(function (list) {
      if (!list.length) { alert('Tour photos are coming soon.'); return; }
      tourPhotos = list;
      modal.classList.add('show');          // show first so the 360 viewer can measure its size
      modal.setAttribute('aria-hidden', 'false');
      showPhoto(0);
    })
    .catch(function () { alert('Could not load the tour photos. Please try again later.'); });
}

function closeTour() {
  clearPano();
  modal.classList.remove('show');
  modal.setAttribute('aria-hidden', 'true');
}

document.getElementById('openTour').addEventListener('click', openTour);
document.getElementById('tourClose').addEventListener('click', closeTour);
document.getElementById('tourPrev').addEventListener('click', function () { if (tourPhotos.length) showPhoto(current - 1); });
document.getElementById('tourNext').addEventListener('click', function () { if (tourPhotos.length) showPhoto(current + 1); });
modal.addEventListener('click', function (e) { if (e.target === modal) closeTour(); });
document.addEventListener('keydown', function (e) {
  if (!modal.classList.contains('show')) return;
  if (e.key === 'Escape') closeTour();
  if (e.key === 'ArrowLeft') showPhoto(current - 1);
  if (e.key === 'ArrowRight') showPhoto(current + 1);
});
