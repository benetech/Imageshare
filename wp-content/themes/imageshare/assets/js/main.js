const advancedBtn = document.querySelector(".advanced-btn");
advancedBtn &&
  advancedBtn.addEventListener("click", (event) => {
    event.stopPropagation();
    if (event.currentTarget.getAttribute("aria-expanded") === "false") {
      event.currentTarget.setAttribute("aria-expanded", "true");
      document.querySelector(".refinements-wrapper").style.display = "flex";
    } else {
      event.currentTarget.setAttribute("aria-expanded", "false");
      document.querySelector(".refinements-wrapper").style.display = "none";
    }
  });

const filters = document.querySelectorAll("input.search-filter");
filters && filters.forEach(function (filter) {
  filter.addEventListener('click', function (event) {
    const checked = this.checked;
    const id = filter.dataset.filter;
    const value = this.value;
    const fieldName = id + '_' + value;

    if (!checked) {
        const field = document.querySelector('input[type="hidden"][name="'+ fieldName + '"]');
        if (field === null) { return; }
        field.removeAttribute('name');
        field.setAttribute('_name', fieldName);
    } else {
        const field = document.querySelector('input[type="hidden"][_name="'+ fieldName + '"]');
        if (field === null) { return; }
        field.removeAttribute('_name');
        field.setAttribute('name', fieldName);
    }
  });
});

document.querySelectorAll(".tab").forEach((tab, index, arr) => {
  tab.addEventListener("click", (e) => {
    const i = index;
    resetTabs(arr);
    selectTab(i, arr);
  });
  tab.addEventListener("keydown", (e) => {
    const i = index;

    if (e.keyCode === 36) {
      arr[0].focus();
    }

    if (e.keyCode == 35) {
      arr[arr.length - 1].focus();
    }

    if (e.keyCode === 37) {
      // move backward
      resetTabs(arr);
      selectTab(i > 0 ? i - 1 : arr.length - 1, arr);
    }

    if (e.keyCode === 39) {
      // move forward
      resetTabs(arr);
      selectTab(i < arr.length - 1 ? i + 1 : 0, arr);
    }
  });
});

const selectTab = (index, arr) => {
  arr[index].setAttribute("aria-selected", true);
  arr[index].removeAttribute("tabindex");
  arr[index].focus();
  document
    .getElementById(arr[index].getAttribute("aria-controls"))
    .removeAttribute("hidden");
};

const resetTabs = (tabs) => {
  tabs.forEach((tab) => {
    document
      .getElementById(tab.getAttribute("aria-controls"))
      .setAttribute("hidden", "hidden");
    tab.setAttribute("tabindex", -1);
    tab.setAttribute("aria-selected", false);
  });
};

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
