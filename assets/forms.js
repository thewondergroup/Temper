/* Contact and private hire forms: reCAPTCHA v3 token, then send to api/enquiry.php. */
(function () {
  var forms = document.querySelectorAll('form[data-form-submit]');
  var preview = /\.github\.io$/.test(location.hostname); // GitHub Pages can't run the form handler
  var FAIL = "Sorry, your message couldn't be sent. Please try again, or email reservationsmanager@temperrestaurant.com.";
  var captcha;

  function loadCaptcha(key) {
    if (!captcha) {
      captcha = new Promise(function (resolve, reject) {
        var s = document.createElement('script');
        s.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(key);
        s.onload = function () {
          if (window.grecaptcha) grecaptcha.ready(resolve);
          else { captcha = null; s.remove(); reject(new Error('recaptcha')); }
        };
        s.onerror = function () { captcha = null; s.remove(); reject(new Error('recaptcha')); };
        document.head.appendChild(s);
      });
    }
    return captcha;
  }

  forms.forEach(function (form) {
    var btn = form.querySelector('[data-form-submit-btn]');
    var status = form.querySelector('[data-form-status]');
    var token = form.querySelector('input[name="g-recaptcha-response"]');
    var key = form.getAttribute('data-recaptcha-site-key');
    var action = form.getAttribute('data-recaptcha-action');

    function busy(on) {
      btn.disabled = on;
      btn.toggleAttribute('aria-busy', on);
      btn.textContent = btn.getAttribute(on ? 'data-submit-loading' : 'data-submit-label');
    }
    function show(message, ok) {
      status.textContent = message;
      status.classList.toggle('is-error', !ok);
      status.hidden = false;
    }

    if (preview) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        show('Preview site: nothing was sent.', true);
      });
      return;
    }

    loadCaptcha(key).catch(function () {});

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (btn.disabled) return;
      busy(true);
      status.hidden = true;

      loadCaptcha(key)
        .then(function () { return grecaptcha.execute(key, { action: action }); })
        .then(function (value) {
          token.value = value;
          return fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data.ok === true) { form.reset(); show(data.message, true); }
          else show(data.message || FAIL, false);
        })
        .catch(function () { show(FAIL, false); })
        .then(function () { token.value = ''; busy(false); });
    });
  });
})();
