/*
 * Back-office progressive enhancement. Every action also works without JavaScript.
 */
(() => {
  'use strict';

  // Ask before destructive form submissions: <form data-confirm="Delete this?">.
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const submitter = event.submitter;
    const message = (submitter && submitter.dataset.confirm) || form.dataset.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
  });
})();
