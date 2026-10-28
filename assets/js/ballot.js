(function () {
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Countdown in the hero (display only; voting is opened and closed from the admin) */
  var closesEl = document.querySelector('[data-closes]');
  if (closesEl) {
    var leftEl = closesEl.querySelector('.closes-left');
    var end = new Date(closesEl.dataset.closes).getTime();
    var tick = function () {
      var ms = end - Date.now();
      if (isNaN(ms) || !leftEl) return;
      if (ms <= 0) { leftEl.textContent = 'Closing now'; leftEl.hidden = false; return; }
      var d = Math.floor(ms / 864e5);
      var h = Math.floor((ms % 864e5) / 36e5);
      var m = Math.floor((ms % 36e5) / 6e4);
      leftEl.textContent = (d > 0 ? d + ' d ' + h + ' h' : h + ' h ' + m + ' min') + ' left';
      leftEl.hidden = false;
    };
    tick();
    setInterval(tick, 30000);
  }

  var form = document.getElementById('ballot-form');
  if (!form) return;

  var total = Number(form.dataset.total);
  var label = form.dataset.label;
  var inputs = [].slice.call(form.querySelectorAll('.nominee-input'));
  var sections = [].slice.call(form.querySelectorAll('.category'));
  var chips = [].slice.call(form.querySelectorAll('.chip'));
  var chipsEl = document.getElementById('chips');
  var countEl = document.getElementById('picked-count');
  var hintEl = document.getElementById('picked-hint');
  var fillEl = document.getElementById('progress-fill');
  var reviewBtn = document.getElementById('review-btn');
  var sheetEl = document.getElementById('review-sheet');
  var listEl = document.getElementById('review-list');
  var skippedEl = document.getElementById('review-skipped');
  var payBtn = document.getElementById('pay-btn');
  var pendingScroll = null;

  function groupOf(input) {
    return inputs.filter(function (i) { return i.name === input.name; });
  }

  function chipFor(catId) {
    return chips.filter(function (c) { return c.dataset.cat === catId; })[0];
  }

  function update() {
    var picks = {};
    inputs.forEach(function (i) { if (i.checked) picks[i.dataset.cat] = i; });
    var n = Object.keys(picks).length;

    countEl.textContent = n;
    fillEl.style.width = (total ? (n / total) * 100 : 0) + '%';
    reviewBtn.disabled = n === 0;
    reviewBtn.textContent = n > 0 ? 'Review ballot (' + n + ')' : 'Review ballot';
    hintEl.textContent = n === 0
      ? 'Pick a nominee to start your ballot.'
      : (n < total ? 'You can skip the rest, or keep going.' : 'You have picked in every category.');

    sections.forEach(function (sec) {
      var pick = picks[sec.dataset.cat];
      sec.classList.toggle('has-pick', !!pick);
      sec.querySelector('.status-text').textContent = pick ? 'You picked ' + pick.dataset.name : 'No pick yet';
      var chip = chipFor(sec.dataset.cat);
      if (chip) {
        chip.classList.toggle('done', !!pick);
        chip.querySelector('.chip-done-label').textContent = pick ? '(picked)' : '';
      }
    });
  }

  function renderSheet() {
    listEl.textContent = '';
    var skipped = 0;
    sections.forEach(function (sec) {
      var pick = form.querySelector('.nominee-input[data-cat="' + sec.dataset.cat + '"]:checked');
      if (!pick) { skipped++; return; }
      var row = document.createElement('div');
      row.className = 'review-row';

      var text = document.createElement('div');
      var cat = document.createElement('span');
      cat.className = 'review-cat';
      cat.textContent = sec.dataset.name;
      var who = document.createElement('strong');
      who.textContent = pick.dataset.name;
      text.appendChild(cat);
      text.appendChild(who);

      var change = document.createElement('button');
      change.type = 'button';
      change.className = 'btn btn-link btn-sm review-change';
      change.textContent = 'Change';
      change.addEventListener('click', function () {
        pendingScroll = sec.id;
        bootstrap.Offcanvas.getOrCreateInstance(sheetEl).hide();
      });

      row.appendChild(text);
      row.appendChild(change);
      listEl.appendChild(row);
    });
    skippedEl.textContent = skipped > 0
      ? 'You are skipping ' + skipped + (skipped === 1 ? ' category.' : ' categories.')
      : '';
  }

  function scrollToSection(id) {
    var el = document.getElementById(id);
    if (el) el.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
  }

  sheetEl.addEventListener('show.bs.offcanvas', renderSheet);
  sheetEl.addEventListener('hidden.bs.offcanvas', function () {
    if (pendingScroll) { scrollToSection(pendingScroll); pendingScroll = null; }
  });

  inputs.forEach(function (input) {
    // Clicking the selected nominee again clears the pick
    input.addEventListener('click', function () {
      if (input.dataset.was === '1') {
        input.checked = false;
        input.dataset.was = '0';
        update();
      }
    });
    input.addEventListener('change', function () {
      groupOf(input).forEach(function (g) { g.dataset.was = '0'; });
      if (input.checked) input.dataset.was = '1';
      update();
    });
  });

  /* Highlight the chip for the category in view and keep it visible in the strip */
  if ('IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        chips.forEach(function (c) {
          var on = c.dataset.cat === entry.target.dataset.cat;
          c.classList.toggle('active', on);
          if (on) {
            c.setAttribute('aria-current', 'true');
            chipsEl.scrollTo({
              left: c.offsetLeft - chipsEl.clientWidth / 2 + c.clientWidth / 2,
              behavior: reduceMotion ? 'auto' : 'smooth'
            });
          } else {
            c.removeAttribute('aria-current');
          }
        });
      });
    }, { rootMargin: '-30% 0px -60% 0px' });
    sections.forEach(function (s) { observer.observe(s); });
  }

  form.addEventListener('submit', function () {
    payBtn.disabled = true;
    payBtn.textContent = 'Opening payment page...';
  });

  // Coming back with the browser Back button: restore the button
  window.addEventListener('pageshow', function () {
    payBtn.disabled = false;
    payBtn.textContent = label;
    update();
  });

  update();
})();