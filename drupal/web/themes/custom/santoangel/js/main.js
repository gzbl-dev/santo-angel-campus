/**
 * @file
 * Menú desplegable en móvil.
 */
((Drupal, once) => {
  Drupal.behaviors.santoangelNav = {
    attach(context) {
      once('sa-nav', '[data-nav-toggle]', context).forEach((button) => {
        const nav = document.getElementById(button.getAttribute('aria-controls'));
        if (!nav) {
          return;
        }

        const setOpen = (open) => {
          button.setAttribute('aria-expanded', String(open));
          nav.classList.toggle('is-open', open);
        };

        button.addEventListener('click', () => {
          setOpen(button.getAttribute('aria-expanded') !== 'true');
        });

        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && nav.classList.contains('is-open')) {
            setOpen(false);
            button.focus();
          }
        });
      });
    },
  };
})(Drupal, once);
