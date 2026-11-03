(() => {
  const toggle = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
      if (!window.confirm(form.dataset.confirm || 'Are you sure?')) event.preventDefault();
    });
  });
  document.querySelectorAll('.alert').forEach(alert => {
    if (!alert.classList.contains('error')) {
      window.setTimeout(() => { alert.classList.add('fade-out'); }, 5000);
    }
  });
  const memberForm = document.getElementById('memberForm');
  if (memberForm) memberForm.addEventListener('submit', event => {
    const email = memberForm.querySelector('input[name="email"]');
    if (email && email.value && !email.validity.valid) {
      event.preventDefault(); email.focus(); email.reportValidity();
    }
  });
})();
