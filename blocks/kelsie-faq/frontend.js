(function () {
  const blocks = document.querySelectorAll('.kelsie-faq-list');
  if (!blocks.length) return;

  blocks.forEach(init);

  function init(blockEl) {
    const list = blockEl.querySelector('.kelsie-faq-list__items');
    if (!list) return;

    const items = Array.from(list.querySelectorAll('.kelsie-faq-list__item'));
    const select = blockEl.querySelector('.kelsie-faq-list__filter');
    const search = blockEl.querySelector('.kelsie-faq-list__search');
    const count  = blockEl.querySelector('.kelsie-faq-list__count');

    // 1) Build category list from data-cats
    const cats = new Set();
    items.forEach(it => {
      const raw = (it.getAttribute('data-cats') || '').split('|').filter(Boolean);
      raw.forEach(c => cats.add(c));
    });

    // Populate select (humanize labels)
    if (select && cats.size) {
      const frag = document.createDocumentFragment();
      Array.from(cats).sort().forEach(c => {
        const opt = document.createElement('option');
        opt.value = c;
        opt.textContent = c.replace(/-/g, ' ').replace(/\b\w/g, m => m.toUpperCase());
        frag.appendChild(opt);
      });
      select.appendChild(frag);
    }

    // 2) Filter logic
    function normalize(s) { return (s || '').toLowerCase(); }

    function applyFilter() {
      const cat   = (select && select.value) ? select.value : '';
      const term  = normalize(search && search.value ? search.value : '');

      let visible = 0;

      items.forEach(it => {
        const itCats = (it.getAttribute('data-cats') || '').split('|').filter(Boolean);
        const q = it.querySelector('.kelsie-faq-list__question');
        const a = it.querySelector('.kelsie-faq-list__answer');
        const text = normalize((q ? q.textContent : '') + ' ' + (a ? a.textContent : ''));

        const matchCat  = !cat || itCats.includes(cat);
        const matchTerm = !term || text.includes(term);

        const show = matchCat && matchTerm;
        it.style.display = show ? '' : 'none';
        if (show) visible++;
      });

      if (count) {
        count.textContent = visible === items.length
          ? `${visible} items`
          : `${visible} of ${items.length} shown`;
      }
    }

    // 3) Events (debounced input for nicer feel)
    if (select) select.addEventListener('change', applyFilter);
    if (search) {
      let t;
      search.addEventListener('input', () => {
        clearTimeout(t);
        t = setTimeout(applyFilter, 120);
      });
    }

    // 4) Init state
    applyFilter();
  }
})();
