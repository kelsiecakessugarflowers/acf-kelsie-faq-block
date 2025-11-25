(function () {
  const blocks = document.querySelectorAll('.kelsie-faq-list');
  if (!blocks.length) return;

  blocks.forEach(init);

  function init(blockEl) {
    const list   = blockEl.querySelector('.kelsie-faq-list__items');
    const items  = list ? Array.from(list.querySelectorAll('.kelsie-faq-list__item')) : [];
    const select = blockEl.querySelector('.kelsie-faq-list__filter');
    const search = blockEl.querySelector('.kelsie-faq-list__search');
    const count  = blockEl.querySelector('.kelsie-faq-list__count');

    if (!list || !items.length) return;

    // ---------- Utilities ----------
    const norm = (s) =>
      (s || '')
        .toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .trim();

    // If server didn't populate categories (only "All" present), build from data-cats.
    if (select) {
      const hasServerOptions = select.options && select.options.length > 1;
      if (!hasServerOptions) {
        const cats = new Set();
        items.forEach((it) => {
          const raw = (it.getAttribute('data-cats') || '')
            .split('|')
            .filter(Boolean);
          raw.forEach((c) => cats.add(c));
        });
        if (cats.size) {
          const frag = document.createDocumentFragment();
          Array.from(cats)
            .sort()
            .forEach((c) => {
              const opt = document.createElement('option');
              opt.value = c;
              opt.textContent = c.replace(/-/g, ' ').replace(/\b\w/g, (m) => m.toUpperCase());
              frag.appendChild(opt);
            });
          select.appendChild(frag);
        }
      }
    }

    // Precompute searchable text per item (question + answer + cats)
    const haystack = new WeakMap();
    items.forEach((el) => {
      const q = el.querySelector('.kelsie-faq-list__question');
      const a = el.querySelector('.kelsie-faq-list__answer');
      const cats = el.getAttribute('data-cats') || '';
      haystack.set(el, norm((q ? q.textContent : '') + ' ' + (a ? a.textContent : '') + ' ' + cats));
    });

    function applyFilter() {
      const cat  = select && select.value ? select.value : '';
      const term = norm(search && search.value ? search.value : '');

      let visible = 0;
      items.forEach((it) => {
        const itCats = (it.getAttribute('data-cats') || '').split('|').filter(Boolean);
        const matchCat  = !cat || itCats.includes(cat);
        const matchTerm = !term || haystack.get(it).includes(term);
        const show = matchCat && matchTerm;
        it.style.display = show ? '' : 'none';
        if (show) visible++;
      });

      if (count) {
        count.textContent = visible === 1 ? '1 review' : `${visible} reviews`;
      }
    }

    // Events (debounced input)
    if (select) select.addEventListener('change', applyFilter);
    if (search) {
      let t;
      search.addEventListener('input', () => {
        clearTimeout(t);
        t = setTimeout(applyFilter, 120);
      });
    }

    // Init
    applyFilter();
  }
})();
