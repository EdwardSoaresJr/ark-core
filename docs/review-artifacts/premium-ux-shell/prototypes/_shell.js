(function () {
  const shell = document.getElementById('shell');
  document.getElementById('toggleSidebar')?.addEventListener('click', () => {
    if (window.matchMedia('(max-width: 900px)').matches) {
      shell.classList.toggle('is-nav-open');
    } else {
      shell.classList.toggle('is-collapsed');
    }
  });
  document.getElementById('toggleTheme')?.addEventListener('click', () => {
    const html = document.documentElement;
    html.setAttribute('data-bs-theme', html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark');
  });
})();
