'use strict';

document.querySelectorAll('form[data-confirm]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});

const togglePassword = document.querySelector('[data-toggle-password]');
togglePassword?.addEventListener('click', () => {
  const input = document.getElementById('password');
  const reveal = input.type === 'password';
  input.type = reveal ? 'text' : 'password';
  togglePassword.setAttribute('aria-pressed', String(reveal));
  togglePassword.setAttribute('aria-label', reveal ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
});

const upload = document.querySelector('[data-photo-upload]');
if (upload) {
  const preview = document.querySelector('[data-photo-preview]');
  const error = document.querySelector('[data-upload-error]');
  let urls = [];
  function updatePreview() {
    urls.forEach((url) => URL.revokeObjectURL(url));
    urls = [];
    preview.replaceChildren();
    error.textContent = '';
    upload.setCustomValidity('');
    const files = Array.from(upload.files);
    const removed = document.querySelectorAll('input[name="remove_photos[]"]:checked').length;
    const total = Number(upload.dataset.existingCount) - removed + files.length;
    const message = total > 5 ? 'Maksimal 5 foto per pengumuman. Hapus foto lama atau kurangi pilihan.'
      : files.some((file) => file.size > 5 * 1024 * 1024) ? 'Ukuran setiap foto maksimal 5 MB.'
      : files.some((file) => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) ? 'Format foto harus JPG, PNG, atau WebP.' : '';
    if (message) {
      error.textContent = message;
      upload.setCustomValidity(message);
      return;
    }
    files.forEach((file) => {
      const figure = document.createElement('figure');
      const image = document.createElement('img');
      const caption = document.createElement('figcaption');
      const url = URL.createObjectURL(file);
      urls.push(url);
      image.src = url;
      image.alt = 'Pratinjau: ' + file.name;
      caption.textContent = file.name;
      figure.append(image, caption);
      preview.append(figure);
    });
  }
  upload.addEventListener('change', updatePreview);
  document.querySelectorAll('input[name="remove_photos[]"]').forEach((input) => input.addEventListener('change', updatePreview));
}

document.querySelector('[data-announcement-form]')?.addEventListener('submit', (event) => {
  const button = event.currentTarget.querySelector('button[type="submit"]');
  button.disabled = true;
  button.textContent = 'Menyimpan...';
});
window.addEventListener('pageshow', () => {
  const button = document.querySelector('[data-announcement-form] button[type="submit"]');
  if (button?.disabled) {
    button.disabled = false;
    button.textContent = 'Simpan pengumuman';
  }
});

const themeMedia = window.matchMedia('(prefers-color-scheme: dark)');
let themePreference = 'system';
try { themePreference = localStorage.getItem('portal-theme') || 'system'; } catch {}
function applyPortalTheme(preference) {
  themePreference = ['light', 'dark', 'system'].includes(preference) ? preference : 'system';
  document.documentElement.dataset.theme = themePreference === 'system' ? (themeMedia.matches ? 'dark' : 'light') : themePreference;
  document.querySelectorAll('[data-theme-select]').forEach(select => { select.value = themePreference; });
}
applyPortalTheme(themePreference);
document.querySelectorAll('[data-theme-select]').forEach(select => select.addEventListener('change', () => {
  applyPortalTheme(select.value);
  try { localStorage.setItem('portal-theme', themePreference); } catch {}
}));
themeMedia.addEventListener('change', () => { if (themePreference === 'system') applyPortalTheme('system'); });
document.addEventListener('keydown', event => {
  if (event.key === 'Escape') document.querySelectorAll('.portal-menu[open]').forEach(menu => { menu.open = false; menu.querySelector('summary').focus(); });
});
document.addEventListener('click', event => {
  document.querySelectorAll('.portal-menu[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
});
document.querySelectorAll('[data-ministry-carousel]').forEach(carousel => {
  const slides = [...carousel.querySelectorAll('[data-ministry-slide]')];
  const dots = [...carousel.querySelectorAll('[data-slide-to]')];
  const toggle = carousel.querySelector('[data-slide-toggle]');
  const status = carousel.querySelector('[data-carousel-status]');
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let current = 0;
  let paused = motion.matches;
  let hovered = false;
  let pointerPaused = null;
  let timer;
  function schedule() {
    window.clearInterval(timer);
    toggle.textContent = paused ? 'Lanjutkan' : 'Jeda';
    toggle.setAttribute('aria-label', paused ? 'Lanjutkan slideshow' : 'Jeda slideshow');
    if (!paused && !hovered && !document.hidden) timer = window.setInterval(() => show(current + 1), 5000);
  }
  function show(index, manual = false) {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, slideIndex) => { slide.hidden = slideIndex !== current; });
    dots.forEach((dot, slideIndex) => {
      if (slideIndex === current) dot.setAttribute('aria-current', 'true');
      else dot.removeAttribute('aria-current');
    });
    carousel.dataset.currentSlide = String(current);
    if (manual) {
      status.textContent = slides[current].querySelector('strong').textContent + ', foto ' + (current + 1) + ' dari ' + slides.length;
      schedule();
    }
  }
  carousel.querySelector('[data-slide-prev]').addEventListener('click', () => show(current - 1, true));
  carousel.querySelector('[data-slide-next]').addEventListener('click', () => show(current + 1, true));
  dots.forEach(dot => dot.addEventListener('click', () => show(Number(dot.dataset.slideTo), true)));
  toggle.addEventListener('pointerdown', () => { pointerPaused = paused; });
  toggle.addEventListener('pointercancel', () => { pointerPaused = null; });
  toggle.addEventListener('click', event => {
    paused = event.detail > 0 && pointerPaused !== null ? !pointerPaused : !paused;
    pointerPaused = null;
    schedule();
  });
  carousel.addEventListener('mouseenter', () => { hovered = true; schedule(); });
  carousel.addEventListener('mouseleave', () => { hovered = false; schedule(); });
  carousel.addEventListener('focusin', () => { paused = true; schedule(); });
  carousel.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
      event.preventDefault();
      show(current + (event.key === 'ArrowRight' ? 1 : -1), true);
    }
  });
  document.addEventListener('visibilitychange', schedule);
  motion.addEventListener('change', () => { paused = motion.matches; schedule(); });
  carousel.querySelector('[data-carousel-controls]').hidden = false;
  show(0);
  schedule();
});
const coverChoice = document.querySelector('[data-cover-choice]');
if (coverChoice) {
  const category = document.querySelector('#category');
  const coverPreview = coverChoice.querySelector('[data-category-cover-preview]');
  function updateCategoryCover() {
    const useDefault = coverChoice.querySelector('input[name="use_default_cover"]:checked')?.value === '1';
    coverPreview.hidden = !useDefault;
    coverPreview.querySelectorAll('[data-cover-category]').forEach(image => { image.hidden = image.dataset.coverCategory !== category.value; });
  }
  category.addEventListener('change', updateCategoryCover);
  coverChoice.querySelectorAll('input[name="use_default_cover"]').forEach(input => input.addEventListener('change', updateCategoryCover));
  document.querySelector('[data-photo-upload]')?.addEventListener('change', event => {
    if (event.target.files.length) {
      coverChoice.querySelector('input[value="0"]').checked = true;
      updateCategoryCover();
    }
  });
  updateCategoryCover();
}