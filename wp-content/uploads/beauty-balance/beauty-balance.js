(() => {
  const modal = document.getElementById('lashSetModal');
  if (!modal) return;
  const panel = modal.firstElementChild;
  const openModal = (event) => {
    if (event) event.preventDefault();
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    window.requestAnimationFrame(() => {
      modal.classList.remove('opacity-0');
      if (panel) panel.classList.remove('scale-95');
    });
    document.body.style.overflow = 'hidden';
  };
  const closeModal = () => {
    modal.classList.add('opacity-0');
    if (panel) panel.classList.add('scale-95');
    window.setTimeout(() => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.body.style.overflow = '';
    }, 300);
  };
  document.addEventListener('click', (event) => {
    const open = event.target.closest('[data-open-lash-modal]');
    if (open) return openModal(event);
    if (event.target.closest('[data-close-lash-modal]')) return closeModal();
    if (event.target === modal) closeModal();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
  });
})();