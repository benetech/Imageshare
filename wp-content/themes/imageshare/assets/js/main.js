const advancedBtn = document.querySelector(".advanced-btn");
advancedBtn &&
  advancedBtn.addEventListener("click", (event) => {
    event.stopPropagation();
    if (event.currentTarget.getAttribute("aria-expanded") === "false") {
      event.currentTarget.parentNode.classList.add('expanded');
      event.currentTarget.setAttribute("aria-expanded", "true");
      document.querySelector(".refinements-wrapper").style.display = "flex";
    } else {
      event.currentTarget.parentNode.classList.remove('expanded');
      event.currentTarget.setAttribute("aria-expanded", "false");
      document.querySelector(".refinements-wrapper").style.display = "none";
    }
  });

const removeElement = (el) => {
  el.remove();
};

const announcement = (msg, selector, time) => {
  const el = document.querySelector(selector);
  el.innerHTML = msg;
  setTimeout(() => {
    el.innerHTML = "";
  }, time);
};

if (resetBtn = document.querySelector('button#reset')) {
  resetBtn.removeAttribute('hidden');
  resetBtn.addEventListener("click", (event) => {
    document.getElementById('search').value = '';
    if (filters = document.querySelector('#search-filters')) {
        filters.remove();
    }
    document.querySelector('#subject').selectedIndex = 0;
    document.querySelector('#type').selectedIndex = 0;
    document.querySelector('#acc').selectedIndex = 0;
    announcement('Search form has been reset.', '#announcement', 3000);
  });
}
