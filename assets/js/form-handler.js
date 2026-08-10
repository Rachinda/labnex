(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    const forms = document.querySelectorAll('.php-email-form');

    forms.forEach(form => {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        const action = form.getAttribute('action');
        const recaptcha = form.getAttribute('data-recaptcha-site-key');

        if (!action) {
          return showError(form, 'The form action property is not set!');
        }

        toggleLoading(form, true);

        const formData = new FormData(form);

        if (recaptcha) {
          if (typeof grecaptcha !== "undefined") {
            grecaptcha.ready(function () {
              try {
                grecaptcha.execute(recaptcha, { action: 'php_email_form_submit' })
                  .then(token => {
                    formData.set('recaptcha-response', token);
                    sendForm(form, action, formData);
                  });
              } catch (err) {
                showError(form, err);
              }
            });
          } else {
            showError(form, 'The reCaptcha JavaScript API URL is not loaded!');
          }
        } else {
          sendForm(form, action, formData);
        }
      });
    });

    function sendForm(form, action, formData) {
      fetch(action, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(response => {
          if (response.ok) return response.text();
          throw new Error(`${response.status} ${response.statusText} ${response.url}`);
        })
        .then(data => {
          toggleLoading(form, false);
          if (data.trim().toUpperCase() === 'OK') {
            form.querySelector('.sent-message').classList.add('d-block');
            form.reset();
          } else {
            throw new Error(data || 'Form submission failed with no server message.');
          }
        })
        .catch(err => showError(form, err));
    }

    function toggleLoading(form, isLoading) {
      form.querySelector('.loading').classList.toggle('d-block', isLoading);
      form.querySelector('.error-message').classList.remove('d-block');
      form.querySelector('.sent-message').classList.remove('d-block');
    }

    function showError(form, error) {
      form.querySelector('.loading').classList.remove('d-block');
      form.querySelector('.error-message').innerHTML = error;
      form.querySelector('.error-message').classList.add('d-block');
    }
  });
})();
