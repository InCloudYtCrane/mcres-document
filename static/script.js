/* ---------- 移动端菜单开关 ---------- */
function openMenu() {
  document.body.classList.add('menu-open');
}

function closeMenu() {
  document.body.classList.remove('menu-open');
}

/* ---------- 移动端 TOC 折叠 ---------- */
function toggleToc() {
  const toc = document.querySelector('.toc-sidebar');
  if (toc) toc.classList.toggle('open');
}

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') closeMenu();
});


/* ---------- 侧边栏折叠 + localStorage 记忆 ---------- */
function updateGroupIcon(li) {
  const icon = li.querySelector(':scope > .nav-group-header .nav-group-icon');
  if (!icon) return;
  if (li.classList.contains('collapsed')) {
    icon.classList.remove('fa-folder-open');
    icon.classList.add('fa-folder');
  } else {
    icon.classList.remove('fa-folder');
    icon.classList.add('fa-folder-open');
  }
}

function toggleGroup(el) {
  const li = el.closest('.nav-group-item');
  if (!li) return;

  li.classList.toggle('collapsed');
  updateGroupIcon(li);

  const id = li.dataset.groupId;
  if (!id) return;
  try {
    const states = JSON.parse(localStorage.getItem('navGroupStates') || '{}');
    states[id] = !li.classList.contains('collapsed');
    localStorage.setItem('navGroupStates', JSON.stringify(states));
  } catch (e) {}
}

function markNavReady() {
  requestAnimationFrame(function () {
    document.documentElement.classList.add('nav-ready');
  });
}
document.addEventListener('DOMContentLoaded', markNavReady);
window.addEventListener('pageshow', markNavReady);


/* ---------- 搜索 ---------- */
document.addEventListener('DOMContentLoaded', function () {
  const input = document.getElementById('search-input');
  const results = document.getElementById('search-results');
  if (!input || !results) return;

  let timer = null;

  function escapeHtml(s) {
    return s.replace(/[&<>"']/g, function (c) {
      return {
        '&': '&amp;', '<': '&lt;', '>': '&gt;',
        '"': '&quot;', "'": '&#39;'
      }[c];
    });
  }

  function highlight(text, q) {
    if (!q) return escapeHtml(text);
    const escaped = escapeHtml(text);
    const re = new RegExp(
      '(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi'
    );
    return escaped.replace(re, '<mark>$1</mark>');
  }

  function renderResults(items, q) {
    if (!items.length) {
      results.innerHTML = '<div class="search-empty">没有找到结果</div>';
      results.classList.add('show');
      return;
    }
    results.innerHTML = items.map(function (item) {
      return '<a class="search-item" href="' + item.url + '">' +
             '<div class="search-item-title">' + highlight(item.title, q) + '</div>' +
             '<div class="search-item-snippet">' + highlight(item.snippet, q) + '</div>' +
             '</a>';
    }).join('');
    results.classList.add('show');
  }

  input.addEventListener('input', function () {
    const q = input.value.trim();
    clearTimeout(timer);

    if (!q) {
      results.classList.remove('show');
      results.innerHTML = '';
      return;
    }

    timer = setTimeout(function () {
      fetch('/api/search?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => renderResults(data, q))
        .catch(() => {
          results.innerHTML = '<div class="search-empty">搜索失败</div>';
          results.classList.add('show');
        });
    }, 200);
  });

  document.addEventListener('click', function (e) {
    if (!input.contains(e.target) && !results.contains(e.target)) {
      results.classList.remove('show');
    }
  });

  input.addEventListener('focus', function () {
    if (results.innerHTML && input.value.trim()) {
      results.classList.add('show');
    }
  });
});


/* ---------- TOC 平滑滚动 ---------- */
document.querySelectorAll('.toc-sidebar a').forEach(function (link) {
  link.addEventListener('click', function (e) {
    const href = this.getAttribute('href') || '';
    if (!href.startsWith('#')) return;

    const id = decodeURIComponent(href.slice(1));
    const target = document.getElementById(id);
    if (!target) return;

    e.preventDefault();
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    history.pushState(null, '', '#' + id);

    document.querySelectorAll('.toc-sidebar a').forEach(function (a) {
      a.classList.remove('active');
    });
    this.classList.add('active');
  });
});


/* ---------- TOC 滚动高亮 ---------- */
document.addEventListener('DOMContentLoaded', function () {
  const tocLinks = document.querySelectorAll('.toc-sidebar a');
  const headings = document.querySelectorAll(
    '.content h1, .content h2, .content h3, .content h4'
  );
  if (!tocLinks.length || !headings.length) return;

  function setActive(id) {
    tocLinks.forEach(function (a) {
      const href = decodeURIComponent((a.getAttribute('href') || '').slice(1));
      a.classList.toggle('active', href === id);
    });
  }

  const observer = new IntersectionObserver(function (entries) {
    const visible = entries.filter(e => e.isIntersecting).map(e => e.target);
    if (visible.length) setActive(visible[0].id);
  }, {
    rootMargin: '-80px 0px -70% 0px',
    threshold: 0,
  });

  headings.forEach(function (h) { observer.observe(h); });

  if (location.hash) {
    const id = decodeURIComponent(location.hash.slice(1));
    const target = document.getElementById(id);
    if (target) {
      target.scrollIntoView({ behavior: 'auto', block: 'start' });
      setActive(id);
    }
  }
});