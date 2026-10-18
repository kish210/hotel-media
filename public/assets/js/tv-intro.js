/* ═══════════════════════════════════════════════════════════════════
   تیزر شروع — عکس/ویدیوهایی که تلویزیون هنگام روشن‌شدن یک‌بار پخش
   می‌کند و بعد محتوای عادی شروع می‌شود.

   TVINTRO.play(screenCode, done): اگر تیزری نبود یا خطا داد، همان لحظه
   done() صدا می‌شود — تیزر هرگز نباید جلوی محتوای اصلی را بگیرد.

   «یک‌بار» با sessionStorage شمرده می‌شود، نه روی سرور: سرور نمی‌داند
   تلویزیون خاموش و روشن شده یا فقط صفحه refresh شده. sessionStorage با
   بستن مرورگر/خاموش‌شدن تلویزیون پاک می‌شود، که دقیقا همان «روشن‌شدن
   دوباره» است. روی تلویزیونی که sessionStorage ندارد، تیزر هر بار پخش
   می‌شود — بهتر از این‌که هیچ‌وقت پخش نشود.

   ES5 است و بدون flexbox: پروفایل Orsay روی WebKit 534 اجرا می‌شود.
   ═══════════════════════════════════════════════════════════════════ */
(function (w, d) {
  'use strict';

  var KEY = 'tvintro_done';
  var BOX = 'tvintro-ov';

  function seen() {
    try { return w.sessionStorage && w.sessionStorage.getItem(KEY) === '1'; } catch (e) { return false; }
  }
  function mark() {
    try { if (w.sessionStorage) w.sessionStorage.setItem(KEY, '1'); } catch (e) {}
  }

  function box() {
    var el = d.getElementById(BOX);
    if (el) return el;
    el = d.createElement('div');
    el.id = BOX;
    el.style.cssText = 'position:fixed;left:0;top:0;right:0;bottom:0;z-index:2147483100;' +
                       'background:#000;display:none;text-align:center;';
    d.body.appendChild(el);
    return el;
  }

  var TVINTRO = {
    playing: false,

    /** @param code کد صفحه  @param done وقتی تیزر تمام شد (یا نبود) */
    play: function (code, done) {
      var finish = function () {
        TVINTRO.playing = false;
        var el = d.getElementById(BOX);
        if (el) { el.innerHTML = ''; el.style.display = 'none'; }
        mark();
        if (done) done();
      };

      if (seen() || !code) { if (done) done(); return; }

      var url = '/api/v1/screens/' + encodeURIComponent(code) + '/intro';
      var xhr = new XMLHttpRequest();
      var answered = false;
      /* اگر سرور کند بود، تلویزیون نباید روی صفحهٔ سیاه بماند */
      var guard = setTimeout(function () {
        if (!answered) { answered = true; try { xhr.abort(); } catch (e) {} if (done) done(); }
      }, 6000);

      xhr.open('GET', url, true);
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4 || answered) return;
        answered = true;
        clearTimeout(guard);
        var items = [];
        try {
          var j = JSON.parse(xhr.responseText);
          items = (j && j.data && j.data.items) || [];
        } catch (e) {}
        if (!items.length) { if (done) done(); return; }

        TVINTRO.playing = true;
        var el = box();
        el.style.display = 'block';
        var i = 0, timer = null;

        function step() {
          clearTimeout(timer);
          if (i >= items.length) { finish(); return; }
          var it = items[i++];
          var secs = parseInt(it.duration, 10); if (!secs || secs < 1) secs = 8;

          if (it.type === 'video') {
            el.innerHTML = '<video id="tvintro-v" autoplay muted playsinline ' +
              'style="width:100%;height:100%;background:#000;"></video>';
            var v = d.getElementById('tvintro-v');
            v.src = it.url;
            /* ویدیو تا پایان خودش؛ ولی اگر خراب بود یا هرگز تمام نشد،
               سقف زمانی جلوی گیرکردن تلویزیون را می‌گیرد. */
            v.onended = step;
            v.onerror = step;
            timer = setTimeout(step, Math.max(secs, 120) * 1000);
            try { var p = v.play(); if (p && p['catch']) p['catch'](function () {}); } catch (e2) {}
          } else {
            el.innerHTML = '<img src="' + it.url + '" alt="" ' +
              'style="max-width:100%;max-height:100%;position:absolute;left:0;top:0;right:0;bottom:0;margin:auto;">';
            var im = el.firstChild;
            if (im) im.onerror = step;
            timer = setTimeout(step, secs * 1000);
          }
        }
        step();
      };
      xhr.onerror = function () {
        if (answered) return;
        answered = true; clearTimeout(guard);
        if (done) done();
      };
      try { xhr.send(); } catch (e3) {
        answered = true; clearTimeout(guard); if (done) done();
      }
    }
  };

  w.TVINTRO = TVINTRO;
})(window, document);
