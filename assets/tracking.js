/* Tracking and cookie consent.
   The tags are the ones used on the previous temperrestaurant.com. They load only after "Accept all",
   and only on the real domain, so previews and test copies never send data. */
(function () {
  var KEY = 'site_cookie_consent'; // same key as the previous site, so earlier choices carry over
  var LIVE = /^(www\.)?temperrestaurant\.com$/.test(location.hostname);
  var ROOT = document.currentScript.src.replace(/assets\/tracking\.js.*$/, '');
  var loaded = false;

  function choice() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
  function save(value) { try { localStorage.setItem(KEY, value); } catch (e) {} }

  function script(src, onload) {
    var s = document.createElement('script');
    s.async = true;
    s.src = src;
    if (onload) s.onload = onload;
    document.head.appendChild(s);
  }

  function whenReady(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  function load() {
    if (loaded || !LIVE) return;
    loaded = true;

    // Google Tag Manager
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
    script('https://www.googletagmanager.com/gtm.js?id=GTM-5T3TV85G');

    // Google Analytics 4
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', 'G-W78WNBLB90');
    script('https://www.googletagmanager.com/gtag/js?id=G-W78WNBLB90');

    // Microsoft Clarity
    window.clarity = window.clarity || function () { (window.clarity.q = window.clarity.q || []).push(arguments); };
    script('https://www.clarity.ms/tag/riu8grutkr');

    // Meta Pixel
    if (!window.fbq) {
      var f = window.fbq = function () { f.callMethod ? f.callMethod.apply(f, arguments) : f.queue.push(arguments); };
      if (!window._fbq) window._fbq = f;
      f.push = f; f.loaded = true; f.version = '2.0'; f.queue = [];
      script('https://connect.facebook.net/en_US/fbevents.js');
    }
    window.fbq('init', '1762122564981588');
    window.fbq('track', 'PageView');

    // Atreemo (needs <body>)
    whenReady(function () {
      script('https://tracking.atreemo.com/Scripts/TrackingInit.js', function () {
        window.AtreemoTrackingLbr.init(['TheTemperRestaurants', 'https://thetemperrestaurants.webecast.atreemo.uk']);
        script('https://tracking.atreemo.com/Scripts/Tracking.js');
      });
    });
  }

  function clearCookies() {
    var parts = location.hostname.split('.');
    var domains = ['', location.hostname, '.' + parts.slice(-2).join('.')];
    document.cookie.split('; ').forEach(function (c) {
      var name = c.split('=')[0];
      if (!/^(_ga|_gid|_gat|_gcl|_fbp|_fbc|_clck|_clsk|Atreemo)/.test(name)) return;
      domains.forEach(function (d) {
        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + (d ? '; domain=' + d : '');
      });
    });
  }

  function banner() {
    if (document.getElementById('cookie')) return;
    var el = document.createElement('div');
    el.id = 'cookie';
    el.className = 'cookie-bar';
    el.setAttribute('role', 'region');
    el.setAttribute('aria-label', 'Cookie consent');
    el.innerHTML = '<p>We use a small number of cookies to keep the site running. Analytics help us see what’s working. ' +
      'No cookies are set until you choose. See our <a href="' + ROOT + 'cookies-policy">cookie policy</a>.</p>' +
      '<div><button type="button" data-cookie="essential">Essential only</button>' +
      '<button type="button" data-cookie="all">Accept all</button></div>';
    el.addEventListener('click', function (e) {
      var value = e.target.getAttribute('data-cookie');
      if (!value) return;
      save(value);
      el.remove();
      if (value === 'all') load();
      else if (loaded) { clearCookies(); location.reload(); }
    });
    document.body.appendChild(el);
  }

  var style = document.createElement('style');
  style.textContent =
    '.cookie-bar{position:fixed;left:16px;right:16px;bottom:16px;z-index:70;max-width:780px;margin:0 auto;display:flex;flex-wrap:wrap;gap:14px 22px;align-items:center;justify-content:space-between;padding:18px 20px;background:#121013;color:#f5ede0;border:1px solid rgba(245,237,224,.22);box-shadow:0 18px 50px rgba(0,0,0,.45);font:400 14px/1.55 Inter,system-ui,sans-serif;text-align:left}' +
    '.cookie-bar p{flex:1 1 320px;margin:0;font:inherit;color:inherit;max-width:none}' +
    '.cookie-bar a{color:inherit;text-decoration:underline}' +
    '.cookie-bar div{display:flex;gap:10px}' +
    '.cookie-bar button{cursor:pointer;padding:12px 16px;font:600 11px/1 Inter,system-ui,sans-serif;letter-spacing:.16em;text-transform:uppercase;background:transparent;color:#f5ede0;border:1px solid #d94a1c}' +
    '.cookie-bar button[data-cookie="all"]{background:#d94a1c;color:#121013}' +
    '.cookie-settings{background:none;border:0;padding:0;margin:0;cursor:pointer;color:inherit;font:inherit;letter-spacing:inherit;text-transform:inherit}' +
    '@media(max-width:900px){.cookie-bar{bottom:76px;left:10px;right:10px;padding:14px;font-size:13px}.cookie-bar div{width:100%}.cookie-bar button{flex:1}}';
  document.head.appendChild(style);

  if (choice() === 'all') load();

  whenReady(function () {
    // Lets visitors change their choice later
    var links = document.querySelector('.footer__base > div:last-child');
    if (links) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'cookie-settings';
      button.textContent = 'Cookie settings';
      button.addEventListener('click', banner);
      links.appendChild(button);
    }
    if (!choice()) banner();
  });
})();
